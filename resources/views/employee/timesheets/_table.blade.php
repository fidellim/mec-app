<div class="content-card p-3">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Week</th><th>Period</th><th>Status</th><th>Total</th><th></th></tr></thead>
            <tbody>
            @forelse($timesheets as $timesheet)
                <tr>
                    <td>{{ $timesheet->period->week_number }} / {{ $timesheet->period->year }}</td>
                    <td>{{ $timesheet->period->start_date->toDateString() }} to {{ $timesheet->period->end_date->toDateString() }}</td>
                    <td>@include('partials.status', ['status' => $timesheet->status])</td>
                    <td>{{ $timesheet->total_hours }} hrs</td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('employee.timesheets.show', $timesheet) }}">View</a>
                        @if($timesheet->editableBy(auth()->user()))
                            <a class="btn btn-sm btn-primary" href="{{ route('employee.timesheets.edit', $timesheet) }}">Edit</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-4">No timesheets found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
