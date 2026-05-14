@extends('layouts.app')

@section('content')
<div class="section-header">
    <div>
        <h1 class="h3 page-heading mb-1">Department Timesheets</h1>
        <div class="text-muted">Review and action employee submissions from your department.</div>
    </div>
    <form class="d-flex gap-2">
        <select class="form-select" name="status">
            <option value="">All statuses</option>
            @foreach(['submitted','approved','rejected','draft'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>@endforeach
        </select>
        <button class="btn btn-outline-primary">Filter</button>
    </form>
</div>
<div class="content-card overflow-hidden"><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Employee</th><th>Week</th><th>Status</th><th>Total</th><th></th></tr></thead><tbody>
@forelse($timesheets as $timesheet)
    <tr><td class="fw-semibold">{{ $timesheet->user->name }}</td><td>{{ $timesheet->period->week_number }} / {{ $timesheet->period->year }}</td><td>@include('partials.status', ['status' => $timesheet->status])</td><td><span class="fw-semibold">{{ $timesheet->total_hours }}</span></td><td class="text-end"><a class="btn btn-sm btn-primary" href="{{ route('hod.timesheets.show', $timesheet) }}">Review</a></td></tr>
@empty
    <tr><td colspan="5" class="empty-state">No records found.</td></tr>
@endforelse
</tbody></table></div></div>
<div class="mt-3">{{ $timesheets->links() }}</div>
@endsection
