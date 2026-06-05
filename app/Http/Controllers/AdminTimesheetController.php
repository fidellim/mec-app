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
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminTimesheetController extends Controller
{
    use GuardsExports;

    public function index()
    {
        $filters = $this->validatedFilters();

        return view('admin.timesheets.index', [
            'timesheets' => $this->filtered($filters)->with(['user', 'department', 'period'])->latest()->paginate(20)->withQueryString(),
            'departments' => Department::orderBy('name')->get(),
            'employees' => User::orderBy('name')->get(),
            'projects' => Project::orderBy('project_code')->orderBy('project_name')->get(),
            'selectedPeriodRange' => $this->selectedPeriodRange($filters),
        ]);
    }

    public function show(Timesheet $timesheet)
    {
        return view('admin.timesheets.show', ['timesheet' => $timesheet->load(['user', 'department', 'period', 'entries.project', 'approver', 'voider'])]);
    }

    public function export(TimesheetExportService $export)
    {
        return $this->guardedExport(fn () => $export->excel($this->validatedFilters()));
    }

    public function voidTimesheet(Request $request, Timesheet $timesheet, AuditLogService $audit)
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

        $audit->record('timesheet_voided', $timesheet, $old, $timesheet->fresh()->toArray());

        return redirect()
            ->route('admin.timesheets.show', $timesheet)
            ->with('success', 'Timesheet voided. The employee can now create a corrected timesheet for this weekly period.');
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
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'status' => ['nullable', 'in:draft,submitted,approved,rejected,voided'],
            'include_employee_sheets' => ['nullable', 'boolean'],
        ], [
            'week_from.required_with' => 'Enter From Week when using To Week.',
            'week_to.gte' => 'To Week must be greater than or equal to From Week.',
            'year.required_with' => 'Year is required when filtering by week.',
        ]);

        $this->validateWeekPeriodExists($filters);

        return $filters;
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
}
