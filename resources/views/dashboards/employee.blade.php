@extends('layouts.app')

@section('content')
<h1 class="h3 mb-4">Employee Dashboard</h1>
<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="content-card p-3"><div class="text-muted">Current week</div><div class="fs-4">@if($current) @include('partials.status', ['status' => $current->status]) @else Not submitted @endif</div></div></div>
    <div class="col-md-4"><div class="content-card p-3"><div class="text-muted">Drafts</div><div class="fs-4">{{ $drafts->count() }}</div></div></div>
    <div class="col-md-4"><div class="content-card p-3"><div class="text-muted">Rejected requiring action</div><div class="fs-4">{{ $rejected->count() }}</div></div></div>
</div>
<div class="d-flex justify-content-between mb-3">
    <h2 class="h5">Recent submissions</h2>
    <a class="btn btn-primary" href="{{ route('employee.timesheets.create') }}">Create Weekly Timesheet</a>
</div>
@include('employee.timesheets._table', ['timesheets' => $recent])
@endsection
