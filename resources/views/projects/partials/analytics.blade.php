<link rel="stylesheet" href="{{ asset('css/project-analytics.css') }}">
<div class="project-analytics" id="project-analytics">
<div class="filter-card p-3 mb-3">
    <form id="analytics-filters" method="get" action="{{ route('projects.utilization', ['project' => $project] + $listContext) }}">
        @include('shared.list-context')
        <input type="hidden" name="tab" value="analytics">
        <div class="row g-3 align-items-end">
            <div class="col-sm-6 col-lg-4">
                <label class="form-label" for="date_from">From date</label>
                <input class="form-control @error('date_from') is-invalid @enderror" type="date" id="date_from" name="date_from" min="1900-01-01" max="2100-12-31" value="{{ old('date_from', $filters['date_from'] ?? $analytics['from']) }}">
                @error('date_from')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-sm-6 col-lg-4">
                <label class="form-label" for="date_to">To date</label>
                <input class="form-control @error('date_to') is-invalid @enderror" type="date" id="date_to" name="date_to" min="1900-01-01" max="2100-12-31" value="{{ old('date_to', $filters['date_to'] ?? $analytics['to']) }}">
                @error('date_to')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-sm-6 col-lg-4 d-flex flex-wrap gap-2">
                <button class="btn btn-outline-secondary" type="submit">Apply filters</button>
                @if(filled($filters['date_from'] ?? null) || filled($filters['date_to'] ?? null) || filled($filters['rank_by'] ?? null))
                <a data-analytics-reset class="btn btn-link" href="{{ route('projects.utilization', ['project' => $project, 'tab' => 'analytics'] + $listContext) }}">Clear filters</a>
                @endif
            </div>
        </div>
        <p class="small text-muted mt-3 mb-0">{{ $analytics['from'] }} to {{ $analytics['to'] }} · Approved and submitted entries only. All hours shown belong to this project. Partial months follow the selected dates.</p>
    </form>
</div>
@if($analytics['projectNotStarted'])
<div class="alert alert-secondary">Project has not started. The scheduled start date is {{ $project->start_date->toFormattedDateString() }}. You can adjust the dates to review recorded hours.</div>
@endif
@php
    $periodHours = $analytics['regular'] + $analytics['overtime'];
    $remainingHours = $analytics['allocated'] - $analytics['approved'] - $analytics['pending'];
@endphp
<section class="content-card overflow-hidden mb-3" aria-labelledby="effort-heading">
    <div class="content-card-header analytics-heading">
        <div><h2 class="h5 mb-1" id="effort-heading">Selected-period effort</h2><p class="small text-muted mb-0">{{ $analytics['from'] }} to {{ $analytics['to'] }} · Hours, not a measure of completion.</p></div>
        <span class="badge text-bg-secondary">{{ $analytics['employees']->count() }} {{ \Illuminate\Support\Str::plural('employee', $analytics['employees']->count()) }} with recorded hours</span>
    </div>
    <div class="analytics-summary">
        @foreach(['Recorded hours' => $periodHours, 'Approved hours' => $analytics['periodApproved'], 'Pending hours' => $analytics['periodPending'], 'Overtime hours' => $analytics['overtime']] as $label => $value)
        <div class="analytics-stat"><div class="small text-muted">{{ $label }}</div><div class="analytics-value">{{ number_format($value, 2) }}</div><small>{{ match($label) { 'Recorded hours' => 'Regular + overtime', 'Approved hours' => 'Approved timesheets', 'Pending hours' => 'Submitted, awaiting approval', default => ($periodHours > 0 ? number_format(100 * $value / $periodHours, 1).'%' : '0%').' of recorded hours' } }}</small></div>
        @endforeach
    </div>
    <div class="analytics-ledger">
        <div><div class="fw-semibold">Lifetime allocation position</div><div class="small text-muted">Through {{ $analytics['to'] }}, including earlier entries</div></div>
        <dl>
            @foreach(['Allocated hours' => $analytics['hasAllocation'] ? number_format($analytics['allocated'], 2) : 'Not set', 'Approved to end date' => number_format($analytics['approved'], 2), 'Pending to end date' => number_format($analytics['pending'], 2), 'Remaining after pending' => $analytics['hasAllocation'] ? number_format($remainingHours, 2) : 'Not set'] as $label => $value)
            <div><dt>{{ $label }}</dt><dd class="{{ $label === 'Remaining after pending' && $analytics['hasAllocation'] && $remainingHours < 0 ? 'text-danger-emphasis' : '' }}">{{ $value }}</dd></div>
            @endforeach
        </dl>
    </div>
</section>
@if($analytics['hasAllocation'] && $remainingHours < 0)
<div class="alert alert-warning"><strong>Over allocation by {{ number_format(abs($remainingHours), 2) }} hours.</strong> Approved and pending hours through the end date exceed the current lifetime allowance.</div>
@endif
@if(auth()->user()->isSuperAdmin())
    @include('projects.partials.analytics-cost')
