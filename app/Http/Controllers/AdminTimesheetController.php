<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GuardsExports;
use App\Models\Department;
use App\Models\Project;
use App\Models\Timesheet;
use App\Models\TimesheetPeriod;
use App\Models\User;
use App\Services\TimesheetExportService;
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
        ]);
    }

    public function show(Timesheet $timesheet)
    {
        return view('admin.timesheets.show', ['timesheet' => $timesheet->load(['user', 'department', 'period', 'entries.project', 'approver'])]);
    }

    public function export(TimesheetExportService $export)
    {
        return $this->guardedExport(fn () => $export->excel($this->validatedFilters()));
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
            'status' => ['nullable', 'in:draft,submitted,approved,rejected'],
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
}
