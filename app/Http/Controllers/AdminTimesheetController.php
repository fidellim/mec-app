<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GuardsExports;
use App\Models\Department;
use App\Models\Project;
use App\Models\Timesheet;
use App\Models\TimesheetPeriod;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\TimesheetExportService;
use App\Services\TimesheetEmailNotificationService;
use App\Services\TimesheetRecallService;
use App\Services\TimesheetStatusHistoryService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminTimesheetController extends Controller
{
    use GuardsExports;

    private const SUMMARY_PREVIEW_MAX_WEEKS = 6;

    public function index(TimesheetExportService $export)
    {
        $filters = $this->validatedFilters();
        $showingNotSubmitted = ($filters['status'] ?? null) === 'not_submitted';
        $summaryPreviewState = $this->summaryPreviewState($filters, $showingNotSubmitted);

        return view('admin.timesheets.index', [
            'timesheets' => $showingNotSubmitted
                ? $this->missingSubmissionRows($filters)
                : $this->filtered($filters)->with(['user', 'department', 'period'])->latest()->paginate(20)->withQueryString(),
            'departments' => Department::orderBy('name')->get(),
            'employees' => User::orderBy('name')->get(),
            'projects' => Project::orderBy('project_code')->orderBy('project_name')->get(),
            'selectedPeriodRange' => $this->selectedPeriodRange($filters),
            'roleLabels' => config('roles.labels'),
            'showingNotSubmitted' => $showingNotSubmitted,
            'summaryPreviewState' => $summaryPreviewState,
            'summaryPreview' => $summaryPreviewState['requested'] && $summaryPreviewState['can_preview']
                ? $export->summaryPreview($filters)
                : null,
        ]);
    }

    public function show(Timesheet $timesheet)
    {
        return view('admin.timesheets.show', ['timesheet' => $timesheet->load(['user', 'department', 'period', 'entries.project', 'approver', 'voider'])]);
    }

    public function history(Timesheet $timesheet)
    {
        return view('shared.timesheet_history_timeline', [
            'timesheet' => $timesheet->load('statusHistories.user'),
        ]);
    }

    public function export(TimesheetExportService $export)
    {
        return $this->guardedExport(fn () => $export->excel($this->validatedFilters()));
    }

    public function voidTimesheet(Request $request, Timesheet $timesheet, AuditLogService $audit, TimesheetStatusHistoryService $history)
    {
        abort_unless($request->user()?->role === 'super_admin', 403);

        if ((int) $timesheet->user_id === (int) $request->user()->id) {
            return back()->with('warning', 'You cannot void your own timesheet. Another Super Admin must complete this correction.');
        }

        abort_unless($timesheet->status === 'approved', 422, 'Only approved timesheets can be voided.');

        $validated = $request->validate([
            'void_reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $old = $timesheet->toArray();

        $timesheet->update([
            'status' => Timesheet::STATUS_VOIDED,
            'voided_at' => now(),
            'voided_by' => $request->user()->id,
            'void_reason' => $validated['void_reason'],
        ]);

        $new = $timesheet->fresh()->toArray();
        $audit->record('timesheet_voided', $timesheet, $old, $new);
        $history->record('timesheet_voided', $timesheet, $old, $new);

        return redirect()
            ->route('admin.timesheets.show', $timesheet)
            ->with('success', 'Timesheet voided. The employee can now create a corrected timesheet for this weekly period.');
    }

    public function recallApproved(Request $request, Timesheet $timesheet, AuditLogService $audit, TimesheetEmailNotificationService $emails, TimesheetRecallService $recalls, TimesheetStatusHistoryService $history)
    {
        if ((int) $timesheet->user_id === (int) $request->user()->id) {
            return back()->with('warning', 'You cannot recall your own approved timesheet. Another authorized reviewer must complete this correction.');
        }

        $timesheet->loadMissing('user');

        if ($request->user()->role === 'admin') {
            abort_unless(
                $timesheet->user?->role === 'hod',
                403,
                'Admin can only recall Head of Department timesheets.'
            );
        } else {
            abort_unless($request->user()->role === 'super_admin', 403);
        }

        abort_unless($timesheet->status === Timesheet::STATUS_APPROVED, 422, 'Only approved timesheets can be recalled.');

        $validated = $request->validate([
            'recall_reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $recalls->recallApproved($timesheet, $request->user(), $validated['recall_reason'], $audit, $emails, $history);

        return redirect()
            ->route('admin.timesheets.show', $timesheet)
            ->with('success', 'Approved timesheet recalled. The employee has been notified to correct and resubmit it.');
    }

    private function filtered(array $filters)
    {
        $weekFrom = $filters['week_from'] ?? $filters['week_number'] ?? null;
        $weekTo = $filters['week_to'] ?? $weekFrom;

        return Timesheet::query()
            ->when($weekFrom, fn ($q) => $q->whereHas('period', fn ($p) => $p->whereBetween('week_number', [(int) $weekFrom, (int) $weekTo])))
            ->when($filters['year'] ?? null, fn ($q, $v) => $q->whereHas('period', fn ($p) => $p->where('year', $v)))
            ->when($filters['department_id'] ?? null, fn ($q, $v) => $q->where('department_id', $v))
            ->when($filters['employee_id'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($filters['role'] ?? null, fn ($q, $v) => $q->whereHas('user', fn ($user) => $user->where('role', $v)))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['project_id'] ?? null, fn ($q, $v) => $q->whereHas('entries', fn ($entry) => $entry->where('project_id', $v)));
    }

    private function validatedFilters(): array
    {
        $filters = request()->validate([
            'week_number' => ['nullable', 'integer', 'between:1,53'],
            'week_from' => ['nullable', 'integer', 'between:1,53', 'required_with:week_to'],
            'week_to' => ['nullable', 'integer', 'between:1,53', 'gte:week_from'],
            'year' => ['nullable', 'integer', 'between:2000,2100', 'required_with:week_number,week_from,week_to'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'employee_id' => ['nullable', 'integer', 'exists:users,id'],
            'role' => ['nullable', Rule::in(array_keys(config('roles.labels')))],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'status' => ['nullable', 'in:draft,submitted,approved,rejected,withdrawn,recalled,voided,not_submitted'],
            'include_employee_sheets' => ['nullable', 'boolean'],
        ], [
            'week_from.required_with' => 'Enter From Week when using To Week.',
            'week_to.gte' => 'To Week must be greater than or equal to From Week.',
            'year.required_with' => 'Year is required when filtering by week.',
        ]);

        $this->validateNotSubmittedFilters($filters);
        $this->validateWeekPeriodExists($filters);

        return $filters;
    }

    private function validateNotSubmittedFilters(array $filters): void
    {
        if (($filters['status'] ?? null) !== 'not_submitted') {
            return;
        }

        if (! ($filters['year'] ?? null) || ! (($filters['week_from'] ?? null) || ($filters['week_number'] ?? null))) {
            throw ValidationException::withMessages([
                'week_from' => 'Select a week and year to view users who have not submitted.',
                'year' => 'Select a week and year to view users who have not submitted.',
            ]);
        }
    }

    private function validateWeekPeriodExists(array $filters): void
    {
        $weekFrom = $filters['week_from'] ?? $filters['week_number'] ?? null;

        if (! $weekFrom || ! ($filters['year'] ?? null)) {
            return;
        }

        $weekTo = $filters['week_to'] ?? $weekFrom;
        $exists = TimesheetPeriod::query()
            ->where('year', $filters['year'])
            ->whereBetween('week_number', [(int) $weekFrom, (int) $weekTo])
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'week_from' => $weekTo === $weekFrom
                    ? 'Selected week period does not exist.'
                    : 'Selected week range does not contain any existing periods.',
            ]);
        }
    }

    private function selectedPeriodRange(array $filters): ?array
    {
        if (! ($filters['year'] ?? null)) {
            return null;
        }

        $weekFrom = $filters['week_from'] ?? $filters['week_number'] ?? null;
        $weekTo = $filters['week_to'] ?? $weekFrom;

        $periods = TimesheetPeriod::query()
            ->where('year', $filters['year'])
            ->when($weekFrom, fn ($q) => $q->whereBetween('week_number', [(int) $weekFrom, (int) $weekTo]))
            ->orderBy('week_number')
            ->get(['week_number', 'year', 'start_date', 'end_date']);

        if ($periods->isEmpty()) {
            return null;
        }

        return [
            'start_date' => $periods->first()->start_date,
            'end_date' => $periods->last()->end_date,
            'first_week' => $periods->first()->week_number,
            'last_week' => $periods->last()->week_number,
            'requested_week_from' => $weekFrom ? (int) $weekFrom : null,
            'requested_week_to' => $weekTo ? (int) $weekTo : null,
            'has_missing_weeks' => $weekFrom && $periods->count() < (((int) $weekTo - (int) $weekFrom) + 1),
        ];
    }

    private function summaryPreviewState(array $filters, bool $showingNotSubmitted): array
    {
        $requested = request('preview') === 'summary';
        $weekFrom = $filters['week_from'] ?? $filters['week_number'] ?? null;
        $weekTo = $filters['week_to'] ?? $weekFrom;
        $weekCount = $weekFrom && $weekTo ? ((int) $weekTo - (int) $weekFrom) + 1 : null;

        $state = [
            'requested' => $requested,
            'can_preview' => false,
            'week_count' => $weekCount,
            'message' => null,
        ];

        if ($showingNotSubmitted) {
            $state['message'] = 'Summary Report Preview is not available for Not Submitted status. Use submitted or approved records for report summaries.';

            return $state;
        }

        if (! ($filters['year'] ?? null) || ! $weekFrom) {
            $state['message'] = 'Select a From Week and Year to enable Summary Report Preview.';

            return $state;
        }

        if ($weekCount > self::SUMMARY_PREVIEW_MAX_WEEKS) {
            $state['message'] = 'Summary Report Preview is available for up to 6 weekly periods. Please narrow the week range, or use Export Excel for larger reports.';

            return $state;
        }

        $state['can_preview'] = true;

        return $state;
    }

    private function selectedPeriods(array $filters)
    {
        if (! ($filters['year'] ?? null)) {
            return collect();
        }

        $weekFrom = $filters['week_from'] ?? $filters['week_number'] ?? null;
        $weekTo = $filters['week_to'] ?? $weekFrom;

        return TimesheetPeriod::query()
            ->where('year', $filters['year'])
            ->when($weekFrom, fn ($q) => $q->whereBetween('week_number', [(int) $weekFrom, (int) $weekTo]))
            ->orderBy('week_number')
            ->get();
    }

    private function missingSubmissionRows(array $filters): LengthAwarePaginator
    {
        $periods = $this->selectedPeriods($filters);
        $users = User::with('department')
            ->whereIn('role', config('roles.timesheet_submitters'))
            ->where('is_active', true)
            ->whereNotNull('department_id')
            ->when($filters['department_id'] ?? null, fn ($query, $departmentId) => $query->where('department_id', $departmentId))
            ->when($filters['employee_id'] ?? null, fn ($query, $employeeId) => $query->where('id', $employeeId))
            ->when($filters['role'] ?? null, fn ($query, $role) => $query->where('role', $role))
            ->orderBy('name')
            ->get();

        $completedUserIdsByPeriod = Timesheet::query()
            ->whereIn('timesheet_period_id', $periods->pluck('id'))
            ->whereIn('status', ['submitted', 'approved'])
            ->get(['user_id', 'timesheet_period_id'])
            ->groupBy('timesheet_period_id')
            ->map(fn ($timesheets) => $timesheets->pluck('user_id')->map(fn ($id) => (int) $id)->all());

        $rows = $periods
            ->flatMap(fn (TimesheetPeriod $period) => $users
                ->reject(fn (User $user) => in_array((int) $user->id, $completedUserIdsByPeriod->get($period->id, []), true))
                ->map(fn (User $user) => (object) [
                    'user' => $user,
                    'department' => $user->department,
                    'period' => $period,
                ]))
            ->sortBy([
                fn ($row) => $row->period->year,
                fn ($row) => $row->period->week_number,
                fn ($row) => $row->user->name,
            ])
            ->values();

        $perPage = 20;
        $page = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );
    }
}
