<div class="border rounded-3 p-3 mt-3" id="pool-conversion-review" hidden data-endpoint="{{ route('manage.projects.pool-conversion.preview', $project) }}">
    <input type="hidden" name="pool_conversion_version" value="" id="pool-conversion-version">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
        <div><h2 class="h6 mb-1">Move shared hours into reservations</h2><p class="small text-muted mb-0">Review recorded hours before saving. Categories and approval status stay the same. Consumed legacy or incomplete entries must be classified first.</p></div>
        <button type="button" class="btn btn-outline-secondary" id="pool-conversion-preview">Review conversion</button>
    </div>
    <div class="small mt-3" id="pool-conversion-status" role="status" aria-live="polite">Review the conversion after setting the reservations and department totals.</div>
    <div id="pool-conversion-results" class="mt-3"></div>
    <a class="small d-inline-block mt-2" href="{{ route('projects.utilization', ['project' => $project, 'review_category' => 'legacy']) }}#entry-review-docket" target="_blank" rel="noopener">Review Legacy / Unclassified entries</a>
    <a class="small d-inline-block mt-2 ms-sm-3" href="{{ route('projects.utilization', $project) }}#entry-review-docket" target="_blank" rel="noopener">Review all entries, including incomplete pools</a>
    @error('pool_conversion_version')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
</div>
<style>
@media (max-width: 575.98px) {
    #pool-conversion-results .table-responsive { overflow: visible; }
    #pool-conversion-results table, #pool-conversion-results tbody { display: block; }
    #pool-conversion-results thead { display: none; }
    #pool-conversion-results tr { display: block; border: 1px solid var(--bs-border-color); border-radius: .5rem; padding: .5rem; margin-bottom: .5rem; }
    #pool-conversion-results td { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: .75rem; border: 0; overflow-wrap: anywhere; }
    #pool-conversion-results td::before { content: attr(data-label); font-weight: 600; color: var(--bs-secondary-color); }
}
</style>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const panel = document.getElementById('pool-conversion-review');
    const form = panel.closest('form');
    const token = document.getElementById('pool-conversion-version');
    const button = document.getElementById('pool-conversion-preview');
    const status = document.getElementById('pool-conversion-status');
    const results = document.getElementById('pool-conversion-results');
    let sequence = 0;
    const hours = value => Number(value).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
    const needsReview = () => [...form.querySelectorAll('[data-shared-conversion-source="1"]')].some(mode => {
        const department = mode.closest('[data-department-allocation]');
        return mode.value === 'reserved' && department.querySelector('[data-job-level-toggle]').checked && department.querySelector('[data-department-hours]').value !== '';
    });
    const invalidate = () => {
        ++sequence; token.value = ''; results.replaceChildren(); panel.hidden = !needsReview();
        status.textContent = 'Settings changed. Review the conversion before saving.';
        status.className = 'small mt-3 text-muted'; button.disabled = false;
    };
    form.addEventListener('input', event => { if (event.target.closest('[data-department-allocation]')) invalidate(); });
    form.addEventListener('change', event => { if (event.target.closest('[data-department-allocation]')) invalidate(); });
    const renderTable = (headings, rows) => {
        const wrapper = document.createElement('div'); wrapper.className = 'table-responsive mb-3';
        const table = document.createElement('table'); table.className = 'table table-sm align-middle mb-0';
        const head = table.createTHead().insertRow(); headings.forEach(text => { const th=document.createElement('th'); th.scope='col'; th.textContent=text; head.append(th); });
        const body=table.createTBody(); rows.forEach(values => { const row=body.insertRow(); values.forEach((value,index) => { const td=row.insertCell(); td.dataset.label=headings[index]; td.textContent=value; }); });
        wrapper.append(table); results.append(wrapper);
    };
    button.addEventListener('click', async () => {
        const current=++sequence; token.value=''; button.disabled=true; results.replaceChildren();
        status.textContent='Checking recorded hours and proposed allocations…'; status.className='small mt-3 text-muted';
        const data=new FormData(form); data.delete('_method');
        try {
            const response=await fetch(panel.dataset.endpoint,{method:'POST',headers:{Accept:'application/json','X-CSRF-TOKEN':form.querySelector('[name="_token"]').value},body:data});
            const body=await response.json(); if(current!==sequence)return;
            if(!response.ok)throw new Error(Object.values(body.errors || {}).flat().join(' ') || body.message || 'Preview could not be loaded.');
            renderTable(['Department','Category','Entries','Approved to move','Pending to move','Reservation','Reserved remaining'],body.conversions.map(row=>[row.department,row.label,row.count,hours(row.approved),hours(row.pending),hours(row.reservation),hours(row.reserved_remaining)]));
            renderTable(['Department','Total allocation','Consumed','Remaining','Shared allocation','Shared consumption after conversion','Shared remaining'],body.balances.map(row=>[row.department,hours(row.allocation),hours(row.consumed),hours(row.remaining),hours(row.shared_allocation),hours(row.shared_consumed),hours(row.shared_remaining)]));
            if(!body.valid)throw new Error(Object.values(body.errors).flat().join(' '));
            token.value=body.version; status.textContent='Ready to save. Save Project will apply these conversions and allocation changes together. The allocation change reason is recorded for each entry.';
            status.className='small mt-3 text-success-emphasis';
        } catch(error) { if(current===sequence) { status.textContent=error.message; status.className='small mt-3 text-danger'; } }
        finally { if(current===sequence)button.disabled=false; }
    });
    form.addEventListener('submit',event=>{
        if(needsReview()&&!token.value){event.preventDefault();event.stopImmediatePropagation();panel.hidden=false;status.textContent='Review the Shared to Reserved conversion before saving.';status.className='small mt-3 text-danger';button.focus();}
    },true);
    panel.hidden=!needsReview();
});
</script>
@endpush
