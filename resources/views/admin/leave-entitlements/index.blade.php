@extends('layouts.app')

@section('content')
@php
    $hasFilters = request()->filled('department_id') || request()->filled('employee_id') || request()->filled('year');
@endphp

<div class="section-header">
    <div>
        <h1 class="h3 page-heading mb-1">Leave Entitlements</h1>
        <div class="text-muted">Review yearly leave balances for active employees and Heads of Department.</div>
    </div>
    <a class="btn btn-outline-secondary" href="{{ route('admin.leave-plans.index') }}">Leave Plans</a>
</div>

<form class="filter-card mb-3" method="get">
    <div class="row g-3 align-items-end">
        <div class="col-md-4">
            <label class="form-label" for="department_id">Department</label>
            <select class="form-select @error('department_id') is-invalid @enderror" id="department_id" name="department_id">
                <option value="">All departments</option>
                @foreach($departments as $department)
                    <option value="{{ $department->id }}" @selected((int) $selectedDepartmentId === (int) $department->id)>{{ $department->name }}</option>
                @endforeach
            </select>
            @error('department_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label" for="employee_id">Employee</label>
            <select class="form-select @error('employee_id') is-invalid @enderror" id="employee_id" name="employee_id">
                <option value="">All employees</option>
                @foreach($filterEmployees as $employee)
                    <option value="{{ $employee->id }}" @selected($selectedEmployee && (int) $selectedEmployee->id === (int) $employee->id)>{{ $employee->name }}{{ $employee->employee_code ? ' - '.$employee->employee_code : '' }}</option>
                @endforeach
            </select>
            @error('employee_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-2">
            <label class="form-label" for="year">Calendar year</label>
            <input class="form-control @error('year') is-invalid @enderror" id="year" name="year" type="number" min="2000" max="2100" step="1" value="{{ $year }}">
            @error('year')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-2 d-flex gap-2 justify-content-md-end">
            <button class="btn btn-primary flex-fill">Filter</button>
            @if($hasFilters)
                <a class="btn btn-outline-secondary flex-fill" href="{{ route('admin.leave-entitlements.index') }}">Reset</a>
            @endif
        </div>
    </div>
</form>

@include('shared.leave_entitlement_table', [
    'employees' => $employees,
    'emptyMessage' => 'No active employees or Heads of Department match the selected filters.',
])

<div class="mt-3">{{ $employees->links() }}</div>
@endsection
