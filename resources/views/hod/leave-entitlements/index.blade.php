@extends('layouts.app')

@section('content')
@php($hasFilters = request()->filled('department_id') || request()->filled('employee') || request()->filled('year'))

<div class="section-header">
    <div>
        <h1 class="h3 page-heading mb-1">Annual Leave Entitlements</h1>
        <div class="text-muted">Review annual leave availability for the people you manage.</div>
    </div>
    <a class="btn btn-outline-secondary" href="{{ route('hod.leave-plans.index') }}">Department Leave Plans</a>
</div>

<form class="filter-card mb-3" method="get">
    <div class="row g-3 align-items-end">
        <div class="col-lg-3 col-md-6">
            <label class="form-label" for="department_id">Department</label>
            <select class="form-select @error('department_id') is-invalid @enderror" id="department_id" name="department_id">
                <option value="">All managed departments</option>
                @foreach($departments as $department)
                    <option value="{{ $department->id }}" @selected((int) $selectedDepartmentId === (int) $department->id)>{{ $department->name }}</option>
                @endforeach
            </select>
            @error('department_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-lg-4 col-md-6">
            <label class="form-label" for="employee">Employee</label>
            <input class="form-control @error('employee') is-invalid @enderror" id="employee" name="employee" type="search" maxlength="100" value="{{ $employeeSearch }}" placeholder="Search by name or employee number">
            @error('employee')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-lg-2 col-md-4">
            <label class="form-label" for="year">Calendar year</label>
            <input class="form-control @error('year') is-invalid @enderror" id="year" name="year" type="number" min="2000" max="2100" step="1" value="{{ $year }}">
            @error('year')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-lg-3 col-md-8 d-flex gap-2 justify-content-md-end">
            <button class="btn btn-primary flex-fill">Filter</button>
            @if($hasFilters)
                <a class="btn btn-outline-secondary flex-fill" href="{{ route('hod.leave-entitlements.index') }}">Reset</a>
            @endif
        </div>
    </div>
</form>

<div class="content-card overflow-hidden">
    <div class="content-card-header">
        <h2 class="h5 mb-1">Annual leave balances for {{ $year }}</h2>
        <div class="small text-muted">Used or reserved includes submitted, approved, and cancellation-requested annual leave.</div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Employee</th><th>Department</th><th>Allowance</th><th>Used or reserved</th><th>Remaining</th><th>Basis</th></tr></thead>
            <tbody>
                @forelse($employees as $employee)
                    @php($balance = $employee->annualLeaveBalance)
                    <tr>
                        <td><div class="fw-semibold">{{ $employee->name }}</div><div class="small text-muted">{{ $employee->employee_code ?: $employee->email }}</div></td>
                        <td>{{ $employee->department?->name ?: '-' }}</td>
                        @if($balance)
                            <td>
                                <div>{{ $balance['formatted']['allowance'] }} days</div>
                                @if(($balance['carry_over'] ?? 0) > 0)<div class="small text-muted">Includes {{ $balance['formatted']['carry_over'] }} carry-over days</div>@endif
                            </td>
                            <td>{{ $balance['formatted']['used'] }} days</td>
                            <td><span class="fw-semibold">{{ $balance['formatted']['remaining'] }} days</span></td>
                            <td><span class="badge {{ $balance['uses_override'] ? 'text-bg-info' : 'bg-body-secondary border text-body' }}">{{ $balance['source_label'] }}</span></td>
                        @else
                            <td colspan="4" class="text-muted">Annual leave is not available for this employee profile.</td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty-state">No visible people match the selected filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@include('shared.pagination-footer', ['paginator' => $employees, 'label' => 'person'])
@endsection
