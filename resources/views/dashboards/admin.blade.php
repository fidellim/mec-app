@extends('layouts.app')

@section('content')
@php
    $periodLabel = $period
        ? 'Week '.$period->week_number.', '.$period->year.' ('.$period->start_date->format('M d, Y').' - '.$period->end_date->format('M d, Y').')'
        : 'No weekly period available';
    $hasAttention = $missing > 0 || $summary['rejected'] > 0;
    $periodFilters = $period ? ['week_from' => $period->week_number, 'year' => $period->year] : [];
@endphp
<div class="section-header">
    <div>
        <h1 class="h3 page-heading mb-1">Admin Dashboard</h1>
        <div class="text-muted">Monitor weekly submission progress across departments.</div>
        <div class="dashboard-period-pill">Reporting period: {{ $periodLabel }}</div>
    </div>
</div>
<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="content-card stat-card p-3"><div class="stat-label">Submitted</div><div class="stat-value">{{ $summary['submitted'] }}</div><div class="small text-muted">Awaiting approval</div></div></div>
    <div class="col-md-3"><div class="content-card stat-card p-3"><div class="stat-label">Approved</div><div class="stat-value">{{ $summary['approved'] }}</div><div class="small text-muted">Completed for payroll review</div></div></div>
    <div class="col-md-3"><div class="content-card stat-card p-3"><div class="stat-label">Rejected</div><div class="stat-value">{{ $summary['rejected'] }}</div><div class="small text-muted">Returned to employees</div></div></div>
    <div class="col-md-3"><div class="content-card stat-card p-3"><div class="stat-label">Missing submissions</div><div class="stat-value">{{ $missing }}</div><div class="small text-muted">Active employees without submitted sheets</div></div></div>
</div>

<div class="content-card mb-4">
    <div class="content-card-header">
        <h2 class="h5 mb-1">Needs attention</h2>
        <div class="small text-muted">Follow up on submission gaps and returned timesheets.</div>
    </div>
    <div class="content-card-body">
        <div class="dashboard-attention-grid">
            <div class="dashboard-attention-item">
                <div class="meta-label">Missing submissions</div>
                <div class="stat-value fs-3 mt-2">{{ $missing }}</div>
                <div class="small text-muted mt-2">Employees who have not submitted or received approval for this period.</div>
                <a class="btn btn-sm btn-outline-primary mt-3" href="{{ route('admin.timesheets.index', array_merge($periodFilters, ['status' => 'not_submitted'])) }}">Review missing</a>
            </div>
            <div class="dashboard-attention-item">
                <div class="meta-label">Rejected timesheets</div>
                <div class="stat-value fs-3 mt-2">{{ $summary['rejected'] }}</div>
                <div class="small text-muted mt-2">Timesheets that need employee revision before approval.</div>
                <a class="btn btn-sm btn-outline-primary mt-3" href="{{ route('admin.timesheets.index', array_merge($periodFilters, ['status' => 'rejected'])) }}">Review rejected</a>
            </div>
        </div>
        @unless($hasAttention)
            <div class="dashboard-empty mt-3">No missing or rejected submissions need follow-up for this reporting period.</div>
        @endunless
    </div>
</div>

<div class="mb-4">
    @include('dashboards.partials.regional_submission_chart', [
        'period' => $period,
        'regionalSubmissionSummary' => $regionalSubmissionSummary,
    ])
</div>
<div class="content-card overflow-hidden">
    <div class="content-card-header">
        <h2 class="h5 mb-1">Department health</h2>
        <div class="small text-muted">Timesheet status by department for the reporting period.</div>
    </div>
    <div class="table-responsive">
    <table class="table table-hover mb-0"><thead><tr><th>Department</th><th>Total</th><th>Submitted</th><th>Approved</th><th>Rejected</th><th>Missing</th><th></th></tr></thead><tbody>
        @forelse($departments as $department)
            <tr>
                <td class="fw-semibold">{{ $department->name }}</td>
                <td>{{ $department->timesheets_count }}</td>
                <td>{{ $department->submitted_count }}</td>
                <td>{{ $department->approved_count }}</td>
                <td>{{ $department->rejected_count }}</td>
                <td>{{ $department->missing_count }}</td>
                <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.timesheets.index', array_merge($periodFilters, ['department_id' => $department->id])) }}">View</a></td>
            </tr>
        @empty
            <tr><td colspan="7" class="empty-state">No departments found.</td></tr>
        @endforelse
    </tbody></table>
    </div>
</div>
@include('shared.leave_balance_cards', [
    'leaveBalances' => $leaveBalances,
    'title' => 'My leave balances',
    'description' => 'Your eligible leave entitlements for the current calendar year.',
    'class' => 'mt-4',
])
@endsection
