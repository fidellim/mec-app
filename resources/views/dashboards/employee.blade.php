@extends('layouts.app')

@section('content')
<div class="section-header">
    <div>
        <h1 class="h3 page-heading mb-1">Employee Dashboard</h1>
        <div class="text-muted">Track your current week, drafts, and timesheets that need attention.</div>
    </div>
</div>
<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="content-card stat-card p-3"><div class="stat-label">Current week</div><div class="stat-value fs-4">@if($current) @include('partials.status', ['status' => $current->status]) @else <span class="badge text-bg-secondary">Not submitted</span> @endif</div></div></div>
    <div class="col-md-4"><div class="content-card stat-card p-3"><div class="stat-label">Drafts</div><div class="stat-value">{{ $drafts->count() }}</div><div class="small text-muted">Saved but not submitted</div></div></div>
    <div class="col-md-4"><div class="content-card stat-card p-3"><div class="stat-label">Rejected requiring action</div><div class="stat-value">{{ $rejected->count() }}</div><div class="small text-muted">Revise and resubmit</div></div></div>
</div>
@include('shared.leave_balance_cards', ['leaveBalances' => $leaveBalances])
<div class="section-header">
    <div>
        <h2 class="h5 mb-1">Recent submissions</h2>
        <div class="text-muted">Your latest weekly timesheets.</div>
    </div>
    @if(auth()->user()->department_id)
        <a class="btn btn-primary" href="{{ route('employee.timesheets.create') }}">Create Weekly Timesheet</a>
    @else
        <button class="btn btn-outline-secondary" type="button" disabled>Department Required</button>
    @endif
</div>
@unless(auth()->user()->department_id)
    <div class="alert alert-warning">
        You need to be assigned to a department before creating or submitting a timesheet. Please contact Super Admin.
    </div>
@endunless
@include('employee.timesheets._table', ['timesheets' => $recent])
@endsection
