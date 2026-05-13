@extends('layouts.app')

@section('content')
<h1 class="h3 mb-3">Department Submission Tracker</h1>
<div class="content-card p-3">
    <table class="table mb-0"><thead><tr><th>Employee</th><th>Current week status</th></tr></thead><tbody>
    @foreach($employees as $employee)
        @php($timesheet = $employee->timesheets->first())
        <tr><td>{{ $employee->name }}</td><td>@if($timesheet) @include('partials.status', ['status' => $timesheet->status]) @else <span class="badge text-bg-warning">Not submitted</span> @endif</td></tr>
    @endforeach
    </tbody></table>
</div>
@endsection
