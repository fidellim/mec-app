@extends('layouts.app')

@section('content')
<h1 class="h3 mb-4">Super Admin Dashboard</h1>
<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="content-card p-3"><div class="text-muted">Total users</div><div class="fs-3">{{ $totalUsers }}</div></div></div>
    <div class="col-md-3"><div class="content-card p-3"><div class="text-muted">Departments</div><div class="fs-3">{{ $activeDepartments }}</div></div></div>
    <div class="col-md-3"><div class="content-card p-3"><div class="text-muted">Active projects</div><div class="fs-3">{{ $activeProjects }}</div></div></div>
    <div class="col-md-3"><div class="content-card p-3"><div class="text-muted">Open period</div><div class="fs-6">{{ $period?->start_date?->toDateString() ?? 'None' }}</div></div></div>
</div>
<div class="content-card p-3">
    <h2 class="h5">System-wide submission summary</h2>
    <div class="d-flex gap-4">
        <div>Submitted: <strong>{{ $summary['submitted'] }}</strong></div>
        <div>Approved: <strong>{{ $summary['approved'] }}</strong></div>
        <div>Rejected: <strong>{{ $summary['rejected'] }}</strong></div>
    </div>
</div>
@endsection
