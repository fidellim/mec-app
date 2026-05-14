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
        @if($timesheet->status === 'submitted')
            <form method="post" action="{{ route('employee.timesheets.recall', $timesheet) }}" data-confirm="Recall this submitted timesheet so you can edit it?">
                @csrf
                <button class="btn btn-warning btn-sm">Recall Submission</button>
            </form>
        @endif
    </div>
</div>
@if($timesheet->status === 'rejected')
    <div class="alert alert-warning"><strong>Rejection comment:</strong> {{ $timesheet->rejection_comment }}</div>
@endif
@include('shared.timesheet_detail', ['timesheet' => $timesheet])
@if($timesheet->status === 'draft')
    <form method="post" action="{{ route('employee.timesheets.destroy', $timesheet) }}" class="mt-3 text-end" data-confirm="Delete this draft?">
        @csrf @method('delete')
        <button class="btn btn-outline-danger">Delete Draft</button>
    </form>
@endif
@endsection
