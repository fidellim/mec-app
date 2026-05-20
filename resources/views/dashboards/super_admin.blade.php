@extends('layouts.app')

@section('content')
<div class="section-header">
    <div>
        <h1 class="h3 page-heading mb-1">Super Admin Dashboard</h1>
        <div class="text-muted">System overview for users, departments, projects, and the open period.</div>
    </div>
</div>
<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="content-card stat-card p-3"><div class="stat-label">Total users</div><div class="stat-value">{{ $totalUsers }}</div></div></div>
    <div class="col-md-3"><div class="content-card stat-card p-3"><div class="stat-label">Departments</div><div class="stat-value">{{ $activeDepartments }}</div></div></div>
    <div class="col-md-3"><div class="content-card stat-card p-3"><div class="stat-label">Active projects</div><div class="stat-value">{{ $activeProjects }}</div></div></div>
    <div class="col-md-3"><div class="content-card stat-card p-3"><div class="stat-label">Open period</div><div class="fs-5 fw-bold">{{ $period?->start_date?->toDateString() ?? 'None' }}</div><div class="small text-muted">{{ $period?->end_date?->toDateString() ?? '' }}</div></div></div>
</div>
<div class="mb-4">
    @include('dashboards.partials.regional_submission_chart', [
        'period' => $submissionPeriod,
        'regionalSubmissionSummary' => $regionalSubmissionSummary,
    ])
</div>
<div class="content-card">
    <div class="content-card-header">
        <h2 class="h5 mb-1">System-wide submission summary</h2>
        <div class="small text-muted">Current period status across the organization.</div>
    </div>
    <div class="content-card-body">
        <div class="row g-3">
            <div class="col-md-4"><div class="meta-label">Submitted</div><div class="meta-value">{{ $summary['submitted'] }}</div></div>
            <div class="col-md-4"><div class="meta-label">Approved</div><div class="meta-value">{{ $summary['approved'] }}</div></div>
            <div class="col-md-4"><div class="meta-label">Rejected</div><div class="meta-value">{{ $summary['rejected'] }}</div></div>
        </div>
    </div>
</div>
@endsection
