<section class="content-card mb-3" data-finance-panel data-project-code="{{ $project->project_code }}" data-worker-url="{{ asset('js/project-finance-worker.js').'?v='.$financialAssetVersion }}" aria-labelledby="project-finance-heading">
    <div class="content-card-header analytics-heading">
        <div><h2 class="h5 mb-1" id="project-finance-heading">Project financial position</h2><p class="small text-muted mb-0">Cumulative project totals in AED, excluding VAT. Independent of the hours filters.</p></div>
        <span class="badge text-bg-secondary">{{ $project->project_code }}</span>
    </div>
    <div class="content-card-body">
        <div class="row g-3 align-items-end">
            <div class="col-md-8"><label class="form-label" for="finance-workbook">Load project finance summary</label><input type="file" class="form-control" id="finance-workbook" accept=".xlsx,.xls" data-finance-file aria-describedby="finance-help"></div>
            <div class="col-md-4"><button type="button" class="btn btn-outline-secondary" data-finance-clear disabled>Clear project figures</button></div>
        </div>
        <p class="small text-muted mt-2 mb-1" id="finance-help">One sheet. Columns: Project Code, Order Value, Invoice Submitted, Subcontractor Spent, Manhour Spent. Only {{ $project->project_code }} is used. Excel up to 5 MB.</p>
        <p class="small text-muted mb-0">Read in this browser only. Never uploaded or saved. Leaving, refreshing, logging out, clearing financial data, or 15 minutes removes these figures.</p>
        <p class="small mt-3 mb-0" data-finance-status role="status" aria-live="polite">Load a workbook to view this project’s financial position.</p>
        <div data-finance-results hidden>
            <div class="analytics-summary mt-3">
                @foreach(['order' => 'Order value', 'invoiced' => 'Invoice submitted', 'balance' => 'Balance for invoicing', 'total' => 'Total cost spent'] as $key => $label)
                <div class="analytics-stat"><div class="small text-muted">{{ $label }}</div><div class="analytics-value" data-finance-value="{{ $key }}">—</div></div>
                @endforeach
            </div>
            <p class="small analytics-finance-note" data-finance-note></p>
            <div class="analytics-charts mt-4">
                <section aria-labelledby="finance-billing-heading"><h3 class="h6 mb-3" id="finance-billing-heading">Invoicing against order value</h3><div data-finance-billing></div></section>
                <section aria-labelledby="finance-spend-heading"><h3 class="h6 mb-3" id="finance-spend-heading">Cost spent</h3><div data-finance-spend></div></section>
            </div>
            <div class="analytics-ledger"><span>Overhead = invoice submitted × 8%</span><span>Total cost = labour + subcontractors + overhead</span></div>
            <p class="small text-muted mt-3 mb-0">Invoice submitted is certified work value before retention and advance recovery. Subcontractor spent is actual payments made. Blank Manhour Spent uses approved platform hours through {{ now()->toDateString() }} and AED employee rates; an entered zero overrides the platform. These totals do not represent accounting profit.</p>
        </div>
    </div>
</section>
@push('scripts')
<script src="{{ asset('js/project-finance-view.js').'?v='.$financialAssetVersion }}" defer></script>
@endpush
