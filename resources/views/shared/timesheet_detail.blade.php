<div class="content-card p-3 mb-3">
    <div class="row g-3">
        <div class="col-md-3"><div class="text-muted">Employee</div><strong>{{ $timesheet->user->name }}</strong><div class="small text-muted">{{ $timesheet->user->employee_code }}</div></div>
        <div class="col-md-3"><div class="text-muted">Department</div><strong>{{ $timesheet->department->name }}</strong></div>
        <div class="col-md-2"><div class="text-muted">Regular</div><strong>{{ $timesheet->total_regular_hours }}</strong></div>
        <div class="col-md-2"><div class="text-muted">Overtime</div><strong>{{ $timesheet->total_overtime_hours }}</strong></div>
        <div class="col-md-2"><div class="text-muted">Total</div><strong>{{ $timesheet->total_hours }}</strong></div>
    </div>
</div>
<div class="content-card p-3">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Date</th><th>Day</th><th>Attendance Code</th><th>Project</th><th>Regular</th><th>Overtime</th><th>Description</th><th>Remarks</th></tr></thead>
            <tbody>
            @foreach($timesheet->entries as $entry)
                <tr>
                    <td>{{ $entry->work_date->toDateString() }}</td>
                    <td>{{ $entry->day_name }}</td>
                    <td>{{ $entry->attendance_code }} - {{ config('timesheet.attendance_codes')[$entry->attendance_code] ?? '' }}</td>
                    <td>{{ $entry->project?->project_code }} {{ $entry->project?->project_name }}</td>
                    <td>{{ $entry->regular_hours }}</td>
                    <td>{{ $entry->overtime_hours }}</td>
                    <td>{{ $entry->description }}</td>
                    <td>{{ $entry->remarks }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
