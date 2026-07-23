<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Project;
use App\Models\Timesheet;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\ProjectAllocationSpreadsheetService;
use App\Services\ProjectAssignmentSpreadsheetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Throwable;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $search = $filters['search'] ?? null;
        $status = $filters['status'] ?? null;

        $projects = Project::query()
            ->select(['id', 'project_code', 'project_name', 'client_name', 'is_active'])
            ->withCount('entries')
            ->when($search, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('project_code', 'like', "%{$search}%")
                        ->orWhere('project_name', 'like', "%{$search}%")
                        ->orWhere('client_name', 'like', "%{$search}%");
                });
            })
            ->when($status, fn ($query, $status) => $query->where('is_active', $status === 'active'))
            ->orderBy('project_code')
            ->paginate(20)
            ->withQueryString();

        return view('manage.projects.index', compact('projects', 'search', 'status'));
    }

    public function create()
    {
        return view('manage.projects.form', [
            'project' => new Project(['timesheet_assignment_mode' => Project::ASSIGNMENT_SELECTED_USERS]),
            'timesheetUsers' => $this->timesheetUsers(),
            'assignedUserIds' => collect(),
            'assignedUserCategories' => collect(),
            'projectManagers' => $this->projectManagers(),
            'departments' => Department::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'allocationHours' => collect(),
            'manpowerCategorySettings' => collect(),
            'controlledDepartmentIds' => collect(),
            'legacyCategoryDepartmentIds' => collect(),
        ]);
    }

    public function store(Request $request, AuditLogService $audit)
    {
        $validated = $this->validated($request);
        $assignmentImportToken = $validated['assignment_import_token'] ?? null;
        $assignmentImportApplied = $this->validateAssignmentImportToken($request, null, $assignmentImportToken);
        $allocationImportToken = $validated['allocation_import_token'] ?? null;
        $allocationImport = $this->validateAllocationImportToken($request, null, $allocationImportToken);
        $assignedUserIds = $validated['assigned_user_ids'] ?? [];
        $assignedUserCategories = $this->assignedUserCategories($assignedUserIds, $validated['assigned_user_categories'] ?? []);
        $allocations = $validated['department_allocations'] ?? [];
        $categorySettings = $this->normalizeManpowerCategorySettings($validated, $allocations);
        $this->ensureControlledProjectsUseSelectedAccess($validated, $categorySettings);
        if (collect($allocations)->filter(fn ($hours) => filled($hours) && (float) $hours > 0)->isEmpty()) {
            throw ValidationException::withMessages(['department_allocations' => 'Allocate manhours to at least one department.']);
        }
        $this->validateProjectAssignments($assignedUserCategories, $allocations, $categorySettings);
        $assignmentImportSummary = $assignmentImportApplied
            ? $this->assignmentImportSummary(collect(), collect($assignedUserCategories)->map(fn ($pivot) => $pivot['manpower_category']))
            : null;
        $allocationImportSummary = $allocationImport
            ? $this->allocationImportSummary($allocationImport, $allocations, $categorySettings)
            : null;
        unset($validated['assigned_user_ids'], $validated['assigned_user_categories'], $validated['department_allocations'], $validated['job_level_controls'], $validated['job_level_allocations'], $validated['allocation_change_reason'], $validated['assignment_import_token'], $validated['allocation_import_token']);

        $project = DB::transaction(function () use ($validated, $assignedUserCategories, $allocations, $categorySettings, $audit, $assignmentImportSummary, $allocationImportSummary) {
            $project = Project::create($validated);
            $project->assignedUsers()->sync($assignedUserCategories);
            $this->syncAllocations($project, $allocations, $categorySettings);
            $audit->record('project_created', $project, null, $this->auditValues($project));
            if ($assignmentImportSummary !== null) {
                $audit->record('project_assignment_excel_imported', $project, null, $assignmentImportSummary);
            }
            if ($allocationImportSummary !== null) {
                $audit->record('project_allocation_excel_imported', $project, null, $allocationImportSummary);
            }

            return $project;
        });
        $this->forgetAssignmentImportToken($request, $assignmentImportToken);
        $this->forgetAllocationImportToken($request, $allocationImportToken);

        return redirect()->route('manage.projects.index')->with('success', 'Project created.');
    }

    public function edit(Project $project)
    {
        $project->load('departmentAllocations.manpowerCategoryAllocations');
        $assignedUsers = $project->assignedUsers()->get();

        $canonicalCategories = array_keys(config('manpower_categories.labels'));

        return view('manage.projects.form', [
            'project' => $project,
            'timesheetUsers' => $this->timesheetUsers(),
            'assignedUserIds' => $assignedUsers->pluck('id'),
            'assignedUserCategories' => $assignedUsers->mapWithKeys(fn (User $user) => [
                $user->id => $user->pivot->manpower_category,
            ]),
            'projectManagers' => $this->projectManagers($project),
            'departments' => Department::query()->where('is_active', true)
                ->orWhereIn('id', $project->departmentAllocations()->pluck('department_id'))->orderBy('name')->get(['id', 'name', 'code', 'is_active']),
            'allocationHours' => $project->departmentAllocations()->pluck('allocated_hours', 'department_id'),
            'manpowerCategorySettings' => $project->departmentAllocations->mapWithKeys(fn ($allocation) => [
                $allocation->department_id => $allocation->manpowerCategoryAllocations
                    ->whereIn('manpower_category', $canonicalCategories)
                    ->pluck('allocated_hours', 'manpower_category'),
            ]),
            'controlledDepartmentIds' => $project->departmentAllocations->filter(fn ($allocation) => $allocation->manpowerCategoryAllocations->isNotEmpty())->pluck('department_id'),
            'legacyCategoryDepartmentIds' => $project->departmentAllocations->filter(fn ($allocation) => $allocation->manpowerCategoryAllocations
                ->contains(fn ($item) => ! in_array($item->manpower_category, $canonicalCategories, true)))->pluck('department_id'),
        ]);
    }

    public function assignmentTemplate(Request $request, ProjectAssignmentSpreadsheetService $spreadsheets)
    {
        $validated = $request->validate([
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
        ]);
        $project = filled($validated['project_id'] ?? null)
            ? Project::findOrFail((int) $validated['project_id'])
            : null;
        $spreadsheet = $spreadsheets->template($project);
        $prefix = $project ? Str::slug($project->project_code, '_') : 'new_project';
        $fileName = $prefix.'_assignment_template_'.now()->format('Ymd_His').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            try {
                (new Xlsx($spreadsheet))->save('php://output');
            } finally {
                $spreadsheet->disconnectWorksheets();
            }
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function allocationTemplate(Request $request, ProjectAllocationSpreadsheetService $spreadsheets)
    {
        $validated = $request->validate([
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
        ]);
        $project = filled($validated['project_id'] ?? null)
            ? Project::findOrFail((int) $validated['project_id'])
            : null;
        $spreadsheet = $spreadsheets->template($project);
        $prefix = $project ? Str::slug($project->project_code, '_') : 'new_project';
        $fileName = $prefix.'_department_allocations_'.now()->format('Ymd_His').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            try {
                (new Xlsx($spreadsheet))->save('php://output');
            } finally {
                $spreadsheet->disconnectWorksheets();
            }
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function previewAssignmentImport(
        Request $request,
        ProjectAssignmentSpreadsheetService $spreadsheets,
    ) {
        $uploadedFile = $request->file('assignment_file');
        $path = $uploadedFile?->getRealPath();

        try {
            $validated = $request->validate([
                'assignment_file' => ['required', 'file', 'extensions:xlsx', 'mimes:xlsx', 'max:5120'],
                'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
                'department_allocations' => ['nullable', 'array'],
                'job_level_controls' => ['nullable', 'array'],
                'job_level_allocations' => ['nullable', 'array'],
                'assigned_user_ids' => ['nullable', 'array'],
                'assigned_user_categories' => ['nullable', 'array'],
            ], [
                'assignment_file.required' => 'Choose an Excel assignment file.',
                'assignment_file.extensions' => 'Upload an .xlsx file.',
                'assignment_file.mimes' => 'Upload a valid .xlsx file.',
                'assignment_file.max' => 'The assignment file may not exceed 5 MB.',
            ]);

            $project = filled($validated['project_id'] ?? null)
                ? Project::findOrFail((int) $validated['project_id'])
                : null;
            if (! $path) {
                return response()->json([
                    'message' => 'The uploaded Excel file could not be read.',
                    'valid' => false,
                    'errors' => ['The uploaded Excel file could not be read.'],
                ], 422);
            }

            try {
                $preview = $spreadsheets->preview($path, $request->all(), $project);
            } catch (Throwable $exception) {
                report($exception);

                return response()->json([
                    'message' => 'The Excel preview could not be prepared. Check the workbook and try again.',
                    'valid' => false,
                    'errors' => ['The Excel preview could not be prepared. Check the workbook and try again.'],
                ], 422);
            }

            $preview['token'] = $preview['valid']
                ? $this->storeAssignmentImportToken($request, $project)
                : null;

            return response()->json($preview);
        } finally {
            if ($path && is_file($path)) {
                @unlink($path);
            }
        }
    }

    public function previewAllocationImport(
        Request $request,
        ProjectAllocationSpreadsheetService $spreadsheets,
    ) {
        $uploadedFile = $request->file('allocation_file');
        $path = $uploadedFile?->getRealPath();

        try {
            $validated = $request->validate([
                'allocation_file' => ['required', 'file', 'extensions:xlsx', 'mimes:xlsx', 'max:5120'],
                'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
                'department_allocations' => ['nullable', 'array'],
                'job_level_controls' => ['nullable', 'array'],
                'job_level_allocations' => ['nullable', 'array'],
                'assigned_user_ids' => ['nullable', 'array'],
                'assigned_user_categories' => ['nullable', 'array'],
            ], [
                'allocation_file.required' => 'Choose an Excel allocation file.',
                'allocation_file.extensions' => 'Upload an .xlsx file.',
                'allocation_file.mimes' => 'Upload a valid .xlsx file.',
                'allocation_file.max' => 'The allocation file may not exceed 5 MB.',
            ]);

            $project = filled($validated['project_id'] ?? null)
                ? Project::findOrFail((int) $validated['project_id'])
                : null;
            if (! $path) {
                return response()->json([
                    'message' => 'The uploaded Excel file could not be read.',
                    'valid' => false,
                    'errors' => ['The uploaded Excel file could not be read.'],
                ], 422);
            }

            try {
                $preview = $spreadsheets->preview($path, $request->all(), $project);
            } catch (Throwable $exception) {
                report($exception);

                return response()->json([
                    'message' => 'The allocation preview could not be prepared. Check the workbook and try again.',
                    'valid' => false,
                    'errors' => ['The allocation preview could not be prepared. Check the workbook and try again.'],
                ], 422);
            }

            $preview['token'] = $preview['valid']
                ? $this->storeAllocationImportToken($request, $project, $preview)
                : null;

            return response()->json($preview);
        } finally {
            if ($path && is_file($path)) {
                @unlink($path);
            }
        }
    }

    public function update(Request $request, Project $project, AuditLogService $audit)
    {
        $validated = $this->validated($request, $project);
        $assignmentImportToken = $validated['assignment_import_token'] ?? null;
        $assignmentImportApplied = $this->validateAssignmentImportToken($request, $project, $assignmentImportToken);
        $allocationImportToken = $validated['allocation_import_token'] ?? null;
        $allocationImport = $this->validateAllocationImportToken($request, $project, $allocationImportToken);
        $assignedUserIds = $validated['assigned_user_ids'] ?? [];
        $assignedUserCategories = $this->assignedUserCategories($assignedUserIds, $validated['assigned_user_categories'] ?? []);
        $allocations = $validated['department_allocations'] ?? [];
        $categorySettings = $this->normalizeManpowerCategorySettings($validated, $allocations);
        $this->ensureControlledProjectsUseSelectedAccess($validated, $categorySettings);
        if (collect($allocations)->filter(fn ($hours) => filled($hours) && (float) $hours > 0)->isEmpty()) {
            throw ValidationException::withMessages(['department_allocations' => 'Allocate manhours to at least one department.']);
        }
        $this->validateProjectAssignments($assignedUserCategories, $allocations, $categorySettings, $project);
        $reason = $validated['allocation_change_reason'] ?? null;
        unset($validated['assigned_user_ids'], $validated['assigned_user_categories'], $validated['department_allocations'], $validated['job_level_controls'], $validated['job_level_allocations'], $validated['allocation_change_reason'], $validated['assignment_import_token'], $validated['allocation_import_token']);
        $oldAllocations = $this->allocationAuditValues($project);
        $newAllocations = $this->normalizedAllocationAuditValues($allocations, $categorySettings);
        $allocationsChanged = $oldAllocations !== $newAllocations;
        if ($allocationsChanged && blank($reason)) {
            throw ValidationException::withMessages(['allocation_change_reason' => 'Explain why the manhour allocation is changing.']);
        }
        $old = $this->auditValues($project);
        $oldAssignments = $this->assignmentMap($project);
        $newAssignments = collect($assignedUserCategories)->map(fn ($pivot) => $pivot['manpower_category']);
        $assignmentImportSummary = $assignmentImportApplied
            ? $this->assignmentImportSummary($oldAssignments, $newAssignments)
            : null;
        $allocationImportSummary = $allocationImport
            ? $this->allocationImportSummary($allocationImport, $allocations, $categorySettings, $reason)
            : null;

        DB::transaction(function () use ($project, $validated, $assignedUserCategories, $allocations, $categorySettings, $audit, $old, $oldAllocations, $newAllocations, $allocationsChanged, $reason, $assignmentImportSummary, $allocationImportSummary) {
            $this->validateAllocationChanges($project, $allocations, $categorySettings);
            $project->update($validated);
            $project->assignedUsers()->sync($assignedUserCategories);
            $this->syncAllocations($project, $allocations, $categorySettings);
            $audit->record('project_updated', $project, $old, $this->auditValues($project));
            if ($allocationsChanged) {
                $audit->record('project_allocations_updated', $project, $oldAllocations, $newAllocations + ['reason' => $reason]);
            }
            if ($assignmentImportSummary !== null) {
                $audit->record('project_assignment_excel_imported', $project, null, $assignmentImportSummary);
            }
            if ($allocationImportSummary !== null) {
                $audit->record('project_allocation_excel_imported', $project, $oldAllocations, $allocationImportSummary);
            }
        });
        $this->forgetAssignmentImportToken($request, $assignmentImportToken);
        $this->forgetAllocationImportToken($request, $allocationImportToken);

        return redirect()->route('manage.projects.index')->with('success', 'Project updated.');
    }

    public function status(Project $project, AuditLogService $audit)
    {
        $old = $project->toArray();
        $project->update(['is_active' => ! $project->is_active]);
        $audit->record($project->is_active ? 'project_activated' : 'project_deactivated', $project, $old, $project->fresh()->toArray());

        return redirect()
            ->route('manage.projects.index')
            ->with('success', $project->is_active ? 'Project reactivated.' : 'Project deactivated.');
    }

    public function destroy(Project $project, AuditLogService $audit)
    {
        $project->loadCount('entries');

        if ($project->entries_count > 0) {
            return redirect()
                ->route('manage.projects.index')
                ->with('error', 'This project has timesheet entries. Deactivate it instead of deleting it.');
        }

        $old = $project->toArray();
        $audit->record('project_deleted', $project, $old);
        $project->delete();

        return redirect()->route('manage.projects.index')->with('success', 'Unused project deleted.');
    }

    private function validated(Request $request, ?Project $project = null): array
    {
        return $request->validate([
            'project_code' => ['required', 'string', 'max:100', Rule::unique('projects')->ignore($project)],
            'project_name' => ['required', 'string', 'max:255'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'project_manager_id' => ['required', 'integer', Rule::exists('users', 'id')->where(fn ($query) => $query->where('is_active', true)->whereIn('role', ['employee', 'hod']))],
            'is_active' => ['boolean'],
            'timesheet_assignment_mode' => ['required', Rule::in([
                Project::ASSIGNMENT_ALL_USERS,
                Project::ASSIGNMENT_SELECTED_USERS,
            ])],
            'assigned_user_ids' => ['nullable', 'array'],
            'assigned_user_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('is_active', true)
                    ->whereIn('role', ['employee', 'hod'])),
            ],
            'assigned_user_categories' => ['nullable', 'array'],
            'assigned_user_categories.*' => ['nullable', Rule::in(array_keys(config('manpower_categories.labels')))],
            'assignment_import_token' => ['nullable', 'string', 'max:100'],
            'allocation_import_token' => ['nullable', 'string', 'max:100'],
            'department_allocations' => ['required', 'array', 'min:1'],
            'department_allocations.*' => ['nullable', 'numeric', 'min:0.25', 'max:9999999999.99'],
            'job_level_controls' => ['nullable', 'array'],
            'job_level_controls.*' => ['nullable', 'boolean'],
            'job_level_allocations' => ['nullable', 'array'],
            'job_level_allocations.*' => ['nullable', 'array'],
            'job_level_allocations.*.*.mode' => ['nullable', Rule::in(['shared', 'reserved', 'not_allowed'])],
            'job_level_allocations.*.*.hours' => ['nullable', 'numeric', 'min:0.25', 'max:9999999999.99'],
            'allocation_change_reason' => ['nullable', 'string', 'max:2000'],
        ]) + ['is_active' => false];
    }

    private function timesheetUsers()
    {
        return User::query()
            ->with('department:id,name')
            ->where('is_active', true)
            ->whereIn('role', ['employee', 'hod'])
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'employee_code', 'role', 'department_id'])
            ->sortBy(fn (User $user) => strtolower(($user->department?->name ?? 'ZZZZ No department').'|'.$user->name))
            ->groupBy(fn (User $user) => (string) ($user->department_id ?? 'unassigned'));
    }

    private function auditValues(Project $project): array
    {
        return $project->fresh()->toArray() + [
            'assigned_user_ids' => $project->assignedUsers()->orderBy('users.id')->pluck('users.id')->all(),
            'assigned_user_manpower_categories' => $project->assignedUsers()->orderBy('users.id')->get()
                ->mapWithKeys(fn (User $user) => [(string) $user->id => $user->pivot->manpower_category])->all(),
            'department_allocations' => $project->departmentAllocations()->orderBy('department_id')->pluck('allocated_hours', 'department_id')->all(),
            'manpower_category_allocations' => $this->allocationAuditValues($project),
        ];
    }

    private function projectManagers(?Project $project = null)
    {
        return User::query()->where(function ($query) use ($project) {
            $query->where(fn ($query) => $query->where('is_active', true)->whereIn('role', ['employee', 'hod']));
            if ($project?->project_manager_id) {
                $query->orWhere('id', $project->project_manager_id);
            }
        })->orderBy('name')->get(['id', 'name', 'email', 'role', 'is_active']);
    }

    private function assignedUserCategories(array $assignedUserIds, array $categories): array
    {
        return collect($assignedUserIds)
            ->mapWithKeys(function ($userId) use ($categories) {
                $category = $categories[$userId] ?? null;

                return [(int) $userId => [
                    'manpower_category' => filled($category) ? $category : null,
                ]];
            })
            ->all();
    }

    private function syncAllocations(Project $project, array $allocations, array $categorySettings): void
    {
        $rows = collect($allocations)->filter(fn ($hours) => filled($hours) && (float) $hours > 0);
        $project->departmentAllocations()->whereNotIn('department_id', $rows->keys())->delete();
        foreach ($rows as $departmentId => $hours) {
            $allocation = $project->departmentAllocations()->updateOrCreate(
                ['department_id' => (int) $departmentId],
                ['allocated_hours' => $hours],
            );
            $settings = $categorySettings[(int) $departmentId] ?? null;
            if ($settings === null) {
                $allocation->manpowerCategoryAllocations()->delete();

                continue;
            }
            foreach ($settings as $manpowerCategory => $allocatedHours) {
                $allocation->manpowerCategoryAllocations()->updateOrCreate(
                    ['manpower_category' => $manpowerCategory],
                    ['allocated_hours' => $allocatedHours],
                );
            }
            $allocation->manpowerCategoryAllocations()->whereNotIn('manpower_category', array_keys($settings))->delete();
        }
    }

    private function validateAllocationChanges(Project $project, array $allocations, array $categorySettings): void
    {
        $existing = $project->departmentAllocations()->with('manpowerCategoryAllocations')->lockForUpdate()->get()->keyBy('department_id');
        $usage = DB::table('timesheet_entries as entries')
            ->join('timesheets', 'timesheets.id', '=', 'entries.timesheet_id')
            ->where('entries.project_id', $project->id)
            ->whereIn('timesheets.status', [Timesheet::STATUS_APPROVED, Timesheet::STATUS_SUBMITTED])
            ->whereNotNull('entries.department_id')
            ->groupBy('entries.department_id')
            ->selectRaw('entries.department_id, SUM(entries.regular_hours + entries.overtime_hours) as consumed_hours')
            ->get()
            ->keyBy('department_id');

        foreach ($existing as $departmentId => $existingAllocation) {
            $newHours = $allocations[$departmentId] ?? null;
            $consumedHours = (float) ($usage->get($departmentId)->consumed_hours ?? 0);

            if (filled($newHours) && (float) $newHours + 0.0001 < $consumedHours) {
                throw ValidationException::withMessages(["department_allocations.$departmentId" => 'Allocation cannot be lower than '.number_format($consumedHours, 2).' submitted and approved hours already used.']);
            }
            if ((! filled($newHours) || (float) $newHours <= 0) && $consumedHours > 0) {
                throw ValidationException::withMessages(["department_allocations.$departmentId" => 'This department has approved or pending project hours and cannot be removed.']);
            }

            if (! filled($newHours) || ! isset($categorySettings[(int) $departmentId])) {
                if ($existingAllocation->manpowerCategoryAllocations->isNotEmpty()) {
                    $reservedConsumed = $this->reservedUsage($project, (int) $departmentId);
                    if ($reservedConsumed->sum() > 0) {
                        throw ValidationException::withMessages(["job_level_allocations.$departmentId" => 'Manpower Category controls cannot be removed while reserved hours are consumed.']);
                    }
                }

                continue;
            }

            $settings = $categorySettings[(int) $departmentId];
            $reservedConsumed = $this->reservedUsage($project, (int) $departmentId);
            foreach ($reservedConsumed as $manpowerCategory => $hours) {
                $newReserved = $settings[$manpowerCategory] ?? null;
                if ($hours > 0 && ($newReserved === null || (float) $newReserved + 0.0001 < $hours)) {
                    throw ValidationException::withMessages(["job_level_allocations.$departmentId.$manpowerCategory.hours" => 'Reservation cannot be lower than '.number_format($hours, 2).' submitted and approved hours already used.']);
                }
            }

            $sharedConsumed = $this->sharedUsage($project, (int) $departmentId);
            $reservedTotal = collect($settings)->filter(fn ($hours) => $hours !== null)->sum(fn ($hours) => (float) $hours);
            $sharedRemainder = (float) $newHours - $reservedTotal;
            if ($sharedRemainder + 0.0001 < $sharedConsumed) {
                throw ValidationException::withMessages(["job_level_allocations.$departmentId" => 'The shared remainder cannot be lower than '.number_format($sharedConsumed, 2).' submitted and approved hours already used.']);
            }
        }
    }

    private function normalizeManpowerCategorySettings(array $validated, array $departmentAllocations): array
    {
        $controls = $validated['job_level_controls'] ?? [];
        $input = $validated['job_level_allocations'] ?? [];
        $settings = [];

        foreach ($controls as $departmentId => $enabled) {
            if (! $enabled || ! filled($departmentAllocations[$departmentId] ?? null)) {
                continue;
            }

            $departmentId = (int) $departmentId;
            $total = (float) $departmentAllocations[$departmentId];
            $hasShared = false;
            $reservedTotal = 0.0;

            foreach (array_keys(config('manpower_categories.labels')) as $manpowerCategory) {
                $mode = $input[$departmentId][$manpowerCategory]['mode'] ?? 'shared';
                $hours = $input[$departmentId][$manpowerCategory]['hours'] ?? null;

                if ($mode === 'shared') {
                    $settings[$departmentId][$manpowerCategory] = null;
                    $hasShared = true;

                    continue;
                }

                if ($mode === 'not_allowed') {
                    $settings[$departmentId][$manpowerCategory] = 0.0;

                    continue;
                }

                if (! filled($hours) || (float) $hours <= 0) {
                    throw ValidationException::withMessages([
                        "job_level_allocations.$departmentId.$manpowerCategory.hours" => 'Enter reserved hours greater than zero.',
                    ]);
                }

                $settings[$departmentId][$manpowerCategory] = round((float) $hours, 2);
                $reservedTotal += (float) $hours;
            }

            if ($reservedTotal > $total + 0.0001) {
                throw ValidationException::withMessages([
                    "job_level_allocations.$departmentId" => 'Reserved Manpower Category hours cannot exceed the department allocation.',
                ]);
            }

            if (! $hasShared && abs($reservedTotal - $total) > 0.0001) {
                throw ValidationException::withMessages([
                    "job_level_allocations.$departmentId" => 'When no Manpower Category uses the shared remainder, reserved hours must equal the department allocation.',
                ]);
            }
        }

        return $settings;
    }

    private function reservedUsage(Project $project, int $departmentId)
    {
        $categories = array_keys(config('manpower_categories.labels'));

        return DB::table('timesheet_entries as entries')
            ->join('timesheets', 'timesheets.id', '=', 'entries.timesheet_id')
            ->where('entries.project_id', $project->id)
            ->where('entries.department_id', $departmentId)
            ->where('entries.allocation_bucket_snapshot', 'reserved')
            ->whereIn('entries.manpower_category_snapshot', $categories)
            ->whereIn('timesheets.status', [Timesheet::STATUS_APPROVED, Timesheet::STATUS_SUBMITTED])
            ->groupBy('entries.manpower_category_snapshot')
            ->selectRaw('entries.manpower_category_snapshot, SUM(entries.regular_hours + entries.overtime_hours) as consumed_hours')
            ->get()
            ->pluck('consumed_hours', 'manpower_category_snapshot');
    }

    private function sharedUsage(Project $project, int $departmentId): float
    {
        $categories = array_keys(config('manpower_categories.labels'));

        return (float) DB::table('timesheet_entries as entries')
            ->join('timesheets', 'timesheets.id', '=', 'entries.timesheet_id')
            ->where('entries.project_id', $project->id)
            ->where('entries.department_id', $departmentId)
            ->whereIn('timesheets.status', [Timesheet::STATUS_APPROVED, Timesheet::STATUS_SUBMITTED])
            ->where(function ($query) use ($categories) {
                $query->where('entries.allocation_bucket_snapshot', 'shared')
                    ->orWhereNull('entries.allocation_bucket_snapshot')
                    ->orWhereNull('entries.manpower_category_snapshot')
                    ->orWhereNotIn('entries.manpower_category_snapshot', $categories);
            })
            ->sum(DB::raw('entries.regular_hours + entries.overtime_hours'));
    }

    private function allocationAuditValues(Project $project): array
    {
        return $project->departmentAllocations()->with('manpowerCategoryAllocations')->orderBy('department_id')->get()
            ->mapWithKeys(fn ($allocation) => [(string) $allocation->department_id => [
                'allocated_hours' => number_format((float) $allocation->allocated_hours, 2, '.', ''),
                'manpower_categories' => $allocation->manpowerCategoryAllocations->sortBy('manpower_category')->mapWithKeys(fn ($row) => [
                    $row->manpower_category => $row->allocated_hours === null ? null : number_format((float) $row->allocated_hours, 2, '.', ''),
                ])->all(),
            ]])->all();
    }

    private function normalizedAllocationAuditValues(array $allocations, array $categorySettings): array
    {
        return collect($allocations)->filter(fn ($hours) => filled($hours) && (float) $hours > 0)
            ->sortKeys()->mapWithKeys(function ($hours, $departmentId) use ($categorySettings) {
                $categories = collect($categorySettings[(int) $departmentId] ?? [])->sortKeys()->map(fn ($value) => $value === null ? null : number_format((float) $value, 2, '.', ''))->all();

                return [(string) $departmentId => [
                    'allocated_hours' => number_format((float) $hours, 2, '.', ''),
                    'manpower_categories' => $categories,
                ]];
            })->all();
    }

    private function ensureControlledProjectsUseSelectedAccess(array $validated, array $categorySettings): void
    {
        if ($categorySettings !== [] && ($validated['timesheet_assignment_mode'] ?? null) !== Project::ASSIGNMENT_SELECTED_USERS) {
            throw ValidationException::withMessages([
                'timesheet_assignment_mode' => 'Projects with Manpower Category controls must use Selected users access.',
            ]);
        }
    }

    private function validateProjectAssignments(
        array $assignments,
        array $allocations,
        array $categorySettings,
        ?Project $project = null,
    ): void {
        $allocatedDepartmentIds = collect($allocations)
            ->filter(fn ($hours) => filled($hours) && (float) $hours > 0)
            ->keys()
            ->map(fn ($id) => (int) $id);
        $hasUncontrolled = $allocatedDepartmentIds
            ->contains(fn ($departmentId) => ! array_key_exists($departmentId, $categorySettings));
        $allowedCategories = collect($categorySettings)
            ->flatMap(fn ($settings) => collect($settings)
                ->filter(fn ($hours) => $hours === null || (float) $hours > 0)
                ->keys())
            ->unique();
        $users = User::query()
            ->whereIn('id', array_keys($assignments))
            ->get(['id', 'name'])
            ->keyBy('id');
        $errors = [];

        foreach ($assignments as $userId => $pivot) {
            $category = $pivot['manpower_category'] ?? null;
            $name = $users->get((int) $userId)?->name ?? 'This user';

            if ($category !== null && ! $allowedCategories->contains($category)) {
                $errors["assigned_user_categories.$userId"] = $name."'s category is not Shared or Reserved in any controlled discipline.";
            }
            if ($category === null && ! $hasUncontrolled) {
                $errors["assigned_user_categories.$userId"] = $name.' needs a Manpower Category because this project has no uncontrolled discipline allocation.';
            }
        }

        if ($project) {
            $removedUserIds = $project->assignedUsers()->pluck('users.id')
                ->map(fn ($id) => (int) $id)
                ->diff(array_map('intval', array_keys($assignments)))
                ->values();
            if ($removedUserIds->isNotEmpty()) {
                $blockers = DB::table('timesheet_entries as entries')
                    ->join('timesheets', 'timesheets.id', '=', 'entries.timesheet_id')
                    ->join('users', 'users.id', '=', 'timesheets.user_id')
                    ->where('entries.project_id', $project->id)
                    ->whereIn('timesheets.user_id', $removedUserIds)
                    ->whereIn('timesheets.status', ProjectAssignmentSpreadsheetService::EDITABLE_TIMESHEET_STATUSES)
                    ->groupBy('timesheets.user_id', 'users.name', 'timesheets.status')
                    ->selectRaw('timesheets.user_id, users.name, timesheets.status, COUNT(entries.id) as entry_count')
                    ->get()
                    ->groupBy('user_id');

                if ($blockers->isNotEmpty()) {
                    $descriptions = $blockers->map(function ($usage) {
                        $user = $usage->first();
                        $statuses = $usage->map(fn ($item) => Str::headline($item->status).' ('.$item->entry_count.')')->implode(', ');

                        return $user->name.': '.$statuses;
                    })->implode('; ');
                    $errors['assigned_user_ids'] = 'Resolve or delete editable project timesheet rows before removing assignments. '.$descriptions;
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function assignmentMap(Project $project)
    {
        return $project->assignedUsers()->get(['users.id'])->mapWithKeys(fn (User $user) => [
            (int) $user->id => $user->pivot->manpower_category,
        ]);
    }

    private function assignmentImportSummary($oldAssignments, $newAssignments): array
    {
        $oldAssignments = collect($oldAssignments);
        $newAssignments = collect($newAssignments);
        $sharedUserIds = $oldAssignments->keys()->intersect($newAssignments->keys());

        return [
            'source' => 'excel_import',
            'assigned_count' => $newAssignments->keys()->diff($oldAssignments->keys())->count(),
            'category_changed_count' => $sharedUserIds
                ->filter(fn ($userId) => $oldAssignments->get($userId) !== $newAssignments->get($userId))
                ->count(),
            'removed_count' => $oldAssignments->keys()->diff($newAssignments->keys())->count(),
            'uncontrolled_only_count' => $newAssignments->filter(fn ($category) => $category === null)->count(),
            'final_assigned_count' => $newAssignments->count(),
        ];
    }

    private function allocationImportSummary(
        array $import,
        array $allocations,
        array $categorySettings,
        ?string $reason = null,
    ): array {
        return [
            'source' => 'excel_import',
            'reason' => $reason,
            'summary' => $import['summary'] ?? [],
            'allocations' => $this->normalizedAllocationAuditValues($allocations, $categorySettings),
        ];
    }

    private function storeAssignmentImportToken(Request $request, ?Project $project): string
    {
        $tokens = collect($request->session()->get('project_assignment_import_tokens', []))
            ->filter(fn ($details) => (int) ($details['created_at'] ?? 0) >= now()->subHours(2)->timestamp)
            ->all();
        $token = Str::random(48);
        $tokens[$token] = [
            'user_id' => $request->user()->id,
            'project_id' => $project?->id,
            'created_at' => now()->timestamp,
        ];
        $request->session()->put('project_assignment_import_tokens', $tokens);

        return $token;
    }

    private function validateAssignmentImportToken(Request $request, ?Project $project, ?string $token): bool
    {
        if (blank($token)) {
            return false;
        }

        $details = $request->session()->get('project_assignment_import_tokens.'.$token);
        $valid = is_array($details)
            && (int) ($details['user_id'] ?? 0) === (int) $request->user()->id
            && ($details['project_id'] ?? null) === $project?->id
            && (int) ($details['created_at'] ?? 0) >= now()->subHours(2)->timestamp;

        if (! $valid) {
            throw ValidationException::withMessages([
                'assignment_import_token' => 'The Excel assignment preview expired. Upload the file again.',
            ]);
        }

        return true;
    }

    private function forgetAssignmentImportToken(Request $request, ?string $token): void
    {
        if (filled($token)) {
            $request->session()->forget('project_assignment_import_tokens.'.$token);
        }
    }

    private function storeAllocationImportToken(
        Request $request,
        ?Project $project,
        array $preview,
    ): string {
        $tokens = collect($request->session()->get('project_allocation_import_tokens', []))
            ->filter(fn ($details) => (int) ($details['created_at'] ?? 0) >= now()->subHours(2)->timestamp)
            ->all();
        $token = Str::random(48);
        $tokens[$token] = [
            'user_id' => $request->user()->id,
            'project_id' => $project?->id,
            'created_at' => now()->timestamp,
            'summary' => $preview['summary'] ?? [],
        ];
        $request->session()->put('project_allocation_import_tokens', $tokens);

        return $token;
    }

    private function validateAllocationImportToken(
        Request $request,
        ?Project $project,
        ?string $token,
    ): ?array {
        if (blank($token)) {
            return null;
        }

        $details = $request->session()->get('project_allocation_import_tokens.'.$token);
        $valid = is_array($details)
            && (int) ($details['user_id'] ?? 0) === (int) $request->user()->id
            && ($details['project_id'] ?? null) === $project?->id
            && (int) ($details['created_at'] ?? 0) >= now()->subHours(2)->timestamp;

        if (! $valid) {
            throw ValidationException::withMessages([
                'allocation_import_token' => 'The Excel allocation preview expired. Upload the file again.',
            ]);
        }

        return $details;
    }

    private function forgetAllocationImportToken(Request $request, ?string $token): void
    {
        if (filled($token)) {
            $request->session()->forget('project_allocation_import_tokens.'.$token);
        }
    }
}
