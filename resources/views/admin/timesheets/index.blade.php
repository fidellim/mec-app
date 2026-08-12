@extends('layouts.app')

@section('content')
@php
    $filterMode = request('filter_mode', 'weekly') === 'monthly' ? 'monthly' : 'weekly';
    $weekFrom = request('week_from', request('week_number'));
    $weekTo = request('week_to', $weekFrom);
    $selectedMonth = request('month', now()->month);
    $selectedEmployeeTypes = collect((array) request('employee_types', []))->filter(fn ($type) => is_string($type) && isset($employeeTypeLabels[$type]))->values()->all();
    $selectedEmployeeTypeLabels = collect($selectedEmployeeTypes)->map(fn ($type) => $employeeTypeLabels[$type])->values();
    $hasVisibleFilters = $filterMode === 'monthly' || $weekFrom || request('year') || request('project_id') || request('department_id') || request('employee_id') || request('role') || $selectedEmployeeTypes || request('status') || request('corrections') || request()->boolean('include_employee_sheets') || request()->boolean('employee_totals_only');
@endphp
<style>
    .summary-preview-info-button {
        width: 1.25rem;
        height: 1.25rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0;
        border: 0;
        color: var(--bs-secondary-color);
        background: transparent;
    }
    .summary-preview-info-button:hover,
    .summary-preview-info-button:focus-visible {
        color: var(--bs-primary);
        background: transparent;
        box-shadow: none;
    }
    .summary-preview-info-icon {
        width: 1.25rem;
        height: 1.25rem;
        display: inline-block;
        background-color: currentColor;
        mask: url("{{ asset('images/status/info-icon.svg') }}") center / contain no-repeat;
        -webkit-mask: url("{{ asset('images/status/info-icon.svg') }}") center / contain no-repeat;
    }
    .report-mode-control {
        border: 1px solid var(--bs-border-color);
        border-radius: var(--bs-border-radius);
        padding: .35rem;
        background: color-mix(in srgb, var(--bs-body-bg) 88%, var(--bs-tertiary-bg));
    }
    .report-mode-control .btn {
        border: 0;
    }
    .report-mode-control .btn:not(.active) {
        color: var(--bs-secondary-color);
        background: transparent;
    }
    .employee-totals-option {
        border: 1px solid color-mix(in srgb, var(--bs-primary) 25%, var(--bs-border-color));
        border-radius: var(--bs-border-radius-lg);
        padding: .85rem 1rem;
        background: color-mix(in srgb, var(--bs-primary) 5%, var(--bs-body-bg));
    }
    .employee-type-toggle {
        width: 100%;
        min-height: calc(1.5em + .75rem + 2px);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        border: var(--bs-border-width) solid var(--bs-border-color);
        border-radius: var(--bs-border-radius);
        color: var(--bs-body-color);
        background: var(--bs-body-bg);
        text-align: left;
    }
    .employee-type-toggle:hover,
    .employee-type-toggle:focus-visible,
    .employee-type-toggle[aria-expanded="true"] {
        border-color: color-mix(in srgb, var(--bs-primary) 55%, var(--bs-border-color));
        color: var(--bs-body-color);
        background: var(--bs-body-bg);
        box-shadow: 0 0 0 .25rem color-mix(in srgb, var(--bs-primary) 22%, transparent);
    }
    .employee-type-menu {
        width: max(100%, 18rem);
        padding: .55rem;
        border-color: var(--bs-border-color);
        background: var(--bs-body-bg);
        box-shadow: var(--bs-box-shadow);
    }
    .employee-type-option {
        margin: 0;
        padding: .55rem .65rem .55rem 2.15rem;
        border-radius: var(--bs-border-radius-sm);
    }
    .employee-type-option:hover {
        background: var(--bs-tertiary-bg);
    }
    .employee-type-option-all {
        margin-bottom: .35rem;
        border-bottom: 1px solid var(--bs-border-color);
        border-radius: var(--bs-border-radius-sm) var(--bs-border-radius-sm) 0 0;
        font-weight: 600;
    }
