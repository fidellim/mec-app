<section class="content-card mb-3" data-finance-panel data-project-code="{{ $project->project_code }}" data-cutoff="{{ $analytics['to'] }}" data-worker-url="{{ asset('js/project-finance-worker.js').'?v='.$financialAssetVersion }}" aria-labelledby="project-finance-heading">
    <div class="content-card-header analytics-heading">
        <div><h2 class="h5 mb-1" id="project-finance-heading">Project financial position</h2><p class="small text-muted mb-0">Cumulative as of <strong>{{ \Illuminate\Support\Carbon::parse($analytics['to'])->format('j M Y') }}</strong> · AED, excluding VAT</p></div>
        <div class="d-flex flex-wrap align-items-center gap-3"><span class="badge text-bg-secondary">{{ $project->project_code }}</span><button type="button" class="btn btn-outline-secondary btn-sm" data-finance-clear disabled>Clear project figures</button></div>
    </div>
    <div class="content-card-body">
        <details class="analytics-import" data-finance-import open>
            <summary class="fw-semibold">Load project finance summary</summary>
            <div class="form-check mt-3 mb-3"><input type="checkbox" class="form-check-input" id="finance-cutoff-confirm" data-finance-confirm><label class="form-check-label" for="finance-cutoff-confirm">My Excel totals are cumulative through {{ \Illuminate\Support\Carbon::parse($analytics['to'])->format('j M Y') }}.</label></div>
            <label class="form-label" for="finance-workbook">Finance workbook</label><input type="file" class="form-control" id="finance-workbook" accept=".xlsx,.xls" data-finance-file aria-describedby="finance-help">
            <p class="small text-muted mt-2 mb-1" id="finance-help">Columns: Project Code, Order Value, Invoice Submitted, Subcontractor Spent, Subcontractor Committed Value, Manhour Spent. Only {{ $project->project_code }} is used. Excel up to 5 MB.</p>
            <p class="small text-muted mb-0">Read in this browser only; never uploaded or saved. Leaving, refreshing, logging out, clearing, or 15 minutes removes these figures.</p>
        </details>
        <p class="small mt-3 mb-0" data-finance-status role="status" aria-live="polite">Load a workbook to view this project’s financial position.</p>
        <div data-finance-results hidden>
            <div class="finance-position mt-4">
                <section aria-labelledby="finance-position-heading"><h3 class="h6 mb-1" id="finance-position-heading">Contract, invoicing &amp; spending</h3><p class="small text-muted mb-3">Three totals on the same AED scale.</p><div data-finance-billing></div></section>
                <section class="finance-balances" aria-labelledby="finance-balances-heading"><h3 class="h6 mb-3" id="finance-balances-heading">Current balances</h3><dl>
                    @foreach(['balance' => 'Balance for invoicing', 'orderLessCost' => 'Order value less cost spent', 'invoiceLessCost' => 'Invoice submitted less cost spent'] as $key => $label)
                    <div><dt>{{ $label }}</dt><dd data-finance-value="{{ $key }}">—</dd></div>
                    @endforeach
                </dl><p class="small text-muted mb-0">Balances still need to cover further project costs. They are not profit.</p></section>
            </div>
            <p class="small analytics-finance-note mt-3" data-finance-note></p>
            <div class="finance-detail-grid mt-4">
                <section aria-labelledby="finance-spend-heading"><h3 class="h6 mb-1" id="finance-spend-heading">What makes up cost spent</h3><p class="small text-muted mb-3">Approved labour, actual subcontractor payments and allocated overhead.</p><div data-finance-spend></div></section>
                <section aria-labelledby="finance-commitment-heading"><h3 class="h6 mb-1" id="finance-commitment-heading">Subcontractor commitments</h3><p class="small text-muted mb-3">The approved contract value includes payments already made.</p><div data-finance-commitment></div><p class="small text-warning-emphasis mt-2 mb-0" data-finance-commitment-warning hidden></p></section>
            </div>
            <div class="finance-exposure mt-4"><div><span class="fw-semibold">Cost spent + remaining subcontractor commitment</span><p class="small text-muted mb-0">Includes the unpaid approved subcontract balance once. Future labour costs are excluded.</p></div><strong data-finance-value="exposure">—</strong></div>
            <details class="small text-muted mt-3"><summary>How to read these figures</summary><p class="mt-2 mb-1">Invoice submitted is certified work value before retention and advance recovery. Overhead = invoice submitted × 8%. Subcontractor spent is payments made; committed value includes approved variations and amounts already paid.</p><p class="mb-0">Blank Manhour Spent uses cumulative approved platform hours through {{ $analytics['to'] }} and AED employee rates; zero is an override. Blank subcontractor commitment means unknown. From date affects hours analytics only. Changing To date requires importing finance totals again. These figures are a current position, not accounting profit or a forecast.</p></details>
        </div>
    </div>
</section>
@push('scripts')
<script src="{{ asset('js/project-finance-view.js').'?v='.$financialAssetVersion }}" defer></script>
@endpush
