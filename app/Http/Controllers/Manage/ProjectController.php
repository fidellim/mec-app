<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    public function index()
    {
        return view('manage.projects.index', ['projects' => Project::withCount('entries')->orderBy('project_code')->paginate(20)]);
    }

    public function create()
    {
        return view('manage.projects.form', ['project' => new Project()]);
    }

    public function store(Request $request, AuditLogService $audit)
    {
        $project = Project::create($this->validated($request));
        $audit->record('project_created', $project, null, $project->toArray());
        return redirect()->route('manage.projects.index')->with('success', 'Project created.');
    }

    public function edit(Project $project)
    {
        return view('manage.projects.form', compact('project'));
    }

    public function update(Request $request, Project $project, AuditLogService $audit)
    {
        $old = $project->toArray();
        $project->update($this->validated($request, $project));
        $audit->record('project_updated', $project, $old, $project->fresh()->toArray());
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
        ]) + ['is_active' => false];
    }
}
