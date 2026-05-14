@extends('layouts.app')

@section('content')
<div class="section-header">
    <div>
        <h1 class="h3 page-heading mb-1">Review Timesheet</h1>
        <div class="text-muted">Approve the submission or return it with a required comment.</div>
    </div>
    @include('partials.status', ['status' => $timesheet->status])
</div>
@include('shared.timesheet_detail', ['timesheet' => $timesheet])
@if($timesheet->status === 'submitted')
<div class="content-card mt-3">
    <div class="content-card-header">
        <h2 class="h5 mb-1">Approval action</h2>
        <div class="small text-muted">Rejecting requires a comment that the employee can see.</div>
    </div>
    <div class="content-card-body">
    <div class="d-flex gap-2 mb-3">
        <form method="post" action="{{ route('hod.timesheets.approve', $timesheet) }}" data-confirm="Approve this timesheet?">@csrf<button class="btn btn-success">Approve</button></form>
    </div>
    <form method="post" action="{{ route('hod.timesheets.reject', $timesheet) }}" data-confirm="Reject this timesheet?">
        @csrf
        <label class="form-label">Rejection comment</label>
        <textarea class="form-control mb-2" name="rejection_comment" required></textarea>
        <button class="btn btn-danger">Reject</button>
    </form>
    </div>
</div>
@endif
@endsection
