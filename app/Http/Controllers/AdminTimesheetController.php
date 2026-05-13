<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Timesheet;
use App\Models\User;
use App\Services\TimesheetExportService;

class AdminTimesheetController extends Controller
{
    public function index()
    {
        return view('admin.timesheets.index', [
            'timesheets' => $this->filtered()->with(['user', 'department', 'period'])->latest()->paginate(20)->withQueryString(),
            'departments' => Department::orderBy('name')->get(),
            'employees' => User::orderBy('name')->get(),
        ]);
    }

    public function show(Timesheet $timesheet)
    {
        return view('admin.timesheets.show', ['timesheet' => $timesheet->load(['user', 'department', 'period', 'entries.project', 'approver'])]);
    }

    public function export(TimesheetExportService $export)
    {
        return $export->csv(request()->only(['week_number', 'year', 'department_id', 'employee_id', 'status']));
    }

    private function filtered()
    {
        return Timesheet::query()
            ->when(request('week_number'), fn ($q, $v) => $q->whereHas('period', fn ($p) => $p->where('week_number', $v)))
            ->when(request('year'), fn ($q, $v) => $q->whereHas('period', fn ($p) => $p->where('year', $v)))
            ->when(request('department_id'), fn ($q, $v) => $q->where('department_id', $v))
            ->when(request('employee_id'), fn ($q, $v) => $q->where('user_id', $v))
            ->when(request('status'), fn ($q, $v) => $q->where('status', $v));
    }
}