</style>
<div class="section-header">
    <div>
        <h1 class="h3 page-heading mb-1">All Timesheets</h1>
        <div class="text-muted">Filter, review, and export weekly records or monthly management summaries.</div>
    </div>
    @unless($showingNotSubmitted || request('corrections') === 'open')
        <div class="action-group">
            @if($summaryPreviewState['can_preview'])
                <a class="btn btn-outline-primary" href="{{ route('admin.timesheets.index', array_merge(request()->query(), ['preview' => 'summary'])) }}#summary-report-preview">Summary Report Preview</a>
            @endif
            <a class="btn btn-outline-success" id="timesheetExportButton" href="{{ route('admin.timesheets.export', request()->except('preview')) }}">
                <span class="spinner-border spinner-border-sm me-2 d-none" aria-hidden="true"></span>
                <span data-export-label>Export Excel</span>
            </a>
        </div>
    @endunless
</div>
<form class="filter-card mb-3 row g-2">
    <div class="col-12">
        <label class="form-label small text-muted d-block">Report mode</label>
        <div class="btn-group report-mode-control" role="group" aria-label="Report mode">
            <input class="btn-check" type="radio" name="filter_mode" id="filter_mode_weekly" value="weekly" @checked($filterMode === 'weekly')>
            <label class="btn btn-sm btn-outline-primary @if($filterMode === 'weekly') active @endif" for="filter_mode_weekly">Weekly</label>
            <input class="btn-check" type="radio" name="filter_mode" id="filter_mode_monthly" value="monthly" @checked($filterMode === 'monthly')>
            <label class="btn btn-sm btn-outline-primary @if($filterMode === 'monthly') active @endif" for="filter_mode_monthly">Monthly</label>
        </div>
        @error('filter_mode')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-2" data-weekly-filter>
        <label class="form-label small text-muted" for="week_from">From Week</label>
        <input id="week_from" class="form-control @error('week_from') is-invalid @enderror" name="week_from" placeholder="e.g. 12" value="{{ old('week_from', request('week_from', request('week_number'))) }}">
        @error('week_from')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-2" data-weekly-filter>
        <label class="form-label small text-muted" for="week_to">To Week <span class="fw-normal">(optional)</span></label>
        <input id="week_to" class="form-control @error('week_to') is-invalid @enderror" name="week_to" placeholder="e.g. 15" value="{{ old('week_to', request('week_to')) }}">
        <div class="form-text">Leave blank to view one week.</div>
        @error('week_to')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-2" data-monthly-filter>
        <label class="form-label small text-muted" for="month">Month</label>
        <select id="month" class="form-select @error('month') is-invalid @enderror" name="month">
            @foreach(range(1, 12) as $monthNumber)
                <option value="{{ $monthNumber }}" @selected((int) old('month', $selectedMonth) === $monthNumber)>{{ \Carbon\CarbonImmutable::create(2026, $monthNumber, 1)->format('F') }}</option>
            @endforeach
        </select>
        @error('month')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-2"><label class="form-label small text-muted" for="year">Year</label><input id="year" class="form-control @error('year') is-invalid @enderror" name="year" placeholder="Year" value="{{ request('year') }}">@error('year')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    <div class="col-md-3"><label class="form-label small text-muted" for="project_id">Project</label><select id="project_id" class="form-select" name="project_id"><option value="">All projects</option>@foreach($projects as $project)<option value="{{ $project->id }}" @selected(request('project_id') == $project->id)>{{ $project->project_code }} - {{ $project->project_name }}</option>@endforeach</select></div>
    <div class="col-md-3"><label class="form-label small text-muted" for="department_id">Department</label><select id="department_id" class="form-select" name="department_id"><option value="">All departments</option>@foreach($departments as $department)<option value="{{ $department->id }}" @selected(request('department_id') == $department->id)>{{ $department->name }}</option>@endforeach</select></div>
    <div class="col-md-3"><label class="form-label small text-muted" for="employee_id">User</label><select id="employee_id" class="form-select" name="employee_id"><option value="">All users</option>@foreach($employees as $employee)<option value="{{ $employee->id }}" @selected(request('employee_id') == $employee->id)>{{ $employee->name }}</option>@endforeach</select></div>
    <div class="col-md-2"><label class="form-label small text-muted" for="role">Role</label><select id="role" class="form-select" name="role"><option value="">All roles</option>@foreach($roleLabels as $role => $label)<option value="{{ $role }}" @selected(request('role') === $role)>{{ $label }}</option>@endforeach</select></div>
    <div class="col-md-3">
        <label class="form-label small text-muted" for="employee_type_filter">Employee type</label>
        <div class="dropdown">
            <button class="btn employee-type-toggle" id="employee_type_filter" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                <span data-employee-type-label>{{ $selectedEmployeeTypes ? $selectedEmployeeTypeLabels->join(', ') : 'All employee types' }}</span>
                <span class="badge text-bg-primary rounded-pill @if(count($selectedEmployeeTypes) < 2) d-none @endif" data-employee-type-count>{{ count($selectedEmployeeTypes) }}</span>
            </button>
            <div class="dropdown-menu employee-type-menu" aria-labelledby="employee_type_filter">
                <div class="form-check employee-type-option employee-type-option-all">
                    <input class="form-check-input" type="checkbox" id="employee_type_all" data-employee-type-all @checked(!$selectedEmployeeTypes)>
                    <label class="form-check-label" for="employee_type_all">All employee types</label>
                </div>
                @foreach($employeeTypeLabels as $type => $label)
                    <div class="form-check employee-type-option">
                        <input class="form-check-input" type="checkbox" id="employee_type_{{ $loop->index }}" name="employee_types[]" value="{{ $type }}" data-employee-type-option @checked(in_array($type, $selectedEmployeeTypes, true))>
                        <label class="form-check-label" for="employee_type_{{ $loop->index }}">{{ $label }}</label>
                    </div>
                @endforeach
            </div>
        </div>
        @error('employee_types')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        @error('employee_types.*')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-2"><label class="form-label small text-muted" for="status">Status</label><select id="status" class="form-select" name="status"><option value="">All statuses</option>@foreach(['draft' => 'Draft','submitted' => 'Submitted','approved' => 'Approved','rejected' => 'Rejected','withdrawn' => 'Withdrawn','recalled' => 'Recalled','voided' => 'Voided','not_submitted' => 'Not Submitted'] as $status => $label)<option value="{{ $status }}" @selected(request('status') === $status)>{{ $label }}</option>@endforeach</select></div>
    <div class="col-md-3"><label class="form-label small text-muted" for="corrections">Correction review</label><select id="corrections" class="form-select" name="corrections" data-searchable="false"><option value="">All timesheets</option><option value="open" @selected(request('corrections') === 'open')>Open HOD correction requests</option></select></div>
    <div class="col-md-4 d-flex align-items-end" data-weekly-filter>
        <div class="form-check mb-2">
            <input type="hidden" name="include_employee_sheets" value="0">
            <input class="form-check-input" type="checkbox" id="include_employee_sheets" name="include_employee_sheets" value="1" @checked(request()->boolean('include_employee_sheets'))>
            <label class="form-check-label" for="include_employee_sheets">Include individual employee timesheet sheets</label>
            <div class="form-text">Leave unchecked for a summary export with rate and AED cost columns. Individual sheets are limited to 250 matching timesheets.</div>
        </div>
    </div>
    <div class="col-md-4 d-flex align-items-end" data-monthly-filter>
        <div class="text-muted small mb-2">Monthly reports are summary-only and count only dates inside the selected calendar month.</div>
    </div>
    <div class="col-12">
        <div class="employee-totals-option">
            <div class="form-check mb-0">
                <input type="hidden" name="employee_totals_only" value="0">
                <input class="form-check-input" type="checkbox" id="employee_totals_only" name="employee_totals_only" value="1" @checked(request()->boolean('employee_totals_only'))>
                <label class="form-check-label fw-semibold" for="employee_totals_only">Export employee charging report</label>
                <div class="form-text">Creates one expandable Employee Hours Summary sheet with period totals and project/attendance charging breakdowns for each matching employee.</div>
            </div>
        </div>
    </div>
    <div class="col-12 text-end">
        <div class="d-inline-flex gap-2">
            @if($hasVisibleFilters)
                <a class="btn btn-outline-secondary" href="{{ route('admin.timesheets.index') }}">Clear</a>
            @endif
            <button class="btn btn-primary">Apply Filters</button>
        </div>
    </div>
