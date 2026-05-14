<div class="content-card mb-3">
    <div class="content-card-header">
        <h2 class="h5 mb-1">Timesheet summary</h2>
        <div class="small text-muted">Employee details and weekly hour totals.</div>
    </div>
    <div class="content-card-body">
    <div class="row g-3">
        <div class="col-md-3"><div class="meta-label">Employee</div><div class="meta-value">{{ $timesheet->user->name }}</div><div class="small text-muted">{{ $timesheet->user->employee_code }}</div></div>
        <div class="col-md-3"><div class="meta-label">Department</div><div class="meta-value">{{ $timesheet->department->name }}</div></div>
        <div class="col-md-2"><div class="meta-label">Regular</div><div class="meta-value">{{ $timesheet->total_regular_hours }}</div></div>
        <div class="col-md-2"><div class="meta-label">Overtime</div><div class="meta-value">{{ $timesheet->total_overtime_hours }}</div></div>
        <div class="col-md-2"><div class="meta-label">Total</div><div class="meta-value">{{ $timesheet->total_hours }}</div></div>
    </div>
    </div>
</div>
<div class="content-card overflow-hidden">
    <div class="content-card-header">
        <h2 class="h5 mb-1">Entry details</h2>
        <div class="small text-muted">Daily attendance, project, and overtime records.</div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Date</th><th>Day</th><th>Attendance Code</th><th>Project</th><th>Regular</th><th>Overtime</th><th>Remarks</th></tr></thead>
            <tbody>
            @foreach($timesheet->entries as $entry)
                <tr>
                    <td>{{ $entry->work_date->toDateString() }}</td>
                    <td>{{ $entry->day_name }}</td>
                    <td>{{ $entry->attendance_code }} - {{ config('timesheet.attendance_codes')[$entry->attendance_code] ?? '' }}</td>
                    <td>{{ $entry->project?->project_code }} {{ $entry->project?->project_name }}</td>
                    <td>{{ $entry->regular_hours }}</td>
                    <td>{{ $entry->overtime_hours }}</td>
                    <td>{{ $entry->remarks }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
