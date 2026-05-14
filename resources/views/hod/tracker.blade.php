@extends('layouts.app')

@section('content')
<div class="section-header">
    <div>
        <h1 class="h3 page-heading mb-1">Department Submission Tracker</h1>
        <div class="text-muted">See who has submitted for the current weekly period.</div>
    </div>
</div>
<div class="content-card overflow-hidden">
    <div class="table-responsive">
    <table class="table table-hover mb-0"><thead><tr><th>Employee</th><th>Current week status</th></tr></thead><tbody>
    @foreach($employees as $employee)
        @php($timesheet = $employee->timesheets->first())
        <tr><td class="fw-semibold">{{ $employee->name }}</td><td>@if($timesheet) @include('partials.status', ['status' => $timesheet->status]) @else <span class="badge text-bg-warning">Not submitted</span> @endif</td></tr>
    @endforeach
    </tbody></table>
    </div>
</div>
@endsection
