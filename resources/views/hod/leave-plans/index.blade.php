@extends('layouts.app')

@section('content')
@php
    $hasLeaveFilters = collect(request()->only(['status', 'employee_id', 'department_id']))->contains(fn ($value) => filled($value));
@endphp
<div class="section-header">
    <div>
        <h1 class="h3 page-heading mb-1">Department Leave Plans</h1>
        <div class="text-muted">Review leave plans from your managed departments.</div>
    </div>
    <a class="btn btn-outline-secondary" href="{{ route('hod.leave-plans.calendar') }}">Calendar</a>
</div>
<form class="filter-card mb-3" method="get">
    <div class="row g-3 align-items-end">
        <div class="col-md-4">
            <label class="form-label">Department</label>
            <select class="form-select" name="department_id">
                <option value="">All managed departments</option>
                @foreach($departments as $department)
                    <option value="{{ $department->id }}" @selected((int) $selectedDepartmentId === (int) $department->id)>{{ $department->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Status</label>
            <select class="form-select" name="status">
                <option value="">All statuses</option>
                @foreach(['submitted','approved','rejected','cancellation_requested','recalled','cancelled','voided','draft'] as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Employee</label>
            <select class="form-select" name="employee_id">
                <option value="">All employees</option>
                @foreach($employees as $employee)
                    <option value="{{ $employee->id }}" @selected((int) request('employee_id') === (int) $employee->id)>{{ $employee->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 d-flex gap-2 justify-content-end">
            <button class="btn btn-primary">Filter</button>
            @if($hasLeaveFilters)
                <a class="btn btn-outline-secondary" href="{{ route('hod.leave-plans.index') }}">Reset</a>
            @endif
        </div>
    </div>
</form>
@include('shared.leave_plan_table', ['leavePlans' => $leavePlans, 'showEmployee' => true, 'showDepartment' => true, 'showRoute' => 'hod.leave-plans.show'])
<div class="mt-3">{{ $leavePlans->links() }}</div>
@endsection