</form>
@php
    $selectedProject = request('project_id') ? $projects->firstWhere('id', (int) request('project_id')) : null;
    $selectedDepartment = request('department_id') ? $departments->firstWhere('id', (int) request('department_id')) : null;
    $selectedEmployee = request('employee_id') ? $employees->firstWhere('id', (int) request('employee_id')) : null;
    $selectedRole = request('role') ? ($roleLabels[request('role')] ?? request('role')) : null;
@endphp
<div class="content-card p-3 mb-3">
    <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
        <div>
            <div class="d-flex align-items-center gap-2">
                <div class="fw-semibold">Current view</div>
                @unless($showingNotSubmitted)
                    <button class="btn btn-sm rounded-circle summary-preview-info-button" type="button" data-bs-toggle="tooltip" data-bs-placement="top" title="Summary Report Preview is available when you select a Year and 1 to 6 weekly periods. It is not available for Not Submitted status. Use Export Excel for larger ranges." aria-label="Summary Report Preview information">
                        <span class="summary-preview-info-icon" aria-hidden="true"></span>
                    </button>
                @endunless
            </div>
            <div class="text-muted small">
                @if($selectedPeriodRange)
                    @if($filterMode === 'monthly')
                        Showing monthly reporting dates for
                        <span class="fw-semibold text-body">{{ $selectedPeriodRange['label'] }}</span>,
                        from
                    @else
                        Showing configured dates from
                    @endif
                    <span class="fw-semibold text-body">{{ $selectedPeriodRange['start_date']->format('M j, Y') }}</span>
                    to
                    <span class="fw-semibold text-body">{{ $selectedPeriodRange['end_date']->format('M j, Y') }}</span>.
                    @if($selectedPeriodRange['has_missing_weeks'])
                        Some selected weeks do not have configured timesheet periods yet.
                    @endif
                @elseif($showingNotSubmitted)
                    Select a valid week and year to view users who have not submitted.
                @elseif($hasVisibleFilters)
                    Filters are active. Add a valid week and year to see the configured date range.
                @else
                    Showing all timesheets.
                @endif
            </div>
        </div>
        <div class="d-flex flex-wrap align-items-start justify-content-lg-end gap-2">
            <span class="badge filter-summary-badge px-3 py-2">Mode: {{ ucfirst($filterMode) }}</span>
            @if($weekFrom)
                <span class="badge filter-summary-badge px-3 py-2">
                    Week: {{ $weekTo && $weekTo !== $weekFrom ? $weekFrom.' to '.$weekTo : $weekFrom }}
                </span>
            @endif
            @if($filterMode === 'monthly' && request('month'))
                <span class="badge filter-summary-badge px-3 py-2">Month: {{ \Carbon\CarbonImmutable::create(2026, (int) request('month'), 1)->format('F') }}</span>
            @endif
            @if(request('year'))
                <span class="badge filter-summary-badge px-3 py-2">Year: {{ request('year') }}</span>
            @endif
            @if($selectedPeriodRange)
                <span class="badge filter-summary-badge px-3 py-2">
                    Dates: {{ $selectedPeriodRange['start_date']->format('M j, Y') }} to {{ $selectedPeriodRange['end_date']->format('M j, Y') }}
                </span>
            @endif
            @if(request('project_id'))
                <span class="badge filter-summary-badge px-3 py-2">Project: {{ $selectedProject ? $selectedProject->project_code : 'Selected project' }}</span>
            @endif
            @if(request('department_id'))
                <span class="badge filter-summary-badge px-3 py-2">Department: {{ $selectedDepartment?->name ?? 'Selected department' }}</span>
            @endif
            @if(request('employee_id'))
                <span class="badge filter-summary-badge px-3 py-2">User: {{ $selectedEmployee?->name ?? 'Selected user' }}</span>
            @endif
            @if(request('role'))
                <span class="badge filter-summary-badge px-3 py-2">Role: {{ $selectedRole }}</span>
            @endif
            @if($selectedEmployeeTypes)
                <span class="badge filter-summary-badge px-3 py-2">Employee type: {{ $selectedEmployeeTypeLabels->join(', ') }}</span>
            @endif
            @if(request('status'))
                <span class="badge filter-summary-badge px-3 py-2">Status: {{ str_replace('_', ' ', ucfirst(request('status'))) }}</span>
            @endif
            @if(request('corrections') === 'open')
                <span class="badge filter-summary-badge px-3 py-2">Correction review: Open HOD requests</span>
            @endif
            @if(request()->boolean('employee_totals_only'))
                <span class="badge filter-summary-badge px-3 py-2">Export: Employee charging report</span>
            @endif
            @unless($hasVisibleFilters)
                <span class="badge filter-summary-badge px-3 py-2">No filters applied</span>
            @endunless
        </div>
    </div>
