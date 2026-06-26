@extends('layouts.app')

@section('content')
<div class="section-header">
    <div>
        <h1 class="h3 page-heading mb-1">Leave Plan</h1>
        <div class="text-muted">{{ $leavePlan->start_date->toFormattedDateString() }} to {{ $leavePlan->end_date->toFormattedDateString() }}</div>
    </div>
    <div class="action-group">
        @include('partials.status', ['status' => $leavePlan->status])
        @if($leavePlan->editableBy(auth()->user()))
            <a class="btn btn-primary btn-sm" href="{{ route('employee.leave-plans.edit', $leavePlan) }}">Edit</a>
        @endif
    </div>
</div>
@include('shared.leave_plan_detail', ['leavePlan' => $leavePlan])
@if($leavePlan->status === 'rejected')
    <div class="alert alert-warning mt-3"><strong>Rejection comment:</strong> {{ $leavePlan->rejection_comment }}</div>
@endif
@if($leavePlan->status === 'recalled')
    <div class="alert alert-warning mt-3">
        <strong>Approved leave plan recalled:</strong> Please edit and resubmit this leave plan.
        @if($leavePlan->recall_reason)
            <div class="mt-2">{{ $leavePlan->recall_reason }}</div>
        @endif
    </div>
@endif
@if($leavePlan->status === 'voided')
    <div class="alert alert-warning mt-3">
        <strong>Voided leave plan:</strong> This record is kept for audit history and no longer counts as active planned leave.
        @if($leavePlan->void_reason)
            <div class="mt-2">{{ $leavePlan->void_reason }}</div>
        @endif
    </div>
@endif
@if($leavePlan->status === 'approved')
    <div class="content-card mt-3">
        <div class="content-card-header">
            <h2 class="h5 mb-1">Request cancellation</h2>
            <div class="small text-muted">Your HOD must approve cancellation of an approved leave plan.</div>
        </div>
        <div class="content-card-body">
            <form method="post" action="{{ route('employee.leave-plans.cancel-request', $leavePlan) }}" data-confirm="Request cancellation for this approved leave plan?">
                @csrf
                <label class="form-label" for="cancellation_reason">Cancellation reason</label>
                <textarea id="cancellation_reason" class="form-control @error('cancellation_reason') is-invalid @enderror" name="cancellation_reason" rows="3" required>{{ old('cancellation_reason') }}</textarea>
                @error('cancellation_reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <button class="btn btn-warning mt-3">Request Cancellation</button>
            </form>
        </div>
    </div>
@endif
@if($leavePlan->status === 'cancellation_requested')
    <div class="alert alert-warning mt-3">Cancellation is waiting for HOD approval.</div>
@endif
@if($leavePlan->cancellation_rejection_comment)
    <div class="alert alert-warning mt-3"><strong>Cancellation rejection comment:</strong> {{ $leavePlan->cancellation_rejection_comment }}</div>
@endif
@if($leavePlan->status === 'draft')
    <form method="post" action="{{ route('employee.leave-plans.destroy', $leavePlan) }}" class="mt-3 text-end" data-confirm="Delete this draft leave plan?">
        @csrf @method('delete')
        <button class="btn btn-outline-danger">Delete Draft</button>
    </form>
@endif
@endsection
