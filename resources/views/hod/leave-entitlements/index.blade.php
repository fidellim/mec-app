@extends('layouts.app')

@section('content')
@php
    $hasFilters = request()->filled('department_id') || request()->filled('year');
@endphp

<div class="section-header">
    <div>
        <h1 class="h3 page-heading mb-1">Department Leave Entitlements</h1>
        <div class="text-muted">Review eligible leave balances for employees in your managed departments.</div>
    </div>
    <a class="btn btn-outline-secondary" href="{{ route('hod.leave-plans.index') }}">Leave Plans</a>
</div>

<form class="filter-card mb-3" method="get">
    <div class="row g-3 align-items-end">
        <div class="col-md-5">
            <label class="form-label" for="department_id">Department</label>
            <select class="form-select @error('department_id') is-invalid @enderror" id="department_id" name="department_id">
                <option value="">All managed departments</option>
                @foreach($departments as $department)
                    <option value="{{ $department->id }}" @selected((int) $selectedDepartmentId === (int) $department->id)>{{ $department->name }}</option>
                @endforeach
            </select>
            @error('department_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-3">
            <label class="form-label" for="year">Calendar year</label>
            <input class="form-control @error('year') is-invalid @enderror" id="year" name="year" type="number" min="2000" max="2100" step="1" value="{{ $year }}">
            @error('year')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4 d-flex gap-2 justify-content-md-end">
            <button class="btn btn-primary flex-fill flex-md-grow-0">Filter</button>
            @if($hasFilters)
                <a class="btn btn-outline-secondary flex-fill flex-md-grow-0" href="{{ route('hod.leave-entitlements.index') }}">Reset</a>
            @endif
        </div>
    </div>
</form>

<div class="content-card overflow-hidden">
    <div class="content-card-header">
        <h2 class="h5 mb-1">Employee balances</h2>
        <div class="small text-muted">Used or reserved includes submitted, approved, and cancellation-requested leave plans.</div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Department</th>
                    <th>Leave type</th>
                    <th>Allowance</th>
                    <th>Used or reserved</th>
                    <th>Remaining</th>
                    <th>Basis</th>
                </tr>
            </thead>
            <tbody>
                @forelse($employees as $employee)
                    @forelse($employee->leaveBalances as $balance)
                        <tr>
                            @if($loop->first)
                                <td rowspan="{{ count($employee->leaveBalances) }}">
                                    <div class="fw-semibold">{{ $employee->name }}</div>
                                    <div class="small text-muted">{{ $employee->employee_code ?: $employee->email }}</div>
                                </td>
                                <td rowspan="{{ count($employee->leaveBalances) }}">{{ $employee->department?->name ?: '-' }}</td>
                            @endif
                            <td>
                                <div class="fw-semibold">{{ $balance['label'] }}</div>
                                <div class="small text-muted">{{ $balance['attendance_code'] }} for {{ $balance['year'] }} - {{ $balance['region_label'] }}</div>
                            </td>
                            <td>
                                <div>{{ $balance['formatted']['allowance'] }} days</div>
                                <div class="small text-muted">{{ $balance['allowance_label'] ?? 'Allowance' }}</div>
                            </td>
                            <td>{{ $balance['formatted']['used'] }} days</td>
                            <td>
                                <div>{{ $balance['formatted']['remaining'] }} days</div>
                                <div class="small text-muted">{{ $balance['remaining_label'] ?? 'Remaining' }}</div>
                            </td>
                            <td>
                                <span class="badge {{ $balance['uses_override'] ? 'text-bg-info' : 'bg-body-secondary border text-body' }}">
                                    {{ $balance['uses_override'] ? 'User override' : 'Regional default' }}
                                </span>
                                @if(! empty($balance['description']))
                                    <div class="small text-muted mt-1">{{ $balance['description'] }}</div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $employee->name }}</div>
                                <div class="small text-muted">{{ $employee->employee_code ?: $employee->email }}</div>
                            </td>
                            <td>{{ $employee->department?->name ?: '-' }}</td>
                            <td colspan="5" class="text-muted">No eligible leave entitlements for this profile.</td>
                        </tr>
                    @endforelse
                @empty
                    <tr>
                        <td colspan="7" class="empty-state">No visible active employees found for the selected department view.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $employees->links() }}</div>
@endsection
