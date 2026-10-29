@include('projects.partials.analytics-finance')
<section class="content-card mb-3" data-financial-panel aria-labelledby="labour-cost-heading" data-worker-url="{{ asset('js/project-cost-worker.js') }}">
    <div class="content-card-header analytics-heading">
        <div><h2 class="h5 mb-1" id="labour-cost-heading">Confidential labour costs</h2><p class="small text-muted mb-0">Add salary-based employee rates to this selected-period analysis.</p></div>
        <span class="badge text-bg-secondary" data-cost-badge>Rates not loaded</span>
    </div>
    <div class="content-card-body">
        <div class="analytics-import" data-cost-import>
            <div class="row g-3 align-items-end">
                <div class="col-sm-4 col-xl-3">
                    <label class="form-label" for="cost-currency">Rate currency</label>
                    <select class="form-select" id="cost-currency" data-cost-currency data-searchable="false">
                        <option value="">Choose currency</option>
                        @foreach(['AED', 'USD', 'EUR', 'GBP', 'PHP', 'INR', 'SAR', 'QAR'] as $currency)
                        <option value="{{ $currency }}">{{ $currency }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-8 col-xl-6">
                    <label class="form-label" for="cost-workbook">Load confidential rates</label>
                    <input type="file" id="cost-workbook" class="form-control" accept=".xlsx,.xls" data-cost-file aria-describedby="cost-file-help">
                </div>
                <div class="col-xl-3"><button type="button" class="btn btn-outline-secondary" data-cost-clear disabled>Clear financial data</button></div>
            </div>
            <p class="small text-muted mt-3 mb-1" id="cost-file-help">Excel (.xlsx or .xls), up to 5 MB. Columns: Employee ID, Initials, Employee Name, Rate/Manhour.</p>
            <p class="small text-muted mb-0">Read only in this browser; never uploaded or saved by the app. Clear anytime. Leaving, refreshing, logging out, or 15 minutes clears the financial view. Your original file stays on your computer.</p>
        </div>
        <p class="small mt-3 mb-0" data-cost-status role="status" aria-live="polite">Choose the rate currency, then load your workbook. Hours analytics remains available without rates.</p>
        <div class="alert alert-warning mt-3 mb-0" data-cost-warning hidden></div>
        <div data-cost-results hidden>
            <div class="analytics-summary mt-3">
                @foreach(['approved' => 'Approved labour cost', 'pending' => 'Pending cost exposure', 'total' => 'Total recorded cost', 'premium' => 'Overtime premium'] as $key => $label)
                <div class="analytics-stat"><div class="small text-muted">{{ $label }}</div><div class="analytics-value" data-cost-value="{{ $key }}">—</div><small>{{ match($key) { 'approved' => 'Approved hours only', 'pending' => 'Submitted, awaiting approval', 'total' => 'Approved + pending', default => 'Additional 25% above base rate' } }}</small></div>
                @endforeach
            </div>
            <div class="analytics-ledger mb-3">
                <div><span class="fw-semibold" data-cost-coverage></span><div class="small text-muted" data-cost-matches></div></div>
                <div><span class="small text-muted">Total overtime cost: </span><strong data-cost-value="overtime">—</strong></div>
            </div>
            <div class="analytics-charts">
                <section aria-labelledby="cost-trend-heading"><h3 class="h6 mb-2" id="cost-trend-heading">Monthly cost trend</h3><div class="table-responsive" data-cost-months></div></section>
                <section aria-labelledby="cost-drivers-heading">
                    <div class="analytics-heading mb-2"><h3 class="h6 mb-0" id="cost-drivers-heading">Cost drivers</h3><div><label class="visually-hidden" for="cost-group">Group costs by</label><select id="cost-group" class="form-select form-select-sm" data-cost-group data-searchable="false"><option value="departments">Department</option><option value="categories">Manpower category</option><option value="employees">Employee</option></select></div></div>
                    <div class="table-responsive" data-cost-drivers></div>
                </section>
            </div>
            <details class="small text-muted mt-3"><summary>Calculation basis and limits</summary>
                <p class="mt-2 mb-1">Regular hours × employee rate. Overtime hours × employee rate × 1.25. The overtime premium is the extra 0.25 portion, already included in overtime cost.</p>
                <p class="mb-1">One imported rate per employee applies throughout {{ $analytics['from'] }} to {{ $analytics['to'] }}. Excel formulas use their saved numeric results; save and recalculate the workbook before loading it. No currency conversion is applied.</p>
                <p class="mb-0">Regular and overtime costs are rounded separately to two decimal places per employee, month, charged department, category and approval status, then summed. Salary-based labour cost excludes benefits and overhead unless included in your rate. Unpriced hours are excluded from monetary totals. Figures are not payroll, invoices, profit, or project completion.</p>
            </details>
        </div>
    </div>
</section>
<script type="application/json" id="project-cost-basis">{!! \Illuminate\Support\Js::encode($analytics['costBasis']) !!}</script>
<script type="application/json" id="project-management-cost-basis">{!! \Illuminate\Support\Js::encode($analytics['managementCostBasis']) !!}</script>
@push('scripts')
<script src="{{ asset('js/project-cost-view.js') }}" defer></script>
@endpush
