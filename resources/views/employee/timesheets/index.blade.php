@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">My Timesheets</h1>
    <a class="btn btn-primary" href="{{ route('employee.timesheets.create') }}">Create Weekly Timesheet</a>
</div>
@include('employee.timesheets._table')
<div class="mt-3">{{ method_exists($timesheets, 'links') ? $timesheets->links() : '' }}</div>
@endsection
