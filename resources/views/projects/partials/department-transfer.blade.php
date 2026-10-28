<div class="alert alert-success d-none mt-3" id="department-transfer-success" role="status"></div>
<div class="modal fade" id="departmentTransferModal" tabindex="-1" aria-labelledby="departmentTransferTitle" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
        <form class="modal-content" id="department-transfer-form">
            @csrf
            <div class="modal-header">
                <div><h2 class="modal-title h5" id="departmentTransferTitle">Transfer entry department</h2><div class="small text-muted">Move this entry within {{ $project->project_code }}.</div></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close transfer"></button>
            </div>
            <div class="modal-body">
                <div id="transfer-message" role="status" aria-live="polite" class="alert alert-secondary">Loading entry…</div>
                <div id="transfer-content" hidden>
                    <div class="border rounded-3 bg-body-tertiary p-3 mb-3">
                        <div class="d-flex flex-wrap justify-content-between gap-2 mb-2"><strong id="transfer-employee"></strong><span class="badge bg-body border text-body" id="transfer-status"></span></div>
                        <div class="row g-2 small">
                            <div class="col-sm-6">Work date: <strong id="transfer-date"></strong></div>
                            <div class="col-sm-6">Current department: <strong id="transfer-source"></strong></div>
                            <div class="col-sm-6">Regular hours: <strong id="transfer-regular"></strong></div>
                            <div class="col-sm-6">Overtime: <strong id="transfer-overtime"></strong></div>
                            <div class="col-12">Historical category: <strong id="transfer-historical-category"></strong></div>
                            <div class="col-12 text-break" id="transfer-description"></div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="transfer-department">Destination department</label>
                        <select class="form-select" id="transfer-department" required data-searchable="false" aria-describedby="transfer-capacity-help"></select>
                        <div class="form-text" id="transfer-capacity-help">Capacity includes all approved and pending hours, regardless of the page's date filters.</div>
                    </div>
                    <div id="transfer-category-field" class="mb-3" hidden>
                        <label class="form-label" for="transfer-category">Historical Manpower Category</label>
                        <select class="form-select" id="transfer-category" data-searchable="false" aria-describedby="transfer-category-help"></select>
                        <div class="form-text" id="transfer-category-help">This legacy entry is unclassified. Select the category that applied when the work was done; the selection will be audited.</div>
                    </div>
                    <div id="transfer-preview" hidden>
                        <div class="table-responsive mb-2"><table class="table table-sm align-middle"><caption class="visually-hidden">Remaining hours before and after this transfer</caption><thead><tr><th>Remaining hours</th><th class="text-end">Before</th><th class="text-end">After transfer</th></tr></thead><tbody id="transfer-balances"></tbody></table></div>
                    </div>
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('manage.projects.edit', ['project' => $project] + $listContext) }}" target="_blank" rel="noopener">Adjust allocation <span class="small">(new tab)</span></a>
                        <button class="btn btn-sm btn-outline-secondary" type="button" id="transfer-refresh">Refresh preview</button>
                        <span class="small text-muted">After adjusting, return here and refresh. Nothing transfers automatically.</span>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="transfer-reason">Reason for transfer</label>
                        <textarea class="form-control" id="transfer-reason" rows="2" required minlength="5" maxlength="2000" placeholder="Explain why this entry belongs to the destination department."></textarea>
                    </div>
                    <p class="small text-muted">Employee, project, work date, hours, and approval status stay the same. The transfer and your reason are recorded in the audit log.</p>
                    <details><summary class="small">Recent transfer history for this entry</summary><ul class="list-group list-group-flush small mt-2" id="transfer-history"></ul></details>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" id="transfer-save" disabled>Transfer entry</button>
            </div>
        </form>
    </div>
