@extends('layouts.app')

@section('content')
@php
    $isApprovalExcluded = app(\App\Services\HodExclusionService::class)->approvalExcluded(auth()->user(), $timesheet->user);
@endphp
<div class="section-header">
    <div>
        <h1 class="h3 page-heading mb-1">Review Timesheet</h1>
        <div class="text-muted">Approve the submission or return it with a required comment.</div>
    </div>
    @include('partials.status', ['status' => $timesheet->status])
</div>
@include('shared.timesheet_detail', ['timesheet' => $timesheet])
@if($timesheet->status === 'submitted')
    @if((int) $timesheet->user_id === (int) auth()->id())
        <div class="alert alert-warning mt-3">
            You cannot approve or reject your own timesheet. An Admin or Super Admin must review this submission.
        </div>
    @elseif($isApprovalExcluded)
        <div class="alert alert-warning mt-3">
            You can view this timesheet, but another HOD approver is assigned to approve or reject it.
        </div>
    @else
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
@endif
@if($timesheet->status === 'approved')
    @php
        $isOwnTimesheet = (int) $timesheet->user_id === (int) auth()->id();
    @endphp
    <div class="content-card mt-3">
        <div class="content-card-header">
            <h2 class="h5 mb-1">Recall approved timesheet</h2>
            <div class="small text-muted">Send the approved record back to the employee for correction.</div>
        </div>
        <div class="content-card-body">
            @if($isOwnTimesheet)
                <div class="alert alert-warning mb-0">You cannot recall your own approved timesheet. Another authorized reviewer must complete this correction.</div>
            @elseif($isApprovalExcluded)
                <div class="alert alert-warning mb-0">Another HOD approver is assigned to recall this approved timesheet.</div>
            @else
                <form method="post" action="{{ route('hod.timesheets.recall-approved', $timesheet) }}" data-confirm="Recall this approved timesheet and notify the employee?">
                    @csrf
                    <label class="form-label" for="recall_reason">Recall reason</label>
                    <textarea id="recall_reason" class="form-control @error('recall_reason') is-invalid @enderror" name="recall_reason" rows="3" required placeholder="Explain what the employee needs to correct.">{{ old('recall_reason') }}</textarea>
                    @error('recall_reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">The reason, reviewer, timestamp, and IP address are stored in the history log.</div>
                    <button class="btn btn-warning mt-3">Recall approved timesheet</button>
                </form>
            @endif
        </div>
    </div>
@endif
@endsection
