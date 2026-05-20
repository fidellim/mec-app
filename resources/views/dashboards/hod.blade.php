@extends('layouts.app')

@section('content')
<div class="section-header">
    <div>
        <h1 class="h3 page-heading mb-1">Head of Department Dashboard</h1>
        <div class="text-muted">Review department submissions and follow up on missing timesheets.</div>
        <div class="small text-muted">
            Reporting period:
            @if($period)
                Week {{ $period->week_number }}, {{ $period->year }} ({{ $period->start_date->format('M d, Y') }} - {{ $period->end_date->format('M d, Y') }})
            @else
                No weekly period available
            @endif
        </div>
    </div>
    <a class="btn btn-primary" href="{{ route('hod.timesheets.index', ['status' => 'submitted']) }}">Review Pending Approvals</a>
</div>
<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="content-card stat-card p-3"><div class="stat-label">Pending approvals</div><div class="stat-value">{{ $pending }}</div></div></div>
    <div class="col-md-3"><div class="content-card stat-card p-3"><div class="stat-label">Approved</div><div class="stat-value">{{ $approved }}</div></div></div>
    <div class="col-md-3"><div class="content-card stat-card p-3"><div class="stat-label">Rejected</div><div class="stat-value">{{ $rejected }}</div></div></div>
    <div class="col-md-3"><div class="content-card stat-card p-3"><div class="stat-label">Not submitted</div><div class="stat-value">{{ $missing }}</div></div></div>
</div>
@include('dashboards.partials.regional_submission_chart', [
    'period' => $period,
    'regionalSubmissionSummary' => $regionalSubmissionSummary,
])
@endsection
