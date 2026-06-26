<div class="content-card overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    @if($showEmployee ?? false)<th>Employee</th>@endif
                    @if($showDepartment ?? false)<th>Department</th>@endif
                    <th>Leave Type</th>
                    <th>Date Range</th>
                    <th>Duration</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($leavePlans as $leavePlan)
                <tr>
                    @if($showEmployee ?? false)<td class="fw-semibold">{{ $leavePlan->user?->name ?: '-' }}</td>@endif
                    @if($showDepartment ?? false)<td>{{ $leavePlan->department?->name ?: '-' }}</td>@endif
                    <td>{{ $leavePlan->leaveLabel() }}</td>
                    <td>{{ $leavePlan->start_date->toFormattedDateString() }} to {{ $leavePlan->end_date->toFormattedDateString() }}</td>
                    <td>{{ $leavePlan->leaveLengthLabel() }}</td>
                    <td>@include('partials.status', ['status' => $leavePlan->status])</td>
                    <td class="text-end"><a class="btn btn-sm btn-primary" href="{{ route($showRoute, $leavePlan) }}">Review</a></td>
                </tr>
            @empty
                <tr><td colspan="{{ 5 + (($showEmployee ?? false) ? 1 : 0) + (($showDepartment ?? false) ? 1 : 0) }}" class="empty-state">No leave plans found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
