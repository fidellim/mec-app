<div class="content-card overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Week</th><th>Period</th><th>Status</th><th>Total</th><th></th></tr></thead>
            <tbody>
            @forelse($timesheets as $timesheet)
                <tr>
                    <td><span class="fw-semibold">{{ $timesheet->period->week_number }} / {{ $timesheet->period->year }}</span></td>
                    <td>{{ $timesheet->period->start_date->toDateString() }} to {{ $timesheet->period->end_date->toDateString() }}</td>
                    <td>@include('partials.status', ['status' => $timesheet->status])</td>
                    <td><span class="fw-semibold">{{ $timesheet->total_hours }}</span> <span class="text-muted">hrs</span></td>
                    <td class="text-end">
                        <div class="action-group">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('employee.timesheets.show', $timesheet) }}">View</a>
                            @if($timesheet->editableBy(auth()->user()))
                                <a class="btn btn-sm btn-primary" href="{{ route('employee.timesheets.edit', $timesheet) }}">Edit</a>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty-state">No timesheets found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