</div>
@push('scripts')
<script>
(() => {
    const byId = id => document.getElementById(id);
    const modalElement = byId('departmentTransferModal');
    const form = byId('department-transfer-form');
    const destination = byId('transfer-department');
    const category = byId('transfer-category');
    const save = byId('transfer-save');
    const message = byId('transfer-message');
    const storageKey = 'project-department-transfer-{{ $project->id }}';
    const successKey = storageKey + '-success';
    let context = null, endpoint = null, preview = null, sequence = 0, saving = false, loading = false;
    const hours = value => Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const remember = () => {
        if (endpoint) sessionStorage.setItem(storageKey, JSON.stringify({ endpoint, destination: destination.value, category: category.value, reason: byId('transfer-reason').value }));
    };
    const tell = (text, kind = 'secondary') => {
        message.textContent = text;
        message.className = 'alert alert-' + kind;
        message.hidden = !text;
    };
    const ready = () => {
        save.disabled = saving || loading || !preview?.can_transfer || byId('transfer-reason').value.trim().length < 5;
        destination.disabled = saving;
        category.disabled = saving;
        byId('transfer-reason').disabled = saving;
        byId('transfer-refresh').disabled = saving;
        modalElement.querySelectorAll('[data-bs-dismiss]').forEach(button => button.disabled = saving);
    };
    async function request(url, options = {}) {
        const response = await fetch(url, { ...options, headers: { 'Accept': 'application/json', ...options.headers }, credentials: 'same-origin', cache: 'no-store' });
        let data;
        try { data = await response.json(); } catch { throw new Error('Your session may have expired. Refresh this page and try again.'); }
        if (!response.ok) throw new Error(Object.values(data.errors || {}).flat().join(' ') || data.message || 'Unable to load this entry. Refresh and try again.');
        return data;
    }
    function showEntry(entry) {
        for (const [id, value] of Object.entries({ employee: entry.employee, date: entry.date, source: entry.department, regular: hours(entry.regular), overtime: hours(entry.overtime), status: entry.status === 'approved' ? 'Approved' : 'Pending approval', description: entry.description || 'No description', 'historical-category': context.categories[entry.category] || 'Legacy / unclassified' })) {
            byId('transfer-' + id).textContent = value;
        }
    }
    function categoryVisibility() {
        const target = context?.departments.find(item => String(item.id) === destination.value);
        const needsCategory = !!target?.controlled && !context.entry.category_known;
        byId('transfer-category-field').hidden = !needsCategory;
        category.required = needsCategory;
    }
    function balanceRow(label, before, after) {
        const row = document.createElement('tr');
        for (const [index, value] of [label, hours(before), hours(after)].entries()) {
            const cell = document.createElement(index === 0 ? 'th' : 'td');
            cell.textContent = value;
            if (index === 0) cell.scope = 'row'; else cell.className = 'text-end font-monospace';
            row.append(cell);
        }
        return row;
    }
    async function refreshPreview() {
        if (!context || saving) return;
        const current = ++sequence;
        preview = null; loading = true; ready(); remember(); categoryVisibility();
        byId('transfer-preview').hidden = true;
        if (!destination.value) { loading = false; tell('Choose a destination to preview the transfer.'); ready(); return; }
        tell('Checking current allocation and entry…');
        const url = new URL(endpoint, location.origin);
        url.searchParams.set('department_id', destination.value);
        if (category.required && category.value) url.searchParams.set('manpower_category', category.value);
        try {
            const data = await request(url);
            if (current !== sequence) return;
            preview = data;
            const selectedDepartment = context.departments.find(item => String(item.id) === destination.value);
            if (selectedDepartment) { selectedDepartment.controlled = data.controlled; selectedDepartment.allocated = true; destination.selectedOptions[0].textContent = data.destination_name; }
            context.entry = data.entry; showEntry(data.entry); categoryVisibility();
            const rows = [balanceRow(data.source_name + ' (source)', data.source_before, data.source_after), balanceRow(data.destination_name + ' (destination)', data.destination_before, data.destination_after)];
            if (data.pool_label) rows.push(balanceRow(data.pool_label, data.pool_before, data.pool_after));
            byId('transfer-balances').replaceChildren(...rows);
            byId('transfer-preview').hidden = false;
            tell(data.blocker || `Ready to transfer ${hours(data.hours)} hours to ${data.destination_name}.`, data.can_transfer ? 'success' : 'warning');
            save.textContent = `Transfer ${hours(data.hours)} hours`;
        } catch (error) {
            if (current !== sequence) return;
            tell(error.message, 'danger');
        } finally {
            if (current === sequence) { loading = false; ready(); }
        }
    }
    async function openTransfer(url, restored = null) {
        if (!window.bootstrap?.Modal) return;
        endpoint = url; context = null; preview = null; loading = true; ++sequence;
        byId('transfer-content').hidden = true; byId('transfer-preview').hidden = true;
        form.reset(); save.textContent = 'Transfer entry'; ready(); tell('Loading entry…');
        bootstrap.Modal.getOrCreateInstance(modalElement).show();
        const current = sequence;
        try {
            const data = await request(endpoint);
            if (current !== sequence) return;
            context = data;
            destination.replaceChildren(new Option('Choose a department', ''), ...context.departments.map(item => new Option(item.name + (item.allocated ? '' : ' — allocation needed'), item.id)));
            category.replaceChildren(new Option('Choose the historical category', ''), ...Object.entries(context.categories).map(([value, label]) => new Option(label, value)));
            destination.value = restored?.destination || ''; category.value = restored?.category || '';
            byId('transfer-reason').value = restored?.reason || '';
            showEntry(context.entry); categoryVisibility();
            const history = context.history.map(item => {
                const li = document.createElement('li'); li.className = 'list-group-item bg-transparent px-0 text-break';
                li.textContent = `${item.at} · ${item.actor}: ${item.from} → ${item.to}. ${item.reason}`; return li;
            });
            if (!history.length) { const li = document.createElement('li'); li.className = 'list-group-item bg-transparent px-0'; li.textContent = 'No previous department transfers.'; history.push(li); }
            byId('transfer-history').replaceChildren(...history);
            byId('transfer-content').hidden = false;
            loading = false; await refreshPreview();
            destination.focus();
        } catch (error) { if (current === sequence) { loading = false; tell(error.message, 'danger'); ready(); } }
    }
    document.querySelectorAll('[data-department-transfer]').forEach(button => button.addEventListener('click', () => openTransfer(button.dataset.departmentTransfer)));
    destination.addEventListener('change', refreshPreview);
    category.addEventListener('change', refreshPreview);
    byId('transfer-refresh').addEventListener('click', refreshPreview);
    byId('transfer-reason').addEventListener('input', () => { remember(); ready(); });
    modalElement.addEventListener('hide.bs.modal', event => { if (saving) event.preventDefault(); });
    modalElement.addEventListener('hidden.bs.modal', () => { ++sequence; sessionStorage.removeItem(storageKey); endpoint = null; preview = null; });
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (save.disabled || !form.reportValidity()) return;
        saving = true; ready(); tell('Transferring entry…'); save.textContent = 'Transferring…';
        try {
            const data = { department_id: destination.value, version: preview.version, reason: byId('transfer-reason').value.trim() };
            if (category.required) data.manpower_category = category.value;
            await request(endpoint, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value }, body: JSON.stringify(data) });
            sessionStorage.removeItem(storageKey);
            sessionStorage.setItem(successKey, 'Entry transferred. Department balances and project reports have been refreshed.');
            location.hash = 'entry-review-docket'; location.reload();
        } catch (error) {
            preview = null; tell(error.message + ' Use Refresh preview before trying again.', 'danger');
            saving = false; save.textContent = 'Transfer entry'; ready();
        }
    });
    const success = sessionStorage.getItem(successKey);
    if (success) { const banner = byId('department-transfer-success'); banner.textContent = success; banner.classList.remove('d-none'); byId('entry-review-docket')?.before(banner); sessionStorage.removeItem(successKey); }
    try {
        const restored = JSON.parse(sessionStorage.getItem(storageKey) || 'null');
        if (restored && Array.from(document.querySelectorAll('[data-department-transfer]')).some(button => button.dataset.departmentTransfer === restored.endpoint)) openTransfer(restored.endpoint, restored);
    } catch { sessionStorage.removeItem(storageKey); }
})();
</script>
@endpush
