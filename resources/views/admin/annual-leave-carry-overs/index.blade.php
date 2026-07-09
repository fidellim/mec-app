@extends('layouts.app')

@section('content')
@php
    $hasFilters = collect(['status', 'source', 'department_id', 'employee_id', 'from_year', 'to_year'])->contains(fn ($key) => request()->filled($key));
    $sourceLabels = $sources;
    $statusClasses = [
        'pending' => 'text-bg-warning',
        'approved' => 'text-bg-success',
        'rejected' => 'text-bg-secondary',
        'voided' => 'text-bg-dark',
    ];
@endphp

<style>
    .carry-over-actions {
        min-width: 15rem;
    }

    .carry-over-help-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: .75rem;
    }

    .carry-over-help-item {
        border: 1px solid var(--app-border);
        border-radius: .5rem;
        background: color-mix(in srgb, var(--app-card-bg) 92%, var(--app-muted-bg));
        padding: .875rem;
    }

    .carry-over-help-label {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1.75rem;
        height: 1.75rem;
        border-radius: 999px;
        background: var(--app-muted-bg);
        border: 1px solid var(--app-border);
        font-weight: 700;
        font-size: .8125rem;
        margin-bottom: .5rem;
    }

    .carry-over-tool-stack {
        display: grid;
        gap: 1rem;
    }

    .carry-over-period-help {
        display: grid;
        grid-template-columns: 1fr auto 1fr;
        align-items: center;
        gap: .75rem;
        border: 1px solid var(--app-border);
        border-radius: .5rem;
        padding: .75rem;
        background: var(--app-muted-bg);
    }

    .carry-over-period-help strong {
        display: block;
    }

    .carry-over-period-arrow {
        color: var(--bs-secondary-color);
        font-weight: 700;
    }

    .carry-over-actions .approved-days-input {
        max-width: 6rem;
    }

    .carry-over-actions .void-reason-input {
        min-width: 18rem;
        min-height: 4.5rem;
        resize: vertical;
    }

    @media (max-width: 767.98px) {
        .carry-over-help-grid,
        .carry-over-period-help {
            grid-template-columns: 1fr;
        }

        .carry-over-period-arrow {
            display: none;
        }

        .annual-carry-over-table thead {
            display: none;
        }

        .annual-carry-over-table,
        .annual-carry-over-table tbody,
        .annual-carry-over-table tr,
        .annual-carry-over-table td {
            display: block;
            width: 100%;
        }

        .annual-carry-over-table tr {
            padding: .875rem 1rem;
            border-bottom: 1px solid var(--bs-border-color);
        }

        .annual-carry-over-table td {
            display: grid;
            grid-template-columns: minmax(7rem, 38%) 1fr;
            gap: .75rem;
            padding: .35rem 0;
            border: 0;
            text-align: start !important;
        }

        .annual-carry-over-table td::before {
            content: attr(data-label);
            font-size: .8125rem;
            font-weight: 600;
            color: var(--bs-secondary-color);
        }

        .annual-carry-over-table .carry-over-actions {
            min-width: 0;
        }

        .annual-carry-over-table .carry-over-actions,
        .annual-carry-over-table .carry-over-actions form,
        .annual-carry-over-table .carry-over-actions .btn,
        .annual-carry-over-table .carry-over-actions .form-control-sm,
        .annual-carry-over-table .carry-over-actions .void-reason-input {
            width: 100%;
            max-width: none;
        }
    }
</style>

<div class="section-header">
    <div>
        <h1 class="h3 page-heading mb-1">Annual Leave Carry-Overs</h1>
        <div class="text-muted">Move approved unused annual leave into the year where employees can use it.</div>
    </div>
    <a class="btn btn-outline-secondary" href="{{ route('admin.leave-entitlements.index') }}">Leave Entitlements</a>
</div>