@endif
@unless($analytics['hasEntries'])
<div class="alert alert-secondary">No approved or submitted entries in this period. Adjust the dates or return after timesheets are submitted. Cumulative totals still include earlier entries.</div>
@endunless
<div class="analytics-charts">
<div class="content-card mb-3">
    <div class="content-card-header"><h2 class="h5 mb-1">Monthly trends</h2><p class="small text-muted mb-0">Regular hours and overtime recorded each month, including pending submissions.</p></div>
    <div class="content-card-body">
        @include('projects.partials.analytics-chart', ['chartType' => 'monthly'])
    </div>
</div>
<div class="content-card mb-3">
    <div class="content-card-header"><h2 class="h5 mb-1">Cumulative hours consumed</h2><p class="small text-muted mb-0">Running totals include {{ number_format($analytics['openingHours'], 2) }} hours before the selected period. Allocation is the current lifetime allowance; hours consumed do not measure completion.</p></div>
    <div class="content-card-body">
        @include('projects.partials.analytics-chart', ['chartType' => 'cumulative'])
    </div>
</div>
</div>
<section class="content-card overflow-hidden mb-3" aria-labelledby="department-effort-heading">
    <div class="content-card-header"><h2 class="h5 mb-1" id="department-effort-heading">Department effort</h2><p class="small text-muted mb-0">Selected-period hours by charged department, including pending submissions.</p></div>
    <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th scope="col">Department</th><th scope="col" class="text-end">Approved</th><th scope="col" class="text-end">Pending</th><th scope="col" class="text-end">Overtime</th><th scope="col" class="text-end">Recorded hours</th></tr></thead><tbody>
    @forelse($analytics['departments'] as $department)
    <tr><th scope="row">{{ $department['name'] }}</th><td class="text-end">{{ number_format($department['approved'], 2) }}</td><td class="text-end">{{ number_format($department['pending'], 2) }}</td><td class="text-end">{{ number_format($department['overtime'], 2) }}</td><td class="text-end fw-semibold">{{ number_format($department['regular'] + $department['overtime'], 2) }}</td></tr>
    @empty
    <tr><td colspan="5" class="empty-state text-center">No department hours in this period.</td></tr>
    @endforelse
    </tbody></table></div>
</section>
<div class="content-card overflow-hidden mb-3">
    <div class="content-card-header analytics-heading"><div><h2 class="h5 mb-1">Employee hours</h2><p class="small text-muted mb-0">Ranked by {{ ($filters['rank_by'] ?? 'overtime') === 'total' ? 'total hours, then overtime' : 'overtime, then total hours' }} for the selected period. Review entries to understand the work behind the hours.</p><div class="small mt-2"><span class="analytics-key" aria-hidden="true"></span>Regular <span class="analytics-key analytics-key-overtime ms-3" aria-hidden="true"></span>Overtime</div></div>
        <div class="analytics-inline-label"><label class="form-label small" for="rank_by">Rank employees by</label>
            <select class="form-select @error('rank_by') is-invalid @enderror" id="rank_by" name="rank_by" form="analytics-filters" data-searchable="false">
                <option value="overtime" @selected(($filters['rank_by'] ?? 'overtime') === 'overtime')>Highest overtime</option>
                <option value="total" @selected(($filters['rank_by'] ?? '') === 'total')>Highest total hours</option>
            </select>
            @error('rank_by')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <button class="btn btn-link btn-sm px-0" type="submit" form="analytics-filters">Update ranking</button>
        </div>
    </div>
    <div class="table-responsive">
        <table class="project-responsive-table table align-middle mb-0">
            <thead><tr><th>Employee</th><th style="min-width:12rem">Hours breakdown</th><th class="text-end">Regular</th><th class="text-end">Overtime</th><th class="text-end">Total hours</th><th>Actions</th></tr></thead>
            <tbody>
            @php($maximum = max(1, (float) $analytics['employees']->max('total')))
            @forelse($analytics['employees'] as $employee)
                <tr>
                    <td data-label="Employee" class="fw-semibold">{{ $employee->name }}</td>
                    <td data-label="Hours breakdown"><div class="d-flex rounded overflow-hidden bg-body-tertiary" style="height:1rem" aria-hidden="true"><span style="background:var(--bs-primary);width:{{ 100 * $employee->regular / $maximum }}%"></span><span style="background:var(--bs-warning-text-emphasis);width:{{ 100 * $employee->overtime / $maximum }}%"></span></div></td>
                    <td data-label="Regular" class="text-end">{{ number_format($employee->regular, 2) }}</td>
                    <td data-label="Overtime" class="text-end">{{ number_format($employee->overtime, 2) }}</td>
                    <td data-label="Total hours" class="text-end fw-semibold">{{ number_format($employee->total, 2) }}</td>
                    <td data-label="Actions"><a class="btn btn-sm btn-outline-secondary" href="{{ route('projects.utilization', ['project' => $project, 'review_employee_id' => $employee->id, 'date_from' => $analytics['from'], 'date_to' => $analytics['to']] + $listContext) }}#entry-review-docket">Review entries</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty-state text-center">No employees recorded approved or submitted hours in this period.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

</div>

@push('scripts')
<script src="{{ asset('js/project-analytics-charts.js') }}" defer></script>
@endpush
