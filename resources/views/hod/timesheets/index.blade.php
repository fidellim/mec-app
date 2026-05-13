@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Department Timesheets</h1>
    <form class="d-flex gap-2">
        <select class="form-select" name="status">
            <option value="">All statuses</option>
            @foreach(['submitted','approved','rejected','draft'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>@endforeach
        </select>
        <button class="btn btn-outline-primary">Filter</button>
    </form>
</div>
<div class="content-card p-3"><table class="table mb-0"><thead><tr><th>Employee</th><th>Week</th><th>Status</th><th>Total</th><th></th></tr></thead><tbody>
@forelse($timesheets as $timesheet)
    <tr><td>{{ $timesheet->user->name }}</td><td>{{ $timesheet->period->week_number }} / {{ $timesheet->period->year }}</td><td>@include('partials.status', ['status' => $timesheet->status])</td><td>{{ $timesheet->total_hours }}</td><td class="text-end"><a class="btn btn-sm btn-primary" href="{{ route('hod.timesheets.show', $timesheet) }}">Review</a></td></tr>
@empty
    <tr><td colspan="5" class="text-center text-muted py-4">No records found.</td></tr>
@endforelse
</tbody></table></div>
<div class="mt-3">{{ $timesheets->links() }}</div>
@endsection