<div class="content-card mb-3">
    <div class="content-card-header">
        <h2 class="h5 mb-1">Choose the right action</h2>
        <div class="small text-muted">Only approved carry-over changes an employee's annual leave balance.</div>
    </div>
    <div class="content-card-body">
        <div class="carry-over-help-grid">
            <div class="carry-over-help-item">
                <div class="carry-over-help-label">1</div>
                <div class="fw-semibold">Enter an existing approved balance</div>
                <div class="small text-muted">Use this when HR already knows the carry-over days for one employee.</div>
            </div>
            <div class="carry-over-help-item">
                <div class="carry-over-help-label">2</div>
                <div class="fw-semibold">Import many approved balances</div>
                <div class="small text-muted">Use a CSV when HR has a list of opening carry-over balances.</div>
            </div>
            <div class="carry-over-help-item">
                <div class="carry-over-help-label">3</div>
                <div class="fw-semibold">Generate suggestions for review</div>
                <div class="small text-muted">Create pending rows from prior-year remaining leave, then approve or reject them.</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-xl-7">
        <form class="content-card h-100" method="post" action="{{ route('admin.annual-leave-carry-overs.store') }}">
            @csrf
            <div class="content-card-header">
                <h2 class="h5 mb-1">Enter approved carry-over</h2>
                <div class="small text-muted">Adds days to the employee's annual leave balance immediately.</div>
            </div>
            <div class="content-card-body">
                <div class="carry-over-period-help mb-3">
                    <div>
                        <strong>Unused leave from year</strong>
                        <span class="small text-muted">Where the days came from.</span>
                    </div>
                    <div class="carry-over-period-arrow">to</div>
                    <div>
                        <strong>Apply to year</strong>
                        <span class="small text-muted">Where the days become usable.</span>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="user_id">Employee</label>
                        <select class="form-select @error('user_id') is-invalid @enderror" id="user_id" name="user_id" required>
                            <option value="">Select employee</option>
                            @foreach($employees as $employee)
                                <option value="{{ $employee->id }}" @selected((int) old('user_id') === (int) $employee->id)>{{ $employee->name }}{{ $employee->employee_code ? ' - '.$employee->employee_code : '' }}</option>
                            @endforeach
                        </select>
                        @error('user_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="from_year">Unused leave from year</label>
                        <input class="form-control @error('from_year') is-invalid @enderror" id="from_year" name="from_year" type="number" min="2000" max="2100" step="1" value="{{ old('from_year', now()->subYear()->year) }}" required>
                        <div class="form-text">Example: 2026.</div>
                        @error('from_year')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="to_year">Apply to year</label>
                        <input class="form-control @error('to_year') is-invalid @enderror" id="to_year" name="to_year" type="number" min="2000" max="2100" step="1" value="{{ old('to_year', now()->year) }}" required>
                        <div class="form-text">Example: 2027.</div>
                        @error('to_year')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="approved_days">Approved days</label>
                        <input class="form-control @error('approved_days') is-invalid @enderror" id="approved_days" name="approved_days" type="number" min="0.5" step="0.5" value="{{ old('approved_days') }}" required>
                        @error('approved_days')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="source">Reason for entry</label>
                        <select class="form-select @error('source') is-invalid @enderror" id="source" name="source" required>
                            <option value="manual_opening_balance" @selected(old('source') === 'manual_opening_balance')>Manual opening balance</option>
                            <option value="manual_adjustment" @selected(old('source') === 'manual_adjustment')>Manual adjustment</option>
                        </select>
                        <div class="form-text">Opening balance is for existing HR records. Adjustment is for corrections or special approvals.</div>
                        @error('source')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-5">
                        <label class="form-label" for="notes">Notes</label>
                        <input class="form-control @error('notes') is-invalid @enderror" id="notes" name="notes" value="{{ old('notes') }}" maxlength="1000" placeholder="HR file reference or approval note">
                        @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 d-flex justify-content-end">
                        <button class="btn btn-primary">Save approved carry-over</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
    <div class="col-xl-5">
        <div class="carry-over-tool-stack">
            <div class="content-card">
                <div class="content-card-header">
                    <h2 class="h5 mb-1">Import approved balances</h2>
                    <div class="small text-muted">Use this for HR opening-balance files. Imported rows are approved immediately.</div>
                </div>
                <form class="content-card-body" method="post" action="{{ route('admin.annual-leave-carry-overs.import') }}" enctype="multipart/form-data">
                    @csrf
                    <label class="form-label" for="carry_over_csv">Carry-over CSV</label>
                    <input class="form-control @error('carry_over_csv') is-invalid @enderror" id="carry_over_csv" name="carry_over_csv" type="file" accept=".csv,text/csv" required>
                    <div class="form-text">Header: employee_code,from_year,to_year,approved_days,notes</div>
                    @error('carry_over_csv')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    <button class="btn btn-outline-primary mt-3">Import approved rows</button>
                </form>
            </div>
            <div class="content-card">
                <div class="content-card-header">
                    <h2 class="h5 mb-1">Generate pending suggestions</h2>
                    <div class="small text-muted">Creates review rows only. Balances change after approval.</div>
                </div>
                <form class="content-card-body" method="post" action="{{ route('admin.annual-leave-carry-overs.generate') }}">
                    @csrf
                    <label class="form-label" for="generate_from_year">Calculate remaining leave from year</label>
                    <div class="input-group">
                        <input class="form-control @error('generate_from_year') is-invalid @enderror" id="generate_from_year" name="from_year" type="number" min="2000" max="2100" step="1" value="{{ old('generate_from_year', now()->subYear()->year) }}" required>
                        <button class="btn btn-outline-secondary">Generate</button>
                    </div>
                    <div class="form-text">Example: enter 2026 to create pending suggestions for 2027.</div>
                </form>
            </div>
        </div>
    </div>
</div>

<form class="filter-card mb-3" method="get">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h2 class="h5 mb-1">Carry-over records</h2>
            <div class="small text-muted">
                Review pending rows, void incorrect approved rows, or filter the history.
                @if($carryOvers->total() > 0)
                    Showing {{ $carryOvers->firstItem() }}-{{ $carryOvers->lastItem() }} of {{ $carryOvers->total() }} records.
                @endif
            </div>
        </div>
        @if($hasFilters)
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.annual-leave-carry-overs.index') }}">Reset filters</a>
        @endif
    </div>
    <div class="row g-3 align-items-end">
        <div class="col-md-2">
            <label class="form-label" for="status">Status</label>
            <select class="form-select" id="status" name="status">
                <option value="">All statuses</option>
                @foreach($statuses as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label" for="filter_source">Source</label>
            <select class="form-select" id="filter_source" name="source">
                <option value="">All sources</option>
                @foreach($sources as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['source'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label" for="department_id">Department</label>
            <select class="form-select" id="department_id" name="department_id">
                <option value="">All departments</option>
                @foreach($departments as $department)
                    <option value="{{ $department->id }}" @selected((int) ($filters['department_id'] ?? 0) === (int) $department->id)>{{ $department->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="employee_id">Employee</label>
            <select class="form-select" id="employee_id" name="employee_id">
                <option value="">All employees</option>
                @foreach($employees as $employee)
                    <option value="{{ $employee->id }}" @selected((int) ($filters['employee_id'] ?? 0) === (int) $employee->id)>{{ $employee->name }}{{ $employee->employee_code ? ' - '.$employee->employee_code : '' }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-1">
            <label class="form-label" for="filter_from_year">From</label>
            <input class="form-control" id="filter_from_year" name="from_year" type="number" min="2000" max="2100" value="{{ $filters['from_year'] ?? '' }}">
        </div>
        <div class="col-md-1">
            <label class="form-label" for="filter_to_year">Apply to</label>
            <input class="form-control" id="filter_to_year" name="to_year" type="number" min="2000" max="2100" value="{{ $filters['to_year'] ?? '' }}">
        </div>
        <div class="col-md-1 d-flex gap-2 justify-content-md-end">
            <button class="btn btn-primary w-100">Filter</button>
        </div>
    </div>
</form>

<div class="content-card overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 annual-carry-over-table">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Period</th>
                    <th>Days</th>
                    <th>Status</th>
                    <th>Reason</th>
                    <th>Notes</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($carryOvers as $carryOver)
                    <tr>
                        <td data-label="Employee">
                            <div class="fw-semibold">{{ $carryOver->user?->name }}</div>
                            <div class="small text-muted">{{ $carryOver->user?->employee_code ?: $carryOver->user?->email }}</div>
                            <div class="small text-muted">{{ $carryOver->user?->department?->name ?: '-' }}</div>
                        </td>
                        <td data-label="Period">
                            <div><span class="text-muted">From</span> <span class="fw-semibold">{{ $carryOver->from_year }}</span></div>
                            <div><span class="text-muted">Apply to</span> <span class="fw-semibold">{{ $carryOver->to_year }}</span></div>
                            <div class="small text-muted">{{ $carryOver->attendance_code }}</div>
                        </td>
                        <td data-label="Days">
                            <div><span class="text-muted">Suggested</span> {{ rtrim(rtrim(number_format((float) $carryOver->suggested_days, 2), '0'), '.') }}</div>
                            <div class="fw-semibold"><span class="text-muted fw-normal">Approved</span> {{ $carryOver->approved_days !== null ? rtrim(rtrim(number_format((float) $carryOver->approved_days, 2), '0'), '.') : '-' }}</div>
                        </td>
                        <td data-label="Status"><span class="badge {{ $statusClasses[$carryOver->status] ?? 'bg-body-secondary border text-body' }}">{{ $statuses[$carryOver->status] ?? ucfirst($carryOver->status) }}</span></td>
                        <td data-label="Reason">{{ $sourceLabels[$carryOver->source] ?? $carryOver->source }}</td>
                        <td class="small text-muted" data-label="Notes">
                            <div>{{ $carryOver->notes ?: '-' }}</div>
                            @if($carryOver->status === 'voided' && $carryOver->void_reason)
                                <div class="mt-1"><span class="fw-semibold">Void reason:</span> {{ $carryOver->void_reason }}</div>
                            @endif
                        </td>
                        <td class="text-end carry-over-actions" data-label="Actions">
                            @if($carryOver->status === 'pending')
                                <form class="d-flex flex-column flex-sm-row gap-2 align-items-stretch align-items-sm-center mb-2" method="post" action="{{ route('admin.annual-leave-carry-overs.approve', $carryOver) }}">
                                    @csrf
                                    <input class="form-control form-control-sm approved-days-input" name="approved_days" type="number" min="0.5" step="0.5" value="{{ old('approved_days', $carryOver->suggested_days) }}" aria-label="Approved days">
                                    <button class="btn btn-sm btn-success">Approve</button>
                                </form>
                                <form class="d-inline" method="post" action="{{ route('admin.annual-leave-carry-overs.reject', $carryOver) }}">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-secondary">Reject</button>
                                </form>
                            @elseif($carryOver->status === 'approved')
                                <form class="d-flex flex-column gap-2" method="post" action="{{ route('admin.annual-leave-carry-overs.void', $carryOver) }}">
                                    @csrf
                                    <textarea class="form-control form-control-sm void-reason-input" name="void_reason" maxlength="1000" placeholder="Required void reason" aria-label="Void reason" rows="2" required></textarea>
                                    <button class="btn btn-sm btn-outline-danger">Void</button>
                                </form>
                            @else
                                <span class="text-muted small">No pending action</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="empty-state">No annual leave carry-over records match this view.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@include('shared.pagination-footer', ['paginator' => $carryOvers, 'label' => 'carry-over'])
@endsection
