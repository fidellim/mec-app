@extends('layouts.app')

@section('content')
@php
    $hasLeaveFilters = collect(request()->only(['status', 'employee_id', 'employee_ids', 'department_id', 'attendance_code']))->contains(fn ($value) => filled($value));
@endphp
<style>
    .leave-plan-filter-card .ts-wrapper.single .ts-control,
    .leave-plan-employee-filter .ts-wrapper.multi .ts-control {
        align-items: center;
        flex-wrap: nowrap;
        height: calc(2.25rem + 2px);
        min-height: calc(2.25rem + 2px);
        max-height: calc(2.25rem + 2px);
        overflow: hidden;
    }

    .leave-plan-filter-card .ts-wrapper.single .ts-control .item {
        flex: 1 1 auto;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .leave-plan-filter-card .ts-wrapper.single .ts-control input {
        flex: 0 1 1rem;
        min-width: 0;
        width: 1rem !important;
    }

    .leave-plan-employee-filter .ts-wrapper.multi .ts-control {
        overflow-x: auto;
        overflow-y: hidden;
    }

    .leave-plan-employee-filter .ts-wrapper.multi .ts-control > div {
        flex: 0 0 auto;
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .leave-plan-employee-filter .ts-wrapper.multi .ts-control input {
        flex: 0 0 7rem;
        min-width: 7rem;
    }
</style>
<div class="section-header">
    <div>
        <h1 class="h3 page-heading mb-1">All Leave Plans</h1>
        <div class="text-muted">Review leave plans across all departments.</div>
    </div>
    <a class="btn btn-outline-secondary" href="{{ route('admin.leave-plans.calendar') }}">Calendar</a>
</div>
<form class="filter-card leave-plan-filter-card mb-3" method="get">
    <div class="row g-3 align-items-end">
        <div class="col-md-3">
            <label class="form-label" for="department_id">Department</label>
            <select class="form-select" id="department_id" name="department_id">
                <option value="">All departments</option>
                @foreach($departments as $department)
                    <option value="{{ $department->id }}" @selected((int) request('department_id') === (int) $department->id)>{{ $department->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="status">Status</label>
            <select class="form-select" id="status" name="status">
                <option value="">All statuses</option>
                @foreach(['submitted','approved','rejected','cancellation_requested','recalled','cancelled','voided','draft'] as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="attendance_code">Attendance Code</label>
            <select class="form-select" id="attendance_code" name="attendance_code">
                <option value="">All leave types</option>
                @foreach($attendanceCodes as $code => $label)
                    <option value="{{ $code }}" @selected(request('attendance_code') === $code)>{{ $code }} - {{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3 leave-plan-employee-filter">
            <label class="form-label" for="leave_plan_employee_ids">Employees</label>
            <select
                class="form-select"
                id="leave_plan_employee_ids"
                name="employee_ids[]"
                multiple
                data-searchable="false"
                data-employee-lookup-url="{{ route('admin.leave-plans.index') }}"
                placeholder="Search employees"
            >
                @foreach($selectedEmployees as $employee)
                    <option value="{{ $employee->id }}" selected>
                        {{ $employee->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-12 d-flex gap-2 justify-content-end">
            <button class="btn btn-primary">Filter</button>
            @if($hasLeaveFilters)
                <a class="btn btn-outline-secondary" href="{{ route('admin.leave-plans.index') }}">Reset</a>
            @endif
        </div>
    </div>
</form>
@include('shared.leave_plan_table', ['leavePlans' => $leavePlans, 'showEmployee' => true, 'showDepartment' => true, 'showRoute' => 'admin.leave-plans.show'])
<div class="mt-3">{{ $leavePlans->links() }}</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const employeeSelect = document.getElementById('leave_plan_employee_ids');

        if (! employeeSelect || ! window.TomSelect || employeeSelect.tomselect) {
            return;
        }

        const lookupState = {
            query: '',
            page: 0,
            hasMore: true,
            loading: false,
        };

        const fetchEmployeeOptions = async (query, page) => {
            const url = new URL(employeeSelect.dataset.employeeLookupUrl, window.location.origin);
            url.searchParams.set('employee_lookup', '1');
            url.searchParams.set('page', page);

            if (query) {
                url.searchParams.set('q', query);
            }

            const response = await fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (! response.ok) {
                throw new Error('Employee lookup failed.');
            }

            return response.json();
        };

        const employeeTomSelect = new TomSelect(employeeSelect, {
            allowEmptyOption: true,
            create: false,
            dropdownParent: 'body',
            loadThrottle: 300,
            maxItems: null,
            maxOptions: null,
            plugins: {
                remove_button: {
                    title: 'Remove selected employee',
                },
            },
            valueField: 'value',
            labelField: 'text',
            searchField: ['text'],
            sortField: [{ field: '$order' }],
            placeholder: employeeSelect.getAttribute('placeholder') || 'Search employees',
            shouldLoad: function () {
                return true;
            },
            load: function (query, callback) {
                lookupState.query = query;
                lookupState.page = 1;
                lookupState.loading = true;

                fetchEmployeeOptions(query, 1)
                    .then((data) => {
                        lookupState.hasMore = Boolean(data.has_more);
                        callback(data.results || []);
                    })
                    .catch(() => callback())
                    .finally(() => {
                        lookupState.loading = false;
                    });
            },
        });

        const loadNextEmployeePage = async () => {
            if (lookupState.loading || ! lookupState.hasMore) {
                return;
            }

            lookupState.loading = true;

            try {
                const nextPage = lookupState.page + 1;
                const data = await fetchEmployeeOptions(lookupState.query, nextPage);

                lookupState.page = data.page || nextPage;
                lookupState.hasMore = Boolean(data.has_more);
                (data.results || []).forEach((option) => employeeTomSelect.addOption(option));
                employeeTomSelect.refreshOptions(false);
            } catch (error) {
                lookupState.hasMore = false;
            } finally {
                lookupState.loading = false;
            }
        };

        employeeTomSelect.on('dropdown_open', function () {
            if (lookupState.page === 0 && ! lookupState.loading) {
                employeeTomSelect.load('');
            }
        });

        employeeTomSelect.dropdown_content.addEventListener('scroll', function () {
            const distanceFromBottom = this.scrollHeight - this.scrollTop - this.clientHeight;

            if (distanceFromBottom < 80) {
                loadNextEmployeePage();
            }
        });
    });
</script>
@endpush
