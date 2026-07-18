<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Department;
use App\Models\ProjectDepartmentAllocation;
use App\Models\Timesheet;
use Illuminate\Support\Facades\DB;

class ProjectUtilizationController extends Controller
{
    public function index()
    {
        abort_unless(
            Project::query()->where('project_manager_id', auth()->id())->exists(),
            403
        );

        $projects = Project::query()
            ->where('project_manager_id', auth()->id())
            ->select(['id', 'project_code', 'project_name', 'client_name', 'start_date', 'is_active'])
            ->withCount('departmentAllocations')
            ->orderByDesc('is_active')
            ->orderBy('project_code')
            ->paginate(20);

        return view('projects.managed', compact('projects'));
    }

    public function show(Project $project)
    {
        $user = auth()->user();
        abort_unless($user->isAdminLike() || (int) $project->project_manager_id === (int) $user->id, 403);

        $filters = request()->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ], [
            'date_to.after_or_equal' => 'The to date must be on or after the from date.',
        ]);

        // Aggregate before loading results so the response size grows with people,
        // not with the number of individual timesheet entries.
        $peopleUsage = DB::table('timesheet_entries as entries')
            ->join('timesheets', 'timesheets.id', '=', 'entries.timesheet_id')
            ->join('users', 'users.id', '=', 'timesheets.user_id')
            ->where('entries.project_id', $project->id)
            ->whereNotNull('entries.department_id')
            ->whereIn('timesheets.status', [Timesheet::STATUS_SUBMITTED, Timesheet::STATUS_APPROVED])
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->where('entries.work_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->where('entries.work_date', '<=', $date))
            ->groupBy('entries.department_id', 'timesheets.user_id', 'users.name')
            ->selectRaw('entries.department_id, timesheets.user_id, users.name,
                SUM(CASE WHEN timesheets.status = ? THEN entries.regular_hours + entries.overtime_hours ELSE 0 END) as approved_hours,
                SUM(CASE WHEN timesheets.status = ? THEN entries.regular_hours + entries.overtime_hours ELSE 0 END) as pending_hours',
                [Timesheet::STATUS_APPROVED, Timesheet::STATUS_SUBMITTED])
            ->orderBy('users.name')
            ->get()
            ->groupBy('department_id');

        $usage = $peopleUsage->map(fn ($people) => (object) [
            'approved_hours' => $people->sum('approved_hours'),
            'pending_hours' => $people->sum('pending_hours'),
        ]);

        $allocations = $project->departmentAllocations()->with('department:id,name,code,is_active')
            ->orderBy('department_id')->get()->map(function ($allocation) use ($usage, $peopleUsage) {
                $row = $usage->get($allocation->department_id);
                $allocation->approved_hours = (float) ($row->approved_hours ?? 0);
                $allocation->pending_hours = (float) ($row->pending_hours ?? 0);
                $allocation->charging_people = $peopleUsage->get($allocation->department_id, collect());
                return $allocation;
            });

        $allocatedDepartmentIds = $allocations->pluck('department_id')->map(fn ($id) => (int) $id);
        $unallocatedDepartments = Department::query()
            ->whereIn('id', $usage->keys()->diff($allocatedDepartmentIds))
            ->get(['id', 'name', 'code', 'is_active'])->keyBy('id');
        foreach ($usage as $departmentId => $row) {
            if ($allocatedDepartmentIds->contains((int) $departmentId) || ! $unallocatedDepartments->has($departmentId)) continue;
            $allocation = new ProjectDepartmentAllocation(['department_id' => $departmentId, 'allocated_hours' => 0]);
            $allocation->setRelation('department', $unallocatedDepartments->get($departmentId));
            $allocation->approved_hours = (float) $row->approved_hours;
            $allocation->pending_hours = (float) $row->pending_hours;
            $allocation->charging_people = $peopleUsage->get($departmentId, collect());
            $allocations->push($allocation);
        }

        return view('projects.utilization', [
            'project' => $project->load('projectManager:id,name,email'),
            'allocations' => $allocations,
            'filters' => $filters,
        ]);
    }
}
