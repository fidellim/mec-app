@extends('layouts.app')

@section('content')
<div class="section-header">
    <div>
        <h1 class="h3 page-heading mb-1">All Timesheets</h1>
        <div class="text-muted">Filter, review, and export submitted weekly records.</div>
    </div>
    <a class="btn btn-outline-success" id="timesheetExportButton" href="{{ route('admin.timesheets.export', request()->query()) }}">
        <span class="spinner-border spinner-border-sm me-2 d-none" aria-hidden="true"></span>
        <span data-export-label>Export Excel</span>
    </a>
</div>
<form class="filter-card mb-3 row g-2">
    <div class="col-md-2">
        <label class="form-label small text-muted" for="week_from">From Week</label>
        <input id="week_from" class="form-control @error('week_from') is-invalid @enderror" name="week_from" placeholder="e.g. 12" value="{{ old('week_from', request('week_from', request('week_number'))) }}">
        @error('week_from')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-2">
        <label class="form-label small text-muted" for="week_to">To Week <span class="fw-normal">(optional)</span></label>
        <input id="week_to" class="form-control @error('week_to') is-invalid @enderror" name="week_to" placeholder="e.g. 15" value="{{ old('week_to', request('week_to')) }}">
        <div class="form-text">Leave blank to view one week.</div>
        @error('week_to')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-2"><label class="form-label small text-muted" for="year">Year</label><input id="year" class="form-control" name="year" placeholder="Year" value="{{ request('year') }}"></div>
    <div class="col-md-3"><label class="form-label small text-muted" for="project_id">Project</label><select id="project_id" class="form-select" name="project_id"><option value="">All projects</option>@foreach($projects as $project)<option value="{{ $project->id }}" @selected(request('project_id') == $project->id)>{{ $project->project_code }} - {{ $project->project_name }}</option>@endforeach</select></div>
    <div class="col-md-3"><label class="form-label small text-muted" for="department_id">Department</label><select id="department_id" class="form-select" name="department_id"><option value="">All departments</option>@foreach($departments as $department)<option value="{{ $department->id }}" @selected(request('department_id') == $department->id)>{{ $department->name }}</option>@endforeach</select></div>
    <div class="col-md-3"><label class="form-label small text-muted" for="employee_id">Employee</label><select id="employee_id" class="form-select" name="employee_id"><option value="">All employees</option>@foreach($employees as $employee)<option value="{{ $employee->id }}" @selected(request('employee_id') == $employee->id)>{{ $employee->name }}</option>@endforeach</select></div>
    <div class="col-md-2"><label class="form-label small text-muted" for="status">Status</label><select id="status" class="form-select" name="status"><option value="">All statuses</option>@foreach(['draft','submitted','approved','rejected'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></div>
    <div class="col-md-4 d-flex align-items-end">
        <div class="form-check mb-2">
            <input type="hidden" name="include_employee_sheets" value="0">
            <input class="form-check-input" type="checkbox" id="include_employee_sheets" name="include_employee_sheets" value="1" @checked(request()->boolean('include_employee_sheets'))>
            <label class="form-check-label" for="include_employee_sheets">Include individual employee timesheet sheets</label>
            <div class="form-text">Leave unchecked for a faster project summary export.</div>
        </div>
    </div>
    <div class="col-12 text-end"><button class="btn btn-primary">Apply Filters</button></div>
</form>
<div class="content-card overflow-hidden"><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Employee</th><th>Department</th><th>Week</th><th>Status</th><th>Total</th><th></th></tr></thead><tbody>
@forelse($timesheets as $timesheet)
    <tr><td class="fw-semibold">{{ $timesheet->user->name }}</td><td>{{ $timesheet->department->name }}</td><td>{{ $timesheet->period->week_number }} / {{ $timesheet->period->year }}</td><td>@include('partials.status', ['status' => $timesheet->status])</td><td><span class="fw-semibold">{{ $timesheet->total_hours }}</span></td><td class="text-end"><a class="btn btn-sm btn-primary" href="{{ route('admin.timesheets.show', $timesheet) }}">View</a></td></tr>
@empty
    <tr><td colspan="6" class="empty-state">No records found.</td></tr>
@endforelse
</tbody></table></div></div>
<div class="mt-3">{{ $timesheets->links() }}</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const exportButton = document.getElementById('timesheetExportButton');

        if (! exportButton) {
            return;
        }

        const spinner = exportButton.querySelector('.spinner-border');
        const label = exportButton.querySelector('[data-export-label]');

        exportButton.addEventListener('click', function () {
            exportButton.classList.add('disabled');
            exportButton.setAttribute('aria-disabled', 'true');
            spinner?.classList.remove('d-none');

            if (label) {
                label.textContent = 'Preparing export...';
            }

            window.setTimeout(function () {
                exportButton.classList.remove('disabled');
                exportButton.removeAttribute('aria-disabled');
                spinner?.classList.add('d-none');

                if (label) {
                    label.textContent = 'Export Excel';
                }
            }, 8000);
        });
    });
</script>
@endpush
