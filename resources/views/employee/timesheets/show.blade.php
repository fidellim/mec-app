@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h3 mb-1">Timesheet Week {{ $timesheet->period->week_number }}, {{ $timesheet->period->year }}</h1>
        <div class="text-muted">{{ $timesheet->period->start_date->toDateString() }} to {{ $timesheet->period->end_date->toDateString() }}</div>
    </div>
    <div class="d-flex gap-2">
        @include('partials.status', ['status' => $timesheet->status])
        @if($timesheet->editableBy(auth()->user()))<a class="btn btn-primary btn-sm" href="{{ route('employee.timesheets.edit', $timesheet) }}">Edit</a>@endif
    </div>
</div>
@if($timesheet->status === 'rejected')
    <div class="alert alert-warning"><strong>Rejection comment:</strong> {{ $timesheet->rejection_comment }}</div>
@endif
@include('shared.timesheet_detail', ['timesheet' => $timesheet])
@if($timesheet->status === 'draft')
    <form method="post" action="{{ route('employee.timesheets.destroy', $timesheet) }}" class="mt-3" data-confirm="Delete this draft?">
        @csrf @method('delete')
        <button class="btn btn-outline-danger">Delete Draft</button>
    </form>
@endif
@endsection
