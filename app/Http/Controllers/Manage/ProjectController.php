<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Project;
use App\Models\Timesheet;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
            'projectManagers' => $this->projectManagers(),
            'departments' => Department::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'allocationHours' => collect(),
            'jobLevelSettings' => collect(),
            'controlledDepartmentIds' => collect(),
        ]);
    }

    public function store(Request $request, AuditLogService $audit)
    {
        $validated = $this->validated($request);
        $assignedUserIds = $validated['assigned_user_ids'] ?? [];
        $allocations = $validated['department_allocations'] ?? [];
        $levelSettings = $this->normalizeJobLevelSettings($validated, $allocations);
        if (collect($allocations)->filter(fn ($hours) => filled($hours) && (float) $hours > 0)->isEmpty()) {
            throw ValidationException::withMessages(['department_allocations' => 'Allocate manhours to at least one department.']);
        }
        unset($validated['assigned_user_ids'], $validated['department_allocations'], $validated['job_level_controls'], $validated['job_level_allocations'], $validated['allocation_change_reason']);

        $project = DB::transaction(function () use ($validated, $assignedUserIds, $allocations, $levelSettings, $audit) {
            $project = Project::create($validated);
            $project->assignedUsers()->sync($assignedUserIds);
            $this->syncAllocations($project, $allocations, $levelSettings);
            $audit->record('project_created', $project, null, $this->auditValues($project));

            return $project;
        });

        return redirect()->route('manage.projects.index')->with('success', 'Project created.');
    }

    public function edit(Project $project)
    {
        $project->load('departmentAllocations.jobLevelAllocations');

        return view('manage.projects.form', [
            'project' => $project,
            'timesheetUsers' => $this->timesheetUsers(),
            'assignedUserIds' => $project->assignedUsers()->pluck('users.id'),
            'projectManagers' => $this->projectManagers($project),
            'departments' => Department::query()->where('is_active', true)
                ->orWhereIn('id', $project->departmentAllocations()->pluck('department_id'))->orderBy('name')->get(['id', 'name', 'code', 'is_active']),
            'allocationHours' => $project->departmentAllocations()->pluck('allocated_hours', 'department_id'),
            'jobLevelSettings' => $project->departmentAllocations->mapWithKeys(fn ($allocation) => [
                $allocation->department_id => $allocation->jobLevelAllocations->pluck('allocated_hours', 'job_level'),
            ]),
            'controlledDepartmentIds' => $project->departmentAllocations->filter(fn ($allocation) => $allocation->jobLevelAllocations->isNotEmpty())->pluck('department_id'),
        ]);
    }

    public function update(Request $request, Project $project, AuditLogService $audit)
    {
        $validated = $this->validated($request, $project);
        $assignedUserIds = $validated['assigned_user_ids'] ?? [];
        $allocations = $validated['department_allocations'] ?? [];
        $levelSettings = $this->normalizeJobLevelSettings($validated, $allocations);
        if (collect($allocations)->filter(fn ($hours) => filled($hours) && (float) $hours > 0)->isEmpty()) {
            throw ValidationException::withMessages(['department_allocations' => 'Allocate manhours to at least one department.']);
        }
        $reason = $validated['allocation_change_reason'] ?? null;
        unset($validated['assigned_user_ids'], $validated['department_allocations'], $validated['job_level_controls'], $validated['job_level_allocations'], $validated['allocation_change_reason']);
        $oldAllocations = $this->allocationAuditValues($project);
        $newAllocations = $this->normalizedAllocationAuditValues($allocations, $levelSettings);
        $allocationsChanged = $oldAllocations !== $newAllocations;
        if ($allocationsChanged && blank($reason)) {
            throw ValidationException::withMessages(['allocation_change_reason' => 'Explain why the manhour allocation is changing.']);
        }
        $old = $this->auditValues($project);

        DB::transaction(function () use ($project, $validated, $assignedUserIds, $allocations, $levelSettings, $audit, $old, $oldAllocations, $newAllocations, $allocationsChanged, $reason) {
            $this->validateAllocationChanges($project, $allocations, $levelSettings);
            $project->update($validated);
            $project->assignedUsers()->sync($assignedUserIds);
            $this->syncAllocations($project, $allocations, $levelSettings);
            $audit->record('project_updated', $project, $old, $this->auditValues($project));
            if ($allocationsChanged) {
                $audit->record('project_allocations_updated', $project, $oldAllocations, $newAllocations + ['reason' => $reason]);
            }
        });

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
            ->get(['id', 'name', 'email', 'role', 'department_id'])
            ->sortBy(fn (User $user) => strtolower(($user->department?->name ?? 'ZZZZ No department').'|'.$user->name))
            ->groupBy(fn (User $user) => (string) ($user->department_id ?? 'unassigned'));
    }

    private function auditValues(Project $project): array
    {
        return $project->fresh()->toArray() + [
            'assigned_user_ids' => $project->assignedUsers()->orderBy('users.id')->pluck('users.id')->all(),
            'department_allocations' => $project->departmentAllocations()->orderBy('department_id')->pluck('allocated_hours', 'department_id')->all(),
            'job_level_allocations' => $this->allocationAuditValues($project),
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

    private function syncAllocations(Project $project, array $allocations, array $levelSettings): void
    {
        $rows = collect($allocations)->filter(fn ($hours) => filled($hours) && (float) $hours > 0);
        $project->departmentAllocations()->whereNotIn('department_id', $rows->keys())->delete();
        foreach ($rows as $departmentId => $hours) {
            $allocation = $project->departmentAllocations()->updateOrCreate(
                ['department_id' => (int) $departmentId],
                ['allocated_hours' => $hours],
            );
            $settings = $levelSettings[(int) $departmentId] ?? null;
            if ($settings === null) {
                $allocation->jobLevelAllocations()->delete();

                continue;
            }
            foreach ($settings as $jobLevel => $allocatedHours) {
                $allocation->jobLevelAllocations()->updateOrCreate(
                    ['job_level' => $jobLevel],
                    ['allocated_hours' => $allocatedHours],
                );
            }
            $allocation->jobLevelAllocations()->whereNotIn('job_level', array_keys($settings))->delete();
        }
    }

    private function validateAllocationChanges(Project $project, array $allocations, array $levelSettings): void
    {
        $existing = $project->departmentAllocations()->with('jobLevelAllocations')->lockForUpdate()->get()->keyBy('department_id');
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

            if (! filled($newHours) || ! isset($levelSettings[(int) $departmentId])) {
                if ($existingAllocation->jobLevelAllocations->isNotEmpty()) {
                    $reservedConsumed = $this->reservedUsage($project, (int) $departmentId);
                    if ($reservedConsumed->sum() > 0) {
                        throw ValidationException::withMessages(["job_level_allocations.$departmentId" => 'Job Level controls cannot be removed while reserved hours are consumed.']);
                    }
                }

                continue;
            }

            $settings = $levelSettings[(int) $departmentId];
            $reservedConsumed = $this->reservedUsage($project, (int) $departmentId);
            foreach ($reservedConsumed as $jobLevel => $hours) {
                $newReserved = $settings[$jobLevel] ?? null;
                if ($hours > 0 && ($newReserved === null || (float) $newReserved + 0.0001 < $hours)) {
                    throw ValidationException::withMessages(["job_level_allocations.$departmentId.$jobLevel.hours" => 'Reservation cannot be lower than '.number_format($hours, 2).' submitted and approved hours already used.']);
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

    private function normalizeJobLevelSettings(array $validated, array $departmentAllocations): array
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

            foreach (array_keys(config('job_levels.labels')) as $jobLevel) {
                $mode = $input[$departmentId][$jobLevel]['mode'] ?? 'shared';
                $hours = $input[$departmentId][$jobLevel]['hours'] ?? null;

                if ($mode === 'shared') {
                    $settings[$departmentId][$jobLevel] = null;
                    $hasShared = true;

                    continue;
                }

                if ($mode === 'not_allowed') {
                    $settings[$departmentId][$jobLevel] = 0.0;

                    continue;
                }

                if (! filled($hours) || (float) $hours <= 0) {
                    throw ValidationException::withMessages([
                        "job_level_allocations.$departmentId.$jobLevel.hours" => 'Enter reserved hours greater than zero.',
                    ]);
                }

                $settings[$departmentId][$jobLevel] = round((float) $hours, 2);
                $reservedTotal += (float) $hours;
            }

            if ($reservedTotal > $total + 0.0001) {
                throw ValidationException::withMessages([
                    "job_level_allocations.$departmentId" => 'Reserved Job Level hours cannot exceed the department allocation.',
                ]);
            }

            if (! $hasShared && abs($reservedTotal - $total) > 0.0001) {
                throw ValidationException::withMessages([
                    "job_level_allocations.$departmentId" => 'When no Job Level uses the shared remainder, reserved hours must equal the department allocation.',
                ]);
            }
        }

        return $settings;
    }

    private function reservedUsage(Project $project, int $departmentId)
    {
        return DB::table('timesheet_entries as entries')
            ->join('timesheets', 'timesheets.id', '=', 'entries.timesheet_id')
            ->where('entries.project_id', $project->id)
            ->where('entries.department_id', $departmentId)
            ->where('entries.allocation_bucket_snapshot', 'reserved')
            ->whereIn('timesheets.status', [Timesheet::STATUS_APPROVED, Timesheet::STATUS_SUBMITTED])
            ->groupBy('entries.job_level_snapshot')
            ->selectRaw('entries.job_level_snapshot, SUM(entries.regular_hours + entries.overtime_hours) as consumed_hours')
            ->get()
            ->pluck('consumed_hours', 'job_level_snapshot');
    }

    private function sharedUsage(Project $project, int $departmentId): float
    {
        return (float) DB::table('timesheet_entries as entries')
            ->join('timesheets', 'timesheets.id', '=', 'entries.timesheet_id')
            ->where('entries.project_id', $project->id)
            ->where('entries.department_id', $departmentId)
            ->whereIn('timesheets.status', [Timesheet::STATUS_APPROVED, Timesheet::STATUS_SUBMITTED])
            ->where(fn ($query) => $query->where('entries.allocation_bucket_snapshot', 'shared')->orWhereNull('entries.allocation_bucket_snapshot'))
            ->sum(DB::raw('entries.regular_hours + entries.overtime_hours'));
    }

    private function allocationAuditValues(Project $project): array
    {
        return $project->departmentAllocations()->with('jobLevelAllocations')->orderBy('department_id')->get()
            ->mapWithKeys(fn ($allocation) => [(string) $allocation->department_id => [
                'allocated_hours' => number_format((float) $allocation->allocated_hours, 2, '.', ''),
                'job_levels' => $allocation->jobLevelAllocations->sortBy('job_level')->mapWithKeys(fn ($row) => [
                    $row->job_level => $row->allocated_hours === null ? null : number_format((float) $row->allocated_hours, 2, '.', ''),
                ])->all(),
            ]])->all();
    }

    private function normalizedAllocationAuditValues(array $allocations, array $levelSettings): array
    {
        return collect($allocations)->filter(fn ($hours) => filled($hours) && (float) $hours > 0)
            ->sortKeys()->mapWithKeys(function ($hours, $departmentId) use ($levelSettings) {
                $levels = collect($levelSettings[(int) $departmentId] ?? [])->sortKeys()->map(fn ($value) => $value === null ? null : number_format((float) $value, 2, '.', ''))->all();

                return [(string) $departmentId => [
                    'allocated_hours' => number_format((float) $hours, 2, '.', ''),
                    'job_levels' => $levels,
                ]];
            })->all();
    }
}
