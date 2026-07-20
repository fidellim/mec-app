<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Department;
use App\Models\ProjectDepartmentAllocation;
use App\Models\Timesheet;
use App\Models\TimesheetEntry;
use App\Models\TimesheetCorrectionRequest;
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
            'review_employee_id' => ['nullable', 'integer', 'exists:users,id'],
            'review_status' => ['nullable', 'in:submitted,approved'],
            'review_week' => ['nullable', 'integer', 'between:1,53'],
            'review_year' => ['nullable', 'integer', 'between:2000,2100'],
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

        $entryScope = fn ($query) => $query->where('project_id', $project->id)
            ->when($filters['date_from'] ?? null, fn ($q, $date) => $q->whereDate('work_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($q, $date) => $q->whereDate('work_date', '<=', $date));
        $reviewTimesheets = Timesheet::query()
            ->select(['id', 'user_id', 'department_id', 'timesheet_period_id', 'status'])
            ->with(['user:id,name', 'period:id,week_number,year,start_date,end_date', 'entries' => fn ($query) => $entryScope($query)
                ->select(['id', 'timesheet_id', 'work_date', 'department_id', 'regular_hours', 'overtime_hours', 'description'])
                ->with('department:id,name,code')->orderBy('work_date')->orderBy('id')])
            ->withCount(['entries as project_entries_count' => $entryScope])
            ->withSum(['entries as project_regular_hours' => $entryScope], 'regular_hours')
            ->withSum(['entries as project_overtime_hours' => $entryScope], 'overtime_hours')
            ->whereIn('status', [Timesheet::STATUS_SUBMITTED, Timesheet::STATUS_APPROVED])
            ->whereHas('entries', $entryScope)
            ->when($filters['review_employee_id'] ?? null, fn ($query, $employeeId) => $query->where('user_id', $employeeId))
            ->when($filters['review_status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['review_week'] ?? null, fn ($query, $week) => $query->whereHas('period', fn ($period) => $period->where('week_number', $week)))
            ->when($filters['review_year'] ?? null, fn ($query, $year) => $query->whereHas('period', fn ($period) => $period->where('year', $year)))
            ->latest('submitted_at')->latest('id')->paginate(10, ['*'], 'review_page')->withQueryString();
        $visibleEntryIds = $reviewTimesheets->getCollection()->flatMap->entries->pluck('id');
        $openEntryRequestIds = DB::table('timesheet_correction_request_entries as items')
            ->join('timesheet_correction_requests as requests', 'requests.id', '=', 'items.timesheet_correction_request_id')
            ->whereIn('items.timesheet_entry_id', $visibleEntryIds)
            ->where('requests.status', TimesheetCorrectionRequest::STATUS_OPEN)
            ->pluck('requests.id', 'items.timesheet_entry_id');
        $reviewPeriods = DB::table('timesheet_entries as entries')->join('timesheets', 'timesheets.id', '=', 'entries.timesheet_id')
            ->join('timesheet_periods as periods', 'periods.id', '=', 'timesheets.timesheet_period_id')
            ->where('entries.project_id', $project->id)->whereIn('timesheets.status', [Timesheet::STATUS_SUBMITTED, Timesheet::STATUS_APPROVED])
            ->distinct()->orderByDesc('periods.year')->orderByDesc('periods.week_number')->get(['periods.week_number', 'periods.year']);
        $reviewEmployees = DB::table('timesheet_entries as entries')->join('timesheets', 'timesheets.id', '=', 'entries.timesheet_id')
            ->join('users', 'users.id', '=', 'timesheets.user_id')
            ->where('entries.project_id', $project->id)->whereIn('timesheets.status', [Timesheet::STATUS_SUBMITTED, Timesheet::STATUS_APPROVED])
            ->distinct()->orderBy('users.name')->get(['users.id', 'users.name']);
        $myRequests = TimesheetCorrectionRequest::query()->withCount('entries')->with('timesheet.user:id,name')
            ->where('requested_by', $user->id)->whereHas('entries', fn ($q) => $q->where('project_id', $project->id))
            ->latest()->limit(20)->get();

        return view('projects.utilization', [
            'project' => $project->load('projectManager:id,name,email'),
            'allocations' => $allocations,
            'filters' => $filters,
            'reviewTimesheets' => $reviewTimesheets,
            'openEntryRequestIds' => $openEntryRequestIds,
            'reviewPeriods' => $reviewPeriods,
            'reviewEmployees' => $reviewEmployees,
            'myRequests' => $myRequests,
        ]);
    }
}
