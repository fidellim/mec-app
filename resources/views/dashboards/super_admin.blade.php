@extends('layouts.app')

@section('content')
@php
    $openPeriodLabel = $period
        ? 'Week '.$period->week_number.', '.$period->year.' ('.$period->start_date->format('M d, Y').' - '.$period->end_date->format('M d, Y').')'
        : 'No open period';
    $submissionPeriodLabel = $submissionPeriod
        ? 'Week '.$submissionPeriod->week_number.', '.$submissionPeriod->year.' ('.$submissionPeriod->start_date->format('M d, Y').' - '.$submissionPeriod->end_date->format('M d, Y').')'
        : 'No reporting period available';
    $submissionPeriodFilters = $submissionPeriod ? ['week_from' => $submissionPeriod->week_number, 'year' => $submissionPeriod->year] : [];
@endphp
<div class="section-header">
    <div>
        <h1 class="h3 page-heading mb-1">Super Admin Dashboard</h1>
        <div class="text-muted">System overview for users, departments, projects, and the open period.</div>
        <div class="dashboard-period-pill">Open period: {{ $openPeriodLabel }}</div>
    </div>
</div>
<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="content-card stat-card p-3"><div class="stat-label">Total users</div><div class="stat-value">{{ $totalUsers }}</div><div class="small text-muted">All accounts in the portal</div></div></div>
    <div class="col-md-3"><div class="content-card stat-card p-3"><div class="stat-label">Active departments</div><div class="stat-value">{{ $activeDepartments }}</div><div class="small text-muted">Departments available for assignment</div></div></div>
    <div class="col-md-3"><div class="content-card stat-card p-3"><div class="stat-label">Active projects</div><div class="stat-value">{{ $activeProjects }}</div><div class="small text-muted">Projects available for timesheets</div></div></div>
    <div class="col-md-3"><div class="content-card stat-card p-3"><div class="stat-label">Open period</div><div class="fs-5 fw-bold">{{ $period?->start_date?->toDateString() ?? 'None' }}</div><div class="small text-muted">{{ $period?->end_date?->toDateString() ?: 'No active weekly period' }}</div></div></div>
</div>

<div class="content-card mb-4">
    <div class="content-card-header">
        <h2 class="h5 mb-1">System management</h2>
        <div class="small text-muted">Shortcuts for account, setup, and audit administration.</div>
    </div>
    <div class="content-card-body">
        <div class="dashboard-shortcut-grid">
            <a class="dashboard-shortcut" href="{{ route('manage.users.index') }}"><span><span class="dashboard-shortcut-title">Users</span><span class="dashboard-shortcut-meta d-block">Manage accounts, roles, and access status</span></span><span class="dashboard-shortcut-arrow">-></span></a>
            <a class="dashboard-shortcut" href="{{ route('manage.departments.index') }}"><span><span class="dashboard-shortcut-title">Departments</span><span class="dashboard-shortcut-meta d-block">Maintain departments and HOD assignments</span></span><span class="dashboard-shortcut-arrow">-></span></a>
            <a class="dashboard-shortcut" href="{{ route('manage.projects.index') }}"><span><span class="dashboard-shortcut-title">Projects</span><span class="dashboard-shortcut-meta d-block">Control active project options</span></span><span class="dashboard-shortcut-arrow">-></span></a>
            <a class="dashboard-shortcut" href="{{ route('manage.periods.index') }}"><span><span class="dashboard-shortcut-title">Periods</span><span class="dashboard-shortcut-meta d-block">Open and maintain weekly periods</span></span><span class="dashboard-shortcut-arrow">-></span></a>
            <a class="dashboard-shortcut" href="{{ route('manage.audit-logs.index') }}"><span><span class="dashboard-shortcut-title">Audit logs</span><span class="dashboard-shortcut-meta d-block">Review system activity and exports</span></span><span class="dashboard-shortcut-arrow">-></span></a>
            <a class="dashboard-shortcut" href="{{ route('manage.system-settings.index') }}"><span><span class="dashboard-shortcut-title">System settings</span><span class="dashboard-shortcut-meta d-block">Manage setup mode and portal controls</span></span><span class="dashboard-shortcut-arrow">-></span></a>
        </div>
    </div>
</div>

<div class="mb-4">
    @include('dashboards.partials.regional_submission_chart', [
        'period' => $submissionPeriod,
        'regionalSubmissionSummary' => $regionalSubmissionSummary,
        'actionUrl' => $submissionPeriod ? route('admin.timesheets.index', array_merge($submissionPeriodFilters, ['status' => 'not_submitted'])) : null,
        'actionLabel' => 'Review missing submissions',
    ])
</div>
<div class="content-card">
    <div class="content-card-header">
        <h2 class="h5 mb-1">Submission overview</h2>
        <div class="small text-muted">Reporting period: {{ $submissionPeriodLabel }}</div>
    </div>
    <div class="content-card-body">
        <div class="row g-3">
            <div class="col-md-4"><div class="meta-label">Submitted</div><div class="meta-value">{{ $summary['submitted'] }}</div><div class="small text-muted">Awaiting approval in the open period</div></div>
            <div class="col-md-4"><div class="meta-label">Approved</div><div class="meta-value">{{ $summary['approved'] }}</div><div class="small text-muted">Approved in the open period</div></div>
            <div class="col-md-4"><div class="meta-label">Rejected</div><div class="meta-value">{{ $summary['rejected'] }}</div><div class="small text-muted">Returned in the open period</div></div>
        </div>
    </div>
</div>
@include('shared.leave_balance_cards', [
    'leaveBalances' => $leaveBalances,
    'title' => 'My leave balances',
    'description' => 'Your eligible leave entitlements for the current calendar year.',
    'class' => 'mt-4',
])
@endsection
