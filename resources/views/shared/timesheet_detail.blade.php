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
        @php
            $attendanceCodes = config('timesheet.attendance_codes');
            $entriesByDate = $timesheet->entries
                ->sortBy(fn ($entry) => $entry->work_date->toDateString().'|'.str_pad((string) $entry->id, 10, '0', STR_PAD_LEFT))
                ->groupBy(fn ($entry) => $entry->work_date->toDateString());
        @endphp
        <table class="table table-hover mb-0">
            <thead><tr><th>Attendance Code</th><th>Project / Job</th><th class="text-end">Regular</th><th class="text-end">Overtime</th><th>Remarks</th></tr></thead>
            <tbody>
            @foreach($entriesByDate as $workDate => $dayEntries)
                <tr class="timesheet-day-row">
                    <td colspan="5">
                        <div>
                            <span class="fw-semibold">{{ $dayEntries->first()->day_name }}</span>
                            <span class="text-muted ms-2">{{ $workDate }}</span>
                        </div>
                    </td>
                </tr>
                @foreach($dayEntries as $entry)
                    <tr>
                        <td class="timesheet-entry-code">
                            <div class="fw-semibold">{{ $entry->attendance_code ?: '-' }}</div>
                            <div class="small text-muted">{{ $attendanceCodes[$entry->attendance_code] ?? 'No attendance code' }}</div>
                        </td>
                        <td class="project-name-cell">
                            @if($entry->project)
                                <div class="fw-semibold">{{ $entry->project->project_code }}</div>
                                <div class="small text-muted">{{ $entry->project->project_name }}</div>
                            @else
                                <span class="badge filter-summary-badge px-3 py-2">Non-project</span>
                            @endif
                        </td>
                        <td class="text-end fw-semibold">{{ $entry->regular_hours }}</td>
                        <td class="text-end fw-semibold">{{ $entry->overtime_hours }}</td>
                        <td>{{ $entry->remarks }}</td>
                    </tr>
                @endforeach
            @endforeach
            </tbody>
        </table>
    </div>
</div>
