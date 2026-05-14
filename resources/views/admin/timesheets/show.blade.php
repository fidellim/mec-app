@extends('layouts.app')

@section('content')
<div class="section-header">
    <div>
        <h1 class="h3 page-heading mb-1">Timesheet Details</h1>
        <div class="text-muted">Review employee weekly entries and approval status.</div>
    </div>
    @include('partials.status', ['status' => $timesheet->status])
</div>
@if($timesheet->status === 'rejected')
    <div class="alert alert-warning"><strong>Rejection comment:</strong> {{ $timesheet->rejection_comment }}</div>
@endif
@include('shared.timesheet_detail', ['timesheet' => $timesheet])
@if(auth()->user()->role === 'super_admin' && $timesheet->status === 'submitted')
<div class="content-card mt-3">
    <div class="content-card-header">
        <h2 class="h5 mb-1">Super Admin action</h2>
        <div class="small text-muted">Approve or reject this submitted timesheet.</div>
    </div>
    <div class="content-card-body">
    <form method="post" action="{{ route('admin.timesheets.approve', $timesheet) }}" class="d-inline" data-confirm="Approve this timesheet?">@csrf<button class="btn btn-success">Approve</button></form>
    <form method="post" action="{{ route('admin.timesheets.reject', $timesheet) }}" class="mt-3" data-confirm="Reject this timesheet?">
        @csrf
        <label class="form-label">Rejection comment</label>
        <textarea class="form-control mb-2" name="rejection_comment" required></textarea>
        <button class="btn btn-danger">Reject</button>
    </form>
    </div>
</div>
@endif
@endsection
