@extends('layouts.app')

@section('content')
@php
    $hasTimesheetFilters = collect(request()->only(['status', 'employee_id', 'week_number', 'year', 'department_id']))
        ->contains(fn ($value) => filled($value));
@endphp
<div class="section-header">
    <div>
        <h1 class="h3 page-heading mb-1">Department Timesheets</h1>
        <div class="text-muted">Review and action employee submissions from your department.</div>
    </div>
</div>
<form class="filter-card mb-3" method="get">
    <div class="row g-3 align-items-end">
        <div class="col-md-6 col-xl-3">
            <label class="form-label">Department</label>
            <select class="form-select" name="department_id">
                <option value="">All managed departments</option>
                @foreach($departments as $department)
                    <option value="{{ $department->id }}" @selected((int) $selectedDepartmentId === (int) $department->id)>{{ $department->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6 col-xl-3">
            <label class="form-label">Status</label>
            <select class="form-select" name="status">
                <option value="">All statuses</option>
                @foreach(['submitted','approved','rejected','draft'] as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6 col-xl-3">
            <label class="form-label">Employee</label>
            <select class="form-select" name="employee_id">
                <option value="">All employees</option>
                @foreach($employees as $employee)
                    <option value="{{ $employee->id }}" @selected((int) request('employee_id') === (int) $employee->id)>{{ $employee->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4 col-xl-2">
            <label class="form-label">Week</label>
            <select class="form-select" name="week_number">
                <option value="">All weeks</option>
                @foreach($periods->pluck('week_number')->unique() as $weekNumber)
                    <option value="{{ $weekNumber }}" @selected((string) request('week_number') === (string) $weekNumber)>Week {{ $weekNumber }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4 col-xl-2">
            <label class="form-label">Year</label>
            <select class="form-select" name="year">
                <option value="">All years</option>
                @foreach($periods->pluck('year')->unique() as $year)
                    <option value="{{ $year }}" @selected((string) request('year') === (string) $year)>{{ $year }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4 col-xl-2 d-flex gap-2">
            <button class="btn btn-primary flex-fill">Filter</button>
            @if($hasTimesheetFilters)
                <a class="btn btn-outline-secondary" href="{{ route('hod.timesheets.index') }}">Reset</a>
            @endif
        </div>
    </div>
</form>
<div class="content-card overflow-hidden"><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Employee</th><th>Department</th><th>Week</th><th>Status</th><th>Total</th><th></th></tr></thead><tbody>
@forelse($timesheets as $timesheet)
    <tr><td class="fw-semibold">{{ $timesheet->user->name }}</td><td>{{ $timesheet->department?->name ?: '-' }}</td><td>{{ $timesheet->period->week_number }} / {{ $timesheet->period->year }}</td><td>@include('partials.status', ['status' => $timesheet->status])</td><td><span class="fw-semibold">{{ $timesheet->total_hours }}</span></td><td class="text-end"><a class="btn btn-sm btn-primary" href="{{ route('hod.timesheets.show', $timesheet) }}">Review</a></td></tr>
@empty
    <tr><td colspan="6" class="empty-state">No records found.</td></tr>
@endforelse
</tbody></table></div></div>
<div class="mt-3">{{ $timesheets->links() }}</div>
@endsection
