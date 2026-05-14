@extends('layouts.app')

@section('content')
<div class="section-header">
    <div>
        <h1 class="h3 page-heading mb-1">My Timesheets</h1>
        <div class="text-muted">View your weekly submission history and continue drafts.</div>
    </div>
    <a class="btn btn-primary" href="{{ route('employee.timesheets.create') }}">Create Weekly Timesheet</a>
</div>
@include('employee.timesheets._table')
<div class="mt-3">{{ method_exists($timesheets, 'links') ? $timesheets->links() : '' }}</div>
@endsection
