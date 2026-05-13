@extends('layouts.app')

@section('content')
<h1 class="h3 mb-4">HOD Dashboard</h1>
<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="content-card p-3"><div class="text-muted">Pending approvals</div><div class="fs-3">{{ $pending }}</div></div></div>
    <div class="col-md-3"><div class="content-card p-3"><div class="text-muted">Approved this week</div><div class="fs-3">{{ $approved }}</div></div></div>
    <div class="col-md-3"><div class="content-card p-3"><div class="text-muted">Rejected this week</div><div class="fs-3">{{ $rejected }}</div></div></div>
    <div class="col-md-3"><div class="content-card p-3"><div class="text-muted">Not submitted</div><div class="fs-3">{{ $missing }}</div></div></div>
</div>
<a class="btn btn-primary" href="{{ route('hod.timesheets.index', ['status' => 'submitted']) }}">Review Pending Approvals</a>
@endsection