</div>
@if($summaryPreviewState['requested'] && $summaryPreviewState['message'])
    <div class="alert alert-warning mb-3">
        {{ $summaryPreviewState['message'] }}
    </div>
@endif
@if($summaryPreview)
    @include('admin.timesheets._summary_preview', ['summaryPreview' => $summaryPreview])
@endif
<div class="content-card overflow-hidden"><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>User</th><th>Employee Type</th><th>Role</th><th>Department</th><th>Week</th><th>Status</th><th>Total</th><th></th></tr></thead><tbody>
@if($showingNotSubmitted)
    @forelse($timesheets as $row)
        <tr><td class="fw-semibold">{{ $row->user->name }}</td><td>{{ $row->user->employeeTypeLabel() }}</td><td>{{ $roleLabels[$row->user->role] ?? $row->user->role }}</td><td>{{ $row->department?->name ?: '-' }}</td><td>{{ $row->period->week_number }} / {{ $row->period->year }}</td><td>@include('partials.status', ['status' => 'not_submitted'])</td><td><span class="fw-semibold">0.00</span></td><td></td></tr>
    @empty
        <tr><td colspan="8" class="empty-state">No users match the not submitted filters.</td></tr>
    @endforelse
@else
    @forelse($timesheets as $timesheet)
        <tr><td class="fw-semibold">{{ $timesheet->user->name }}</td><td>{{ $timesheet->user->employeeTypeLabel() }}</td><td>{{ $roleLabels[$timesheet->user->role] ?? $timesheet->user->role }}</td><td>{{ $timesheet->department->name }}</td><td>{{ $timesheet->period->week_number }} / {{ $timesheet->period->year }}</td><td>@include('partials.status', ['status' => $timesheet->status]) @if($timesheet->eligible_open_correction_requests_count)<span class="badge text-bg-warning ms-1">Needs review · {{ $timesheet->eligible_open_correction_requests_count }}</span>@endif</td><td><span class="fw-semibold">{{ $timesheet->total_hours }}</span></td><td class="text-end"><a class="btn btn-sm btn-primary" href="{{ route('admin.timesheets.show', $timesheet) }}">View</a></td></tr>
    @empty
        <tr><td colspan="8" class="empty-state">No records found.</td></tr>
    @endforelse
