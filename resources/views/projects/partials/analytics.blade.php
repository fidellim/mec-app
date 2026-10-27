<div class="filter-card p-3 mb-3">
    <form method="get" action="{{ route('projects.utilization', ['project' => $project] + $listContext) }}">
        @include('shared.list-context')
        <input type="hidden" name="tab" value="analytics">
        <div class="row g-3 align-items-end">
            <div class="col-sm-6 col-lg-3">
                <label class="form-label" for="date_from">From date</label>
                <input class="form-control @error('date_from') is-invalid @enderror" type="date" id="date_from" name="date_from" min="1900-01-01" max="2100-12-31" value="{{ old('date_from', $filters['date_from'] ?? '') }}">
                @error('date_from')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-sm-6 col-lg-3">
                <label class="form-label" for="date_to">To date</label>
                <input class="form-control @error('date_to') is-invalid @enderror" type="date" id="date_to" name="date_to" min="1900-01-01" max="2100-12-31" value="{{ old('date_to', $filters['date_to'] ?? '') }}">
                @error('date_to')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-sm-6 col-lg-3">
                <label class="form-label" for="rank_by">Rank employees by</label>
                <select class="form-select @error('rank_by') is-invalid @enderror" id="rank_by" name="rank_by" data-searchable="false">
                    <option value="overtime" @selected(($filters['rank_by'] ?? 'overtime') === 'overtime')>Highest overtime</option>
                    <option value="total" @selected(($filters['rank_by'] ?? '') === 'total')>Highest total hours</option>
                </select>
                @error('rank_by')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-sm-6 col-lg-3 d-flex flex-wrap gap-2">
                <button class="btn btn-primary" type="submit">Apply filters</button>
                @if(filled($filters['date_from'] ?? null) || filled($filters['date_to'] ?? null) || filled($filters['rank_by'] ?? null))
                <a class="btn btn-link" href="{{ route('projects.utilization', ['project' => $project, 'tab' => 'analytics'] + $listContext) }}">Clear filters</a>
                @endif
            </div>
        </div>
        <p class="small text-muted mt-3 mb-0">{{ $analytics['from'] }} to {{ $analytics['to'] }} · Approved and submitted entries only. All hours shown belong to this project. Partial months follow the selected dates.</p>
    </form>
</div>
<div class="row g-3 mb-3">
    @foreach(['Allocated hours' => $analytics['hasAllocation'] ? number_format($analytics['allocated'], 2) : 'Not set', 'Approved to end date' => number_format($analytics['approved'], 2), 'Pending to end date' => number_format($analytics['pending'], 2), 'Remaining after pending' => $analytics['hasAllocation'] ? number_format($analytics['allocated'] - $analytics['approved'] - $analytics['pending'], 2) : 'Not set'] as $label => $value)
    <div class="col-sm-6 col-xl-3"><div class="content-card p-3 h-100"><div class="small text-muted mb-2">{{ $label }}</div><div class="h4 mb-0 font-monospace">{{ $value }}</div></div></div>
    @endforeach
</div>
@unless($analytics['hasEntries'])
<div class="alert alert-secondary">No approved or submitted entries in this period. Adjust the dates or return after timesheets are submitted. Cumulative totals still include earlier entries.</div>
@endunless
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
<div class="content-card overflow-hidden mb-3">
    <div class="content-card-header"><h2 class="h5 mb-1">Employee hours</h2><p class="small text-muted mb-0">Ranked by {{ ($filters['rank_by'] ?? 'overtime') === 'total' ? 'total hours, then overtime' : 'overtime, then total hours' }} for the selected period. Review entries to understand the work behind the hours.</p></div>
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
