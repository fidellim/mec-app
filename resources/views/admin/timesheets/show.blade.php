@extends('layouts.app')

@section('content')
@php
    $isAdminApprovalExcluded = $timesheet->user?->role === 'hod'
        && app(\App\Services\AdminExclusionService::class)->approvalExcluded(auth()->user(), $timesheet->user);
@endphp
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
@if($timesheet->status === 'voided')
    <div class="alert alert-warning">
        <strong>Voided timesheet:</strong> This record is kept for audit history and is excluded from corrected submissions and exports.
        @if($timesheet->void_reason)
            <div class="mt-2"><strong>Reason:</strong> {{ $timesheet->void_reason }}</div>
        @endif
        @if($timesheet->voider || $timesheet->voided_at)
            <div class="small mt-2">
                Voided
                @if($timesheet->voider)
                    by {{ $timesheet->voider->name }}
                @endif
                @if($timesheet->voided_at)
                    on {{ $timesheet->voided_at->format('M j, Y g:i A') }}
                @endif
            </div>
        @endif
    </div>
@endif
@if($timesheet->status === 'recalled')
    <div class="alert alert-warning">
        <strong>Approved timesheet recalled:</strong> This record is waiting for the employee to correct and resubmit it.
    </div>
@endif
@include('shared.timesheet_detail', ['timesheet' => $timesheet])
@include('shared.timesheet_correction_requests', ['timesheet' => $timesheet])
@if($timesheet->status === 'submitted')
    @php
        $actor = auth()->user();
        $isOwnTimesheet = (int) $timesheet->user_id === (int) $actor->id;
        $canTakeApprovalAction = ! $isOwnTimesheet
            && ! $isAdminApprovalExcluded
            && ($actor->role === 'super_admin' || $actor->role === 'admin');
    @endphp

    @if($isOwnTimesheet)
        <div class="alert alert-warning mt-3">
            You cannot approve or reject your own timesheet. Another Admin or Super Admin must review this submission.
        </div>
    @elseif($isAdminApprovalExcluded)
        <div class="alert alert-warning mt-3">
            You can view this timesheet, but another Admin or Super Admin reviewer is assigned to approve or reject it.
        </div>
    @elseif($canTakeApprovalAction)
    <div class="content-card mt-3">
        <div class="content-card-header">
            <h2 class="h5 mb-1">Approval action</h2>
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
@endif
@if($timesheet->status === 'approved')
    @php
        $actor = auth()->user();
        $isOwnTimesheet = (int) $timesheet->user_id === (int) $actor->id;
        $canRecallApproved = ! $isOwnTimesheet
            && ! $isAdminApprovalExcluded
            && ($actor->role === 'super_admin' || $actor->role === 'admin');
    @endphp

    @if($canRecallApproved)
        <div class="content-card mt-3">
            <div class="content-card-header">
                <h2 class="h5 mb-1">Recall approved timesheet</h2>
                <div class="small text-muted">Send the approved record back to the employee for correction without voiding it.</div>
            </div>
            <div class="content-card-body">
                <form method="post" action="{{ route('admin.timesheets.recall-approved', $timesheet) }}" data-confirm="Recall this approved timesheet and notify the employee?">
                    @csrf
                    <label class="form-label" for="recall_reason">Recall reason</label>
                    <textarea id="recall_reason" class="form-control @error('recall_reason') is-invalid @enderror" name="recall_reason" rows="3" required placeholder="Explain what the employee needs to correct.">{{ old('recall_reason') }}</textarea>
                    @error('recall_reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">The reason, reviewer, timestamp, and IP address are stored in the history log.</div>
                    <button class="btn btn-warning mt-3">Recall approved timesheet</button>
                </form>
            </div>
        </div>
    @elseif($isOwnTimesheet)
        <div class="alert alert-warning mt-3">You cannot recall your own approved timesheet. Another authorized reviewer must complete this correction.</div>
    @elseif($isAdminApprovalExcluded)
        <div class="alert alert-warning mt-3">Another Admin or Super Admin reviewer is assigned to recall this approved HOD timesheet.</div>
    @endif

    @if($actor->role === 'super_admin')
        <div class="content-card mt-3">
            <div class="content-card-header">
                <h2 class="h5 mb-1">Correction action</h2>
                <div class="small text-muted">Void an approved timesheet only when it needs to be replaced with a corrected submission.</div>
            </div>
            <div class="content-card-body">
                @if($isOwnTimesheet)
                    <div class="alert alert-warning mb-0">You cannot void your own timesheet. Another Super Admin must complete this correction.</div>
                @else
                    <form method="post" action="{{ route('admin.timesheets.void', $timesheet) }}" data-confirm="Void this approved timesheet? The employee will be able to create a corrected timesheet for this week.">
                        @csrf
                        <label class="form-label" for="void_reason">Void reason</label>
                        <textarea id="void_reason" class="form-control @error('void_reason') is-invalid @enderror" name="void_reason" rows="3" required placeholder="Explain why this approved timesheet needs to be replaced.">{{ old('void_reason') }}</textarea>
                        @error('void_reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">The original record, reason, Super Admin, and timestamp remain visible in audit history.</div>
                        <button class="btn btn-warning mt-3">Void timesheet</button>
                    </form>
                @endif
            </div>
        </div>
    @endif
@endif
@endsection