@endif
</tbody></table></div></div>
@include('shared.pagination-footer', ['paginator' => $timesheets, 'label' => 'timesheet'])
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const exportButton = document.getElementById('timesheetExportButton');
        const weeklyFilters = document.querySelectorAll('[data-weekly-filter]');
        const monthlyFilters = document.querySelectorAll('[data-monthly-filter]');
        const modeInputs = document.querySelectorAll('input[name="filter_mode"]');
        const employeeTotalsOnly = document.getElementById('employee_totals_only');
        const includeEmployeeSheets = document.getElementById('include_employee_sheets');
        const allEmployeeTypes = document.querySelector('[data-employee-type-all]');
        const employeeTypeOptions = Array.from(document.querySelectorAll('[data-employee-type-option]'));
        const employeeTypeLabel = document.querySelector('[data-employee-type-label]');
        const employeeTypeCount = document.querySelector('[data-employee-type-count]');

        function syncReportMode() {
            const mode = document.querySelector('input[name="filter_mode"]:checked')?.value || 'weekly';
            weeklyFilters.forEach((filter) => {
                filter.classList.toggle('d-none', mode !== 'weekly');
                filter.querySelectorAll('input, select').forEach((input) => {
                    input.disabled = mode !== 'weekly';
                });
            });
            monthlyFilters.forEach((filter) => {
                filter.classList.toggle('d-none', mode !== 'monthly');
                filter.querySelectorAll('input, select').forEach((input) => {
                    input.disabled = mode !== 'monthly';
                });
            });
        }

        function syncExportOptions() {
            if (!employeeTotalsOnly || !includeEmployeeSheets) {
                return;
            }

            if (employeeTotalsOnly.checked) {
                includeEmployeeSheets.checked = false;
            }

            includeEmployeeSheets.disabled = employeeTotalsOnly.checked;
        }

        function syncEmployeeTypes(changedInput) {
            if (!allEmployeeTypes || !employeeTypeLabel) {
                return;
            }

            if (changedInput === allEmployeeTypes && allEmployeeTypes.checked) {
                employeeTypeOptions.forEach((option) => { option.checked = false; });
            } else if (changedInput?.matches('[data-employee-type-option]') && changedInput.checked) {
                allEmployeeTypes.checked = false;
            }

            const selected = employeeTypeOptions.filter((option) => option.checked);

            if (selected.length === 0) {
                allEmployeeTypes.checked = true;
                employeeTypeLabel.textContent = 'All employee types';
            } else {
                allEmployeeTypes.checked = false;
                employeeTypeLabel.textContent = selected.length === 1
                    ? selected[0].nextElementSibling.textContent.trim()
                    : `${selected.length} employee types`;
            }

            if (employeeTypeCount) {
                employeeTypeCount.textContent = selected.length;
                employeeTypeCount.classList.toggle('d-none', selected.length < 2);
            }
        }

        modeInputs.forEach((input) => input.addEventListener('change', syncReportMode));
        employeeTotalsOnly?.addEventListener('change', syncExportOptions);
        allEmployeeTypes?.addEventListener('change', (event) => syncEmployeeTypes(event.currentTarget));
        employeeTypeOptions.forEach((option) => option.addEventListener('change', (event) => syncEmployeeTypes(event.currentTarget)));
        syncReportMode();
        syncExportOptions();
        syncEmployeeTypes();

        if (! exportButton) {
            return;
        }

        const spinner = exportButton.querySelector('.spinner-border');
        const label = exportButton.querySelector('[data-export-label]');

        exportButton.addEventListener('click', function (event) {
            if (exportButton.classList.contains('disabled')) {
                event.preventDefault();
                return;
            }

            exportButton.classList.add('disabled');
            exportButton.setAttribute('aria-disabled', 'true');
            spinner?.classList.remove('d-none');
            window.showAppToast?.('Export started. Your Excel file will download when ready.', 'success', 'Export started');

            if (label) {
                label.textContent = 'Starting export...';
            }

            window.setTimeout(function () {
                exportButton.classList.remove('disabled');
                exportButton.removeAttribute('aria-disabled');
                spinner?.classList.add('d-none');

                if (label) {
                    label.textContent = 'Export Excel';
                }
            }, 2000);
        });
    });
</script>
@endpush
