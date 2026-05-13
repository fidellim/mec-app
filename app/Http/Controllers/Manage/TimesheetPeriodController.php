<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\TimesheetPeriod;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TimesheetPeriodController extends Controller
{
    public function index()
    {
        return view('manage.periods.index', ['periods' => TimesheetPeriod::latest('start_date')->paginate(20)]);
    }

    public function create()
    {
        return view('manage.periods.form', ['period' => new TimesheetPeriod()]);
    }

    public function store(Request $request, AuditLogService $audit)
    {
        $period = TimesheetPeriod::create($this->validated($request));
        $audit->record('timesheet_period_created', $period, null, $period->toArray());
        return redirect()->route('manage.periods.index')->with('success', 'Period created.');
    }

    public function edit(TimesheetPeriod $period)
    {
        return view('manage.periods.form', compact('period'));
    }

    public function update(Request $request, TimesheetPeriod $period, AuditLogService $audit)
    {
        $old = $period->toArray();
        $period->update($this->validated($request, $period));
        $audit->record('timesheet_period_updated', $period, $old, $period->fresh()->toArray());
        return redirect()->route('manage.periods.index')->with('success', 'Period updated.');
    }

    private function validated(Request $request, ?TimesheetPeriod $period = null): array
    {
        return $request->validate([
            'week_number' => ['required', 'integer', 'between:1,53', Rule::unique('timesheet_periods')->where('year', $request->year)->ignore($period)],
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'status' => ['required', Rule::in(['open', 'closed'])],
        ]);
    }
}
