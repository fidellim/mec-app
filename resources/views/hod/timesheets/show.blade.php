@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Review Timesheet</h1>
    @include('partials.status', ['status' => $timesheet->status])
</div>
@include('shared.timesheet_detail', ['timesheet' => $timesheet])
@if($timesheet->status === 'submitted')
<div class="content-card p-3 mt-3">
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
@endif
@endsection
