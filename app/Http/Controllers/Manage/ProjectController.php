<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Department;
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
        ]);
    }

    public function store(Request $request, AuditLogService $audit)
    {
        $validated = $this->validated($request);
        $assignedUserIds = $validated['assigned_user_ids'] ?? [];
        $allocations = $validated['department_allocations'] ?? [];
        if (collect($allocations)->filter(fn ($hours) => filled($hours) && (float) $hours > 0)->isEmpty()) {
            throw ValidationException::withMessages(['department_allocations' => 'Allocate manhours to at least one department.']);
        }
        unset($validated['assigned_user_ids'], $validated['department_allocations']);

        $project = DB::transaction(function () use ($validated, $assignedUserIds, $allocations, $audit) {
            $project = Project::create($validated);
            $project->assignedUsers()->sync($assignedUserIds);
            $this->syncAllocations($project, $allocations);
            $audit->record('project_created', $project, null, $this->auditValues($project));

            return $project;
        });

        return redirect()->route('manage.projects.index')->with('success', 'Project created.');
    }

    public function edit(Project $project)
    {
        return view('manage.projects.form', [
            'project' => $project,
            'timesheetUsers' => $this->timesheetUsers(),
            'assignedUserIds' => $project->assignedUsers()->pluck('users.id'),
            'projectManagers' => $this->projectManagers($project),
            'departments' => Department::query()->where('is_active', true)
                ->orWhereIn('id', $project->departmentAllocations()->pluck('department_id'))->orderBy('name')->get(['id', 'name', 'code', 'is_active']),
            'allocationHours' => $project->departmentAllocations()->pluck('allocated_hours', 'department_id'),
        ]);
    }

    public function update(Request $request, Project $project, AuditLogService $audit)
    {
        $validated = $this->validated($request, $project);
        $assignedUserIds = $validated['assigned_user_ids'] ?? [];
        $allocations = $validated['department_allocations'] ?? [];
        if (collect($allocations)->filter(fn ($hours) => filled($hours) && (float) $hours > 0)->isEmpty()) {
            throw ValidationException::withMessages(['department_allocations' => 'Allocate manhours to at least one department.']);
        }
        unset($validated['assigned_user_ids'], $validated['department_allocations']);
        $allocationWarnings = $this->validateAllocationChanges($project, $allocations);
        $old = $this->auditValues($project);

        DB::transaction(function () use ($project, $validated, $assignedUserIds, $allocations, $audit, $old) {
            $project->update($validated);
            $project->assignedUsers()->sync($assignedUserIds);
            $this->syncAllocations($project, $allocations);
            $audit->record('project_updated', $project, $old, $this->auditValues($project));
        });

        $redirect = redirect()->route('manage.projects.index')->with('success', 'Project updated.');

        if ($allocationWarnings->isNotEmpty()) {
            $redirect->with('warning', 'Allocation reduced below approved plus pending hours for: '.$allocationWarnings->join(', ').'. Pending approvals may create an overrun.');
        }

        return $redirect;
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

    private function syncAllocations(Project $project, array $allocations): void
    {
        $rows = collect($allocations)->filter(fn ($hours) => filled($hours) && (float) $hours > 0);
        $project->departmentAllocations()->whereNotIn('department_id', $rows->keys())->delete();
        foreach ($rows as $departmentId => $hours) {
            $project->departmentAllocations()->updateOrCreate(
                ['department_id' => (int) $departmentId],
                ['allocated_hours' => $hours],
            );
        }
    }

    private function validateAllocationChanges(Project $project, array $allocations)
    {
        $existing = $project->departmentAllocations()->pluck('allocated_hours', 'department_id');
        $usage = DB::table('timesheet_entries as entries')
            ->join('timesheets', 'timesheets.id', '=', 'entries.timesheet_id')
            ->where('entries.project_id', $project->id)
            ->whereIn('timesheets.status', [Timesheet::STATUS_APPROVED, Timesheet::STATUS_SUBMITTED])
            ->whereNotNull('entries.department_id')
            ->groupBy('entries.department_id')
            ->selectRaw('entries.department_id,
                SUM(CASE WHEN timesheets.status = ? THEN entries.regular_hours + entries.overtime_hours ELSE 0 END) as approved_hours,
                SUM(CASE WHEN timesheets.status = ? THEN entries.regular_hours + entries.overtime_hours ELSE 0 END) as pending_hours',
                [Timesheet::STATUS_APPROVED, Timesheet::STATUS_SUBMITTED])
            ->get()
            ->keyBy('department_id');
        $warningDepartmentIds = collect();

        foreach ($existing as $departmentId => $hours) {
            $newHours = $allocations[$departmentId] ?? null;
            $approvedHours = (float) ($usage->get($departmentId)->approved_hours ?? 0);
            $pendingHours = (float) ($usage->get($departmentId)->pending_hours ?? 0);

            if (filled($newHours) && (float) $newHours + 0.0001 < $approvedHours) {
                throw ValidationException::withMessages(["department_allocations.$departmentId" => 'Allocation cannot be lower than '.number_format($approvedHours, 2).' approved hours already used.']);
            }
            if ((! filled($newHours) || (float) $newHours <= 0) && ($approvedHours > 0 || $pendingHours > 0)) {
                throw ValidationException::withMessages(["department_allocations.$departmentId" => 'This department has approved or pending project hours and cannot be removed.']);
            }
            if (filled($newHours) && (float) $newHours + 0.0001 < $approvedHours + $pendingHours) {
                $warningDepartmentIds->push((int) $departmentId);
            }
        }

        return Department::whereIn('id', $warningDepartmentIds)->orderBy('name')->pluck('name');
    }
}
