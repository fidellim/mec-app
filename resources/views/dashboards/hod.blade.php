@extends('layouts.app')

@section('content')
@php
    $periodLabel = $period
        ? 'Week '.$period->week_number.', '.$period->year.' ('.$period->start_date->format('M d, Y').' - '.$period->end_date->format('M d, Y').')'
        : 'No weekly period available';
@endphp
<div class="section-header">
    <div>
        <h1 class="h3 page-heading mb-1">Head of Department Dashboard</h1>
        <div class="text-muted">Review department submissions and follow up on missing timesheets.</div>
        <div class="dashboard-period-pill">Reporting period: {{ $periodLabel }}</div>
    </div>
    <a class="btn btn-primary" href="{{ route('hod.timesheets.index', ['status' => 'submitted']) }}">Review Pending Approvals</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-3 col-md-6"><div class="content-card stat-card p-3"><div class="stat-label">Pending approvals</div><div class="stat-value">{{ $pending }}</div><div class="small text-muted">Submitted timesheets waiting for you</div></div></div>
    <div class="col-lg-3 col-md-6"><div class="content-card stat-card p-3"><div class="stat-label">Not submitted</div><div class="stat-value">{{ $missing }}</div><div class="small text-muted">Employees to follow up with</div></div></div>
    <div class="col-lg-3 col-md-6"><div class="content-card stat-card p-3"><div class="stat-label">Rejected</div><div class="stat-value">{{ $rejected }}</div><div class="small text-muted">Returned for revision</div></div></div>
    <div class="col-lg-3 col-md-6"><div class="content-card stat-card p-3"><div class="stat-label">Approved</div><div class="stat-value">{{ $approved }}</div><div class="small text-muted">Completed this period</div></div></div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="content-card h-100">
            <div class="content-card-header">
                <h2 class="h5 mb-1">Approval work</h2>
                <div class="small text-muted">Start with pending approvals, then follow up on missing submissions.</div>
            </div>
            <div class="content-card-body">
                <div class="dashboard-shortcut-grid">
                    <a class="dashboard-shortcut" href="{{ route('hod.timesheets.index', ['corrections' => 'open']) }}">
                        <span><span class="dashboard-shortcut-title">Review correction requests</span><span class="dashboard-shortcut-meta d-block">{{ $openCorrectionRequestCount }} project concerns need a decision</span></span><span class="badge text-bg-{{ $openCorrectionRequestCount ? 'warning' : 'secondary' }}">{{ $openCorrectionRequestCount }}</span>
                    </a>
                    <a class="dashboard-shortcut" href="{{ route('hod.timesheets.index', ['status' => 'submitted']) }}">
                        <span>
                            <span class="dashboard-shortcut-title">Review pending approvals</span>
                            <span class="dashboard-shortcut-meta d-block">{{ $pending }} timesheets awaiting review</span>
                        </span>
                        <span class="dashboard-shortcut-arrow">-></span>
                    </a>
                    <a class="dashboard-shortcut" href="{{ route('hod.tracker') }}">
                        <span>
                            <span class="dashboard-shortcut-title">Open department tracker</span>
                            <span class="dashboard-shortcut-meta d-block">{{ $missing }} missing submissions to check</span>
                        </span>
                        <span class="dashboard-shortcut-arrow">-></span>
                    </a>
                    <a class="dashboard-shortcut" href="{{ route('assigned.leave-plans.index') }}">
                        <span>
                            <span class="dashboard-shortcut-title">Review assigned leave plans</span>
                            <span class="dashboard-shortcut-meta d-block">Approve or reject leave requests assigned to you</span>
                        </span>
                        <span class="dashboard-shortcut-arrow">-></span>
                    </a>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="content-card h-100">
            <div class="content-card-header">
                <h2 class="h5 mb-1">Managed departments</h2>
                <div class="small text-muted">Dashboard counts are limited to these departments.</div>
            </div>
            <div class="content-card-body">
                @if($departments->isNotEmpty())
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($departments as $department)
                            <span class="badge bg-body-secondary border text-body">{{ $department->name }}</span>
                        @endforeach
                    </div>
                @else
                    <div class="dashboard-empty">No managed departments assigned. Ask a Super Admin to assign your department coverage.</div>
                @endif
            </div>
        </div>
    </div>
</div>

@include('dashboards.partials.regional_submission_chart', [
    'period' => $period,
    'regionalSubmissionSummary' => $regionalSubmissionSummary,
    'actionUrl' => $period ? route('hod.tracker') : null,
    'actionLabel' => 'Open department tracker',
])
@include('shared.leave_balance_cards', [
    'leaveBalances' => $leaveBalances,
    'title' => 'My leave balances',
    'description' => 'Your eligible leave entitlements for the current calendar year.',
    'class' => 'mt-4',
])
@endsection
