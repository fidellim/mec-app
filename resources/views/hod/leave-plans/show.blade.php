@extends('layouts.app')

@section('content')
@php
    $prefix = request()->routeIs('admin.*') ? 'admin' : 'hod';
    $isOwnLeavePlan = (int) $leavePlan->user_id === (int) auth()->id();
@endphp
<div class="section-header">
    <div>
        <h1 class="h3 page-heading mb-1">Review Leave Plan</h1>
        <div class="text-muted">Approve the request or return it with a required comment.</div>
    </div>
    @include('partials.status', ['status' => $leavePlan->status])
</div>
@include('shared.leave_plan_detail', ['leavePlan' => $leavePlan])
@if($leavePlan->status === 'recalled')
    <div class="alert alert-warning mt-3">This approved leave plan was recalled and is waiting for the employee to correct and resubmit it.</div>
@endif
@if($leavePlan->status === 'voided')
    <div class="alert alert-warning mt-3">This approved leave plan was voided and is kept for audit history only.</div>
@endif
@if($leavePlan->status === 'submitted')
    <div class="content-card mt-3">
        <div class="content-card-header">
            <h2 class="h5 mb-1">Approval action</h2>
            <div class="small text-muted">Rejecting requires a comment that the employee can see.</div>
        </div>
        <div class="content-card-body">
            @if($isOwnLeavePlan)
                <div class="alert alert-warning mb-0">You cannot approve or reject your own leave plan.</div>
            @else
                <div class="d-flex gap-2 mb-3">
                    <form method="post" action="{{ route($prefix.'.leave-plans.approve', $leavePlan) }}" data-confirm="Approve this leave plan?">@csrf<button class="btn btn-success">Approve</button></form>
                </div>
                <form method="post" action="{{ route($prefix.'.leave-plans.reject', $leavePlan) }}" data-confirm="Reject this leave plan?">
                    @csrf
                    <label class="form-label" for="rejection_comment">Rejection comment</label>
                    <textarea id="rejection_comment" class="form-control @error('rejection_comment') is-invalid @enderror" name="rejection_comment" rows="3" required>{{ old('rejection_comment') }}</textarea>
                    @error('rejection_comment')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <button class="btn btn-danger mt-3">Reject</button>
                </form>
            @endif
        </div>
    </div>
@endif
@if($leavePlan->status === 'approved')
    @php
        $actor = auth()->user();
        $canVoidLeavePlan = $prefix === 'admin' && $actor->role === 'super_admin';
    @endphp
    <div class="content-card mt-3">
        <div class="content-card-header">
            <h2 class="h5 mb-1">Recall approved leave plan</h2>
            <div class="small text-muted">Send the approved plan back to the employee for correction without cancelling it.</div>
        </div>
        <div class="content-card-body">
            @if($isOwnLeavePlan)
                <div class="alert alert-warning mb-0">You cannot recall your own approved leave plan. Another authorized reviewer must complete this correction.</div>
            @else
                <form method="post" action="{{ route($prefix.'.leave-plans.recall-approved', $leavePlan) }}" data-confirm="Recall this approved leave plan and notify the employee?">
                    @csrf
                    <label class="form-label" for="recall_reason">Recall reason</label>
                    <textarea id="recall_reason" class="form-control @error('recall_reason') is-invalid @enderror" name="recall_reason" rows="3" required placeholder="Explain what the employee needs to correct.">{{ old('recall_reason') }}</textarea>
                    @error('recall_reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <button class="btn btn-warning mt-3">Recall approved leave plan</button>
                </form>
            @endif
        </div>
    </div>
    @if($canVoidLeavePlan)
        <div class="content-card mt-3">
            <div class="content-card-header">
                <h2 class="h5 mb-1">Void approved leave plan</h2>
                <div class="small text-muted">Void only when the approved leave should no longer count as active planned leave.</div>
            </div>
            <div class="content-card-body">
                @if($isOwnLeavePlan)
                    <div class="alert alert-warning mb-0">You cannot void your own leave plan. Another Super Admin must complete this correction.</div>
                @else
                    <form method="post" action="{{ route('admin.leave-plans.void', $leavePlan) }}" data-confirm="Void this approved leave plan? It will remain in audit history but will no longer count as active planned leave.">
                        @csrf
                        <label class="form-label" for="void_reason">Void reason</label>
                        <textarea id="void_reason" class="form-control @error('void_reason') is-invalid @enderror" name="void_reason" rows="3" required placeholder="Explain why this approved leave plan should be voided.">{{ old('void_reason') }}</textarea>
                        @error('void_reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <button class="btn btn-warning mt-3">Void approved leave plan</button>
                    </form>
                @endif
            </div>
        </div>
    @endif
@endif
@if($leavePlan->status === 'cancellation_requested')
    <div class="content-card mt-3">
        <div class="content-card-header">
            <h2 class="h5 mb-1">Cancellation action</h2>
            <div class="small text-muted">Approve cancellation or keep the approved leave plan active.</div>
        </div>
        <div class="content-card-body">
            @if($isOwnLeavePlan)
                <div class="alert alert-warning mb-0">You cannot action cancellation for your own leave plan.</div>
            @else
                <div class="d-flex gap-2 mb-3">
                    <form method="post" action="{{ route($prefix.'.leave-plans.approve-cancellation', $leavePlan) }}" data-confirm="Approve cancellation for this leave plan?">@csrf<button class="btn btn-warning">Approve Cancellation</button></form>
                </div>
                <form method="post" action="{{ route($prefix.'.leave-plans.reject-cancellation', $leavePlan) }}" data-confirm="Reject this cancellation request?">
                    @csrf
                    <label class="form-label" for="cancellation_rejection_comment">Cancellation rejection comment</label>
                    <textarea id="cancellation_rejection_comment" class="form-control @error('cancellation_rejection_comment') is-invalid @enderror" name="cancellation_rejection_comment" rows="3" required>{{ old('cancellation_rejection_comment') }}</textarea>
                    @error('cancellation_rejection_comment')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <button class="btn btn-danger mt-3">Reject Cancellation</button>
                </form>
            @endif
        </div>
    </div>
@endif
@endsection
