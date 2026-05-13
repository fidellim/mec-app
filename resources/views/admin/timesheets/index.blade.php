@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">All Timesheets</h1>
    <a class="btn btn-outline-success" href="{{ route('admin.timesheets.export', request()->query()) }}">Export CSV</a>
</div>
<form class="content-card p-3 mb-3 row g-2">
    <div class="col-md-2"><input class="form-control" name="week_number" placeholder="Week" value="{{ request('week_number') }}"></div>
    <div class="col-md-2"><input class="form-control" name="year" placeholder="Year" value="{{ request('year') }}"></div>
    <div class="col-md-3"><select class="form-select" name="department_id"><option value="">All departments</option>@foreach($departments as $department)<option value="{{ $department->id }}" @selected(request('department_id') == $department->id)>{{ $department->name }}</option>@endforeach</select></div>
    <div class="col-md-3"><select class="form-select" name="employee_id"><option value="">All employees</option>@foreach($employees as $employee)<option value="{{ $employee->id }}" @selected(request('employee_id') == $employee->id)>{{ $employee->name }}</option>@endforeach</select></div>
    <div class="col-md-2"><select class="form-select" name="status"><option value="">All statuses</option>@foreach(['draft','submitted','approved','rejected'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></div>
    <div class="col-12 text-end"><button class="btn btn-primary">Apply Filters</button></div>
</form>
<div class="content-card p-3"><table class="table mb-0"><thead><tr><th>Employee</th><th>Department</th><th>Week</th><th>Status</th><th>Total</th><th></th></tr></thead><tbody>
@forelse($timesheets as $timesheet)
    <tr><td>{{ $timesheet->user->name }}</td><td>{{ $timesheet->department->name }}</td><td>{{ $timesheet->period->week_number }} / {{ $timesheet->period->year }}</td><td>@include('partials.status', ['status' => $timesheet->status])</td><td>{{ $timesheet->total_hours }}</td><td class="text-end"><a class="btn btn-sm btn-primary" href="{{ route('admin.timesheets.show', $timesheet) }}">View</a></td></tr>
@empty
    <tr><td colspan="6" class="text-center text-muted py-4">No records found.</td></tr>
@endforelse
</tbody></table></div>
<div class="mt-3">{{ $timesheets->links() }}</div>
@endsection
