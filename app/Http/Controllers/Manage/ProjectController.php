<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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
        ]);
    }

    public function store(Request $request, AuditLogService $audit)
    {
        $validated = $this->validated($request);
        $assignedUserIds = $validated['assigned_user_ids'] ?? [];
        unset($validated['assigned_user_ids']);

        $project = DB::transaction(function () use ($validated, $assignedUserIds, $audit) {
            $project = Project::create($validated);
            $project->assignedUsers()->sync($assignedUserIds);
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
        ]);
    }

    public function update(Request $request, Project $project, AuditLogService $audit)
    {
        $validated = $this->validated($request, $project);
        $assignedUserIds = $validated['assigned_user_ids'] ?? [];
        unset($validated['assigned_user_ids']);
        $old = $this->auditValues($project);

        DB::transaction(function () use ($project, $validated, $assignedUserIds, $audit, $old) {
            $project->update($validated);
            $project->assignedUsers()->sync($assignedUserIds);
            $audit->record('project_updated', $project, $old, $this->auditValues($project));
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
        ];
    }
}
