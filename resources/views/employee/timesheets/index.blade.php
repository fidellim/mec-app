@extends('layouts.app')

@section('content')
<div class="section-header">
    <div>
        <h1 class="h3 page-heading mb-1">My Timesheets</h1>
        <div class="text-muted">View your weekly submission history and continue drafts.</div>
    </div>
    @if(auth()->user()->department_id)
        <a class="btn btn-primary" href="{{ route('employee.timesheets.create') }}">Create Weekly Timesheet</a>
    @else
        <button class="btn btn-outline-secondary" type="button" disabled>Department Required</button>
    @endif
</div>
@unless(auth()->user()->department_id)
    <div class="alert alert-warning">
        You need to be assigned to a department before creating or submitting a timesheet. Please contact Super Admin.
    </div>
@endunless
@include('employee.timesheets._table')
@if(method_exists($timesheets, 'links'))
    @include('shared.pagination-footer', ['paginator' => $timesheets, 'label' => 'timesheet'])
@endif
@endsection
