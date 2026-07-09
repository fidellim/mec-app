@extends('layouts.app')

@section('content')
<div class="section-header">
    <div>
        <h1 class="h3 page-heading mb-1">My Leave Plans</h1>
        <div class="text-muted">Plan leave dates and track approval status.</div>
    </div>
    <div class="action-group">
        <a class="btn btn-outline-secondary" href="{{ route('employee.leave-plans.calendar') }}">Calendar</a>
        @if(auth()->user()->department_id)
            <a class="btn btn-primary" href="{{ route('employee.leave-plans.create') }}">Create Leave Plan</a>
        @else
            <button class="btn btn-outline-secondary" type="button" disabled>Department Required</button>
        @endif
    </div>
</div>
@unless(auth()->user()->department_id)
    <div class="alert alert-warning">
        You need to be assigned to a department before creating or submitting a leave plan. Please contact Super Admin.
    </div>
@endunless
<div class="content-card overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Leave Type</th><th>Date Range</th><th>Duration</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($leavePlans as $leavePlan)
                <tr>
                    <td class="fw-semibold">{{ $leavePlan->leaveLabel() }}</td>
                    <td>{{ $leavePlan->start_date->toFormattedDateString() }} to {{ $leavePlan->end_date->toFormattedDateString() }}</td>
                    <td>{{ $leavePlan->leaveLengthLabel() }}</td>
                    <td>@include('partials.status', ['status' => $leavePlan->status])</td>
                    <td class="text-end"><a class="btn btn-sm btn-primary" href="{{ route('employee.leave-plans.show', $leavePlan) }}">View</a></td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty-state">No leave plans found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@include('shared.pagination-footer', ['paginator' => $leavePlans, 'label' => 'leave plan'])
@endsection
