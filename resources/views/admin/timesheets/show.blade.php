@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Timesheet Details</h1>
    @include('partials.status', ['status' => $timesheet->status])
</div>
@if($timesheet->status === 'rejected')
    <div class="alert alert-warning"><strong>Rejection comment:</strong> {{ $timesheet->rejection_comment }}</div>
@endif
@include('shared.timesheet_detail', ['timesheet' => $timesheet])
@if(auth()->user()->role === 'super_admin' && $timesheet->status === 'submitted')
<div class="content-card p-3 mt-3">
    <form method="post" action="{{ route('admin.timesheets.approve', $timesheet) }}" class="d-inline" data-confirm="Approve this timesheet?">@csrf<button class="btn btn-success">Approve</button></form>
    <form method="post" action="{{ route('admin.timesheets.reject', $timesheet) }}" class="mt-3" data-confirm="Reject this timesheet?">
        @csrf
        <label class="form-label">Rejection comment</label>
        <textarea class="form-control mb-2" name="rejection_comment" required></textarea>
        <button class="btn btn-danger">Reject</button>
    </form>
</div>
@endif
@endsection
