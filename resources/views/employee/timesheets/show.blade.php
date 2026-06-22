@extends('layouts.app')

@section('content')
<div class="section-header">
    <div>
        <h1 class="h3 page-heading mb-1">Timesheet Week {{ $timesheet->period->week_number }}, {{ $timesheet->period->year }}</h1>
        <div class="text-muted">{{ $timesheet->period->start_date->toDateString() }} to {{ $timesheet->period->end_date->toDateString() }}</div>
    </div>
    <div class="action-group">
        @include('partials.status', ['status' => $timesheet->status])
        @if($timesheet->editableBy(auth()->user()))<a class="btn btn-primary btn-sm" href="{{ route('employee.timesheets.edit', $timesheet) }}">Edit</a>@endif
    </div>
</div>
@if($timesheet->status === 'rejected')
    <div class="alert alert-warning"><strong>Rejection comment:</strong> {{ $timesheet->rejection_comment }}</div>
@endif
@if($timesheet->status === 'withdrawn')
    <div class="alert alert-warning">This submitted timesheet was withdrawn. You can edit and resubmit it when ready.</div>
@endif
@if($timesheet->status === 'recalled')
    <div class="alert alert-warning">This approved timesheet was recalled for correction. Please review the history comment, edit the entries, and resubmit it.</div>
@endif
@if($timesheet->status === 'submitted')
    <div class="content-card mb-3">
        <div class="content-card-header">
            <h2 class="h5 mb-1">Withdraw submission</h2>
            <div class="small text-muted">Use this only before approval if you need to correct your submitted entries.</div>
        </div>
        <div class="content-card-body">
            <form method="post" action="{{ route('employee.timesheets.recall', $timesheet) }}" data-confirm="Withdraw this submitted timesheet so you can edit it?">
                @csrf
                <label class="form-label" for="withdrawal_comment">Comment <span class="text-muted fw-normal">(optional)</span></label>
                <textarea id="withdrawal_comment" class="form-control @error('withdrawal_comment') is-invalid @enderror" name="withdrawal_comment" rows="2" placeholder="Add a short note for the history log.">{{ old('withdrawal_comment') }}</textarea>
                @error('withdrawal_comment')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <button class="btn btn-warning mt-3">Withdraw Submission</button>
            </form>
        </div>
    </div>
@endif
@include('shared.timesheet_detail', ['timesheet' => $timesheet])
@if($timesheet->status === 'draft')
    <form method="post" action="{{ route('employee.timesheets.destroy', $timesheet) }}" class="mt-3 text-end" data-confirm="Delete this draft?">
        @csrf @method('delete')
        <button class="btn btn-outline-danger">Delete Draft</button>
    </form>
@endif
@endsection
