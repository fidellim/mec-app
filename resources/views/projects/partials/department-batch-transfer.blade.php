<div class="modal fade" id="departmentBatchTransferModal" tabindex="-1" aria-labelledby="departmentBatchTransferTitle" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-fullscreen-sm-down">
        <form class="modal-content" id="batch-transfer-form">
            @csrf
            <div class="modal-header">
                <div><h2 class="modal-title h5" id="departmentBatchTransferTitle">Update selected weekly entries</h2><div class="small text-muted" id="batch-transfer-week"></div></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close bulk transfer"></button>
            </div>
            <div class="modal-body">
                <div id="batch-transfer-message" class="alert alert-secondary" role="status" aria-live="polite">Loading selection…</div>
                <div id="batch-transfer-content" hidden>
                    <div class="border rounded-3 bg-body-tertiary p-3 mb-3">
                        <strong id="batch-transfer-employee"></strong>
                        <div class="small mt-2" id="batch-transfer-summary"></div>
                    </div>
                    <div class="mb-3"><label class="form-label" for="batch-transfer-mode">Update type</label><select class="form-select" id="batch-transfer-mode" data-searchable="false"><option value="transfer">Change department and optionally categories</option><option value="category">Change categories only — keep each department</option></select></div>
                    <div class="mb-3" id="batch-transfer-department-field">
                        <label class="form-label" for="batch-transfer-department">Destination department for all selected entries</label>
                        <select class="form-select" id="batch-transfer-department" required data-searchable="false"></select>
                        <div class="form-text">Choose one destination. Entries staying in their current department need a category change; remove any unchanged entries.</div>
                    </div>
                    <div id="batch-transfer-category-tools" class="border rounded-3 p-3 mb-3" hidden>
                        <label class="form-label" for="batch-transfer-category-all">Apply a new category to all selected entries</label>
                        <div class="d-flex flex-wrap gap-2"><select class="form-select flex-grow-1" style="width:auto" id="batch-transfer-category-all" data-searchable="false"></select><button class="btn btn-outline-secondary" type="button" id="batch-transfer-apply-category">Apply category</button></div>
                        <div class="form-text">This sets the proposed category for every selected entry. Review the recorded category and adjust individual proposals below.</div>
                    </div>
                    <p class="small text-muted" id="batch-transfer-selection-help">Only selected entries will be updated. Remove any entry that should remain unchanged.</p>
                    <div class="table-responsive mb-3">
                        <table class="project-responsive-table table table-sm align-middle" aria-label="Selected entries to transfer" aria-describedby="batch-transfer-selection-help"><thead><tr><th>Date / entry</th><th>Current department</th><th class="text-end">Regular</th><th class="text-end">Overtime</th><th>Recorded → proposed category</th><th>Actions</th></tr></thead><tbody id="batch-transfer-entries"></tbody></table>
                    </div>
                    <div id="batch-transfer-preview" hidden>
                        <div class="table-responsive mb-3"><table class="table table-sm align-middle"><caption class="visually-hidden">Combined remaining hours before and after the bulk transfer</caption><thead><tr><th>Remaining hours</th><th class="text-end">Before</th><th class="text-end">After transfer</th></tr></thead><tbody id="batch-transfer-balances"></tbody></table></div>
                    </div>
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('manage.projects.edit', ['project' => $project] + $listContext) }}" target="_blank" rel="noopener">Adjust allocation (new tab)</a>
                        <button class="btn btn-sm btn-outline-secondary" type="button" id="batch-transfer-refresh">Refresh preview</button>
                        <span class="small text-muted">Selection and reason stay here while you adjust allocation. Refresh to check the whole selection again.</span>
                    </div>
                    <label class="form-label" for="batch-transfer-reason">Reason for this batch change</label>
                    <textarea class="form-control mb-2" id="batch-transfer-reason" rows="2" required minlength="5" maxlength="2000" placeholder="Explain the department or category corrections for this selection."></textarea>
                    <p class="small text-muted mb-0">All selected entries update together. If any entry or allocation changes, nothing is saved until you refresh and confirm. Hours, approval status, and current employee project assignments remain unchanged. Each entry receives an audit record linked to this batch.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" id="batch-transfer-save" disabled>Transfer selected entries</button>
            </div>
        </form>
    </div>
</div>
@push('scripts')
<script>
(() => {
    const byId = id => document.getElementById(id);
    const modal = byId('departmentBatchTransferModal');
    const form = byId('batch-transfer-form');
    const destination = byId('batch-transfer-department');
    const save = byId('batch-transfer-save');
    const reason = byId('batch-transfer-reason');
    const mode = byId('batch-transfer-mode');
    const categoryOnly = () => mode.value === 'category';
    const message = byId('batch-transfer-message');
    const key = 'project-department-batch-transfer-{{ $project->id }}';
    let endpoint = null, packet = null, context = null, ids = [], classifications = {}, preview = null, loading = false, saving = false, sequence = 0;
    const hours = number => Number(number).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const selected = block => [...block.querySelectorAll('[data-batch-entry]:checked')];
    function updatePacket(block) {
        const rows = selected(block);
        block.querySelector('[data-batch-selection-summary]').textContent = `${rows.length} entries selected · ${hours(rows.reduce((sum, row) => sum + Number(row.dataset.hours), 0))} hours`;
        block.querySelector('[data-batch-open]').disabled = rows.length === 0 || rows.length > 50;
    }
    function setMode(block, enabled) {
        block.querySelector('[data-batch-controls]').hidden = !enabled;
        const toggle = block.querySelector('[data-batch-mode]');
        toggle.setAttribute('aria-expanded', String(enabled)); toggle.textContent = enabled ? 'Exit selection' : 'Select entries to transfer / reclassify';
        block.querySelectorAll('[data-transfer-column]').forEach(item => { item.hidden = !enabled; item.classList.toggle('d-none', !enabled); });
        block.querySelectorAll('[data-correction-select], [data-correction-controls]').forEach(item => { item.hidden = enabled; item.classList.toggle('d-none', enabled); });
        if (!enabled) block.querySelectorAll('[data-batch-entry]').forEach(input => input.checked = false);
        updatePacket(block);
    }
    const tell = (text, kind = 'secondary') => { message.textContent = text; message.className = 'alert alert-' + kind; };
    const remember = () => {
        if (endpoint) sessionStorage.setItem(key, JSON.stringify({ endpoint, ids, classifications, destination: destination.value, mode: mode.value, reason: reason.value }));
    };
    function ready() {
        save.disabled = saving || loading || !preview?.can_transfer || reason.value.trim().length < 5;
        modal.querySelectorAll('input, select, textarea, button, a').forEach(control => {
            if (control !== save) {
                if (control.tagName === 'A') { control.classList.toggle('pe-none', saving); control.setAttribute('aria-disabled', String(saving)); }
                else control.disabled = saving;
            }
        });
    }
    async function request(url, options = {}) {
        const response = await fetch(url, { ...options, credentials: 'same-origin', cache: 'no-store', headers: { 'Accept': 'application/json', ...options.headers } });
        let data;
        try { data = await response.json(); } catch { throw new Error('Your session may have expired. Refresh this page and try again.'); }
        if (!response.ok) throw new Error(Object.values(data.errors || {}).flat().join(' ') || data.message || 'Unable to check this selection. Refresh and try again.');
        return data;
    }
    function queryUrl(withDestination = false) {
        const url = new URL(endpoint, location.origin);
        ids.forEach(id => url.searchParams.append('entry_ids[]', id));
        if (withDestination) {
            if (categoryOnly()) url.searchParams.set('category_only', '1'); else url.searchParams.set('department_id', destination.value);
            context.entries.forEach(entry => {
                if (classifications[entry.id]) url.searchParams.set(`classifications[${entry.id}]`, classifications[entry.id]);
            });
        }
        return url;
    }
    function isControlled(entry = null) { return !!context?.departments.find(item => String(item.id) === String(categoryOnly() ? entry?.department_id : destination.value))?.controlled; }
    function categoryOptions(select, placeholder) {
        select.replaceChildren(new Option(placeholder, ''), ...Object.entries(context.categories).map(([value, label]) => new Option(label, value)));
    }
    function renderEntries() {
        const focusedCategory = document.activeElement?.dataset.batchCategory;
        const focusedRemove = document.activeElement?.dataset.batchRemove;
        const labels = ['Date / entry', 'Current department', 'Regular', 'Overtime'];
        const rows = context.entries.map(entry => {
            const row = document.createElement('tr');
            const values = [`${entry.date} · #${entry.id}`, entry.department, hours(entry.regular), hours(entry.overtime)];
            values.forEach((value, index) => { const cell = document.createElement('td'); cell.textContent = value; cell.dataset.label = labels[index]; if (index > 1) cell.className = 'text-end font-monospace'; row.append(cell); });
            const categoryCell = document.createElement('td');
            categoryCell.dataset.label = 'Historical category';
            const recorded = document.createElement('div'); recorded.className = 'small text-muted mb-1'; recorded.textContent = 'Recorded: ' + (context.categories[entry.category] || 'Legacy / unclassified'); categoryCell.append(recorded);
            const select = document.createElement('select'); select.className = 'form-select form-select-sm'; select.dataset.searchable = 'false'; select.dataset.batchCategory = entry.id;
            select.setAttribute('aria-label', `Proposed category for entry ${entry.id}, ${entry.date}`); select.required = categoryOnly() || (isControlled(entry) && !entry.category_known);
            categoryOptions(select, entry.category_known ? 'Keep recorded category' : 'Keep unclassified'); select.value = classifications[entry.id] ?? (entry.category_known ? entry.category : '');
            select.addEventListener('change', () => { classifications[entry.id] = select.value; refreshPreview(); });
            categoryCell.append(select);
            row.append(categoryCell);
            const actions = document.createElement('td'), remove = document.createElement('button');
            actions.dataset.label = 'Actions';
            remove.type = 'button'; remove.className = 'btn btn-sm btn-outline-secondary'; remove.textContent = 'Remove'; remove.dataset.batchRemove = entry.id;
            remove.setAttribute('aria-label', `Remove entry ${entry.id} from transfer selection`);
            remove.addEventListener('click', () => {
                ids = ids.filter(id => Number(id) !== entry.id); delete classifications[entry.id];
                context.entries = context.entries.filter(item => item.id !== entry.id);
                if (packet) { packet.querySelectorAll('[data-batch-entry]').forEach(input => input.checked = ids.includes(Number(input.dataset.batchEntry))); updatePacket(packet); }
                renderEntries(); refreshPreview();
            });
            actions.append(remove); row.append(actions); return row;
        });
        byId('batch-transfer-entries').replaceChildren(...rows);
        if (focusedCategory) byId('batch-transfer-entries').querySelector(`[data-batch-category="${focusedCategory}"]`)?.focus();
        if (focusedRemove) (byId('batch-transfer-entries').querySelector(`[data-batch-remove="${focusedRemove}"]`) || byId('batch-transfer-entries').querySelector('[data-batch-remove]') || destination).focus();
        const regular = context.entries.reduce((sum, entry) => sum + Number(entry.regular), 0), overtime = context.entries.reduce((sum, entry) => sum + Number(entry.overtime), 0);
        byId('batch-transfer-summary').textContent = `${ids.length} entries · ${hours(regular)} regular + ${hours(overtime)} overtime = ${hours(regular + overtime)} hours`;
        byId('batch-transfer-category-tools').hidden = false;
        byId('batch-transfer-department-field').hidden = categoryOnly(); destination.required = !categoryOnly();
    }
    function balanceRow(label, before, after) {
        const row = document.createElement('tr');
        [label, hours(before), hours(after)].forEach((value, index) => { const cell = document.createElement(index ? 'td' : 'th'); cell.textContent = value; if (index) cell.className = 'text-end font-monospace'; else cell.scope = 'row'; row.append(cell); });
        return row;
    }
    async function refreshPreview() {
        if (!context || saving) return;
        const current = ++sequence;
        preview = null; loading = true; ready(); remember(); renderEntries(); byId('batch-transfer-preview').hidden = true;
        if (!ids.length || (!categoryOnly() && !destination.value)) { loading = false; tell(ids.length ? 'Choose a destination to check the combined hours.' : 'No entries selected. Cancel and select entries from the weekly packet.'); ready(); return; }
        if (categoryOnly() && context.entries.every(entry => !classifications[entry.id] || classifications[entry.id] === entry.category)) { loading = false; tell('Choose new categories for the selected entries, or apply a category to all. Remove any entry that does not need changing.'); ready(); return; }
        tell('Checking the whole selection and allocation pools…');
        try {
            const data = await request(queryUrl(true)); if (current !== sequence) return;
            preview = data;
            const department = context.departments.find(item => String(item.id) === destination.value);
            if (!categoryOnly() && department) { department.controlled = data.controlled; department.allocated = true; destination.selectedOptions[0].textContent = data.destination_name; }
            data.entries.forEach(item => { const target = context.departments.find(row => row.id === item.destination_id); if (target) { target.controlled = item.controlled; target.allocated = true; } });
            context.entries = data.entries.map(item => item.entry); renderEntries();
            const rows = data.departments.map(item => balanceRow(item.label, item.before, item.after));
            data.pools.forEach(pool => rows.push(balanceRow(pool.label, pool.before, pool.after)));
            byId('batch-transfer-balances').replaceChildren(...rows); byId('batch-transfer-preview').hidden = false;
            tell(data.can_transfer ? (data.category_only ? `Ready to correct categories for all ${data.count} entries. Each entry stays in its current department.` : `Ready to transfer all ${data.count} entries (${hours(data.hours)} hours) to ${data.destination_name}.`) : data.blockers.join(' '), data.can_transfer ? 'success' : 'warning');
            save.textContent = data.category_only ? `Correct ${data.count} categories · ${hours(data.hours)} h` : `Transfer ${data.count} entries · ${hours(data.hours)} h`;
        } catch (error) { if (current === sequence) tell(error.message, 'danger'); }
        finally { if (current === sequence) { loading = false; ready(); } }
    }
    async function open(block, restored = null) {
        if (!window.bootstrap?.Modal) return;
        packet = block; endpoint = block.dataset.batchEndpoint; ids = restored?.ids.map(Number) || selected(block).map(input => Number(input.dataset.batchEntry));
        classifications = restored?.classifications || {}; context = null; preview = null; loading = true; const current = ++sequence;
        form.reset(); mode.value = restored?.mode || 'transfer'; save.textContent = 'Transfer selected entries'; byId('batch-transfer-content').hidden = true; byId('batch-transfer-preview').hidden = true;
        ready(); tell('Loading selection…'); bootstrap.Modal.getOrCreateInstance(modal).show();
        try {
            const data = await request(queryUrl()); if (current !== sequence) return;
            context = data;
            byId('batch-transfer-employee').textContent = data.employee; byId('batch-transfer-week').textContent = data.week + ' · ' + @js($project->project_code);
            destination.replaceChildren(new Option('Choose a department', ''), ...data.departments.map(item => new Option(item.name + (item.allocated ? '' : ' — allocation needed'), item.id)));
            categoryOptions(byId('batch-transfer-category-all'), 'Choose category to apply');
            destination.value = restored?.destination || ''; reason.value = restored?.reason || '';
            byId('batch-transfer-content').hidden = false; loading = false; renderEntries(); await refreshPreview(); destination.focus();
        } catch (error) { if (current === sequence) { loading = false; tell(error.message, 'danger'); ready(); } }
    }
    document.querySelectorAll('[data-weekly-transfer]').forEach(block => {
        block.querySelector('[data-batch-mode]').addEventListener('click', () => setMode(block, block.querySelector('[data-batch-controls]').hidden));
        block.querySelectorAll('[data-batch-entry]').forEach(input => input.addEventListener('change', () => updatePacket(block)));
        const eligible = [...block.querySelectorAll('[data-batch-entry]:not(:disabled)')];
        if (eligible.length > 50) block.querySelector('[data-batch-select-all]').textContent = 'Select first 50 eligible entries';
        block.querySelector('[data-batch-select-all]').addEventListener('click', () => { eligible.forEach((input, index) => input.checked = index < 50); updatePacket(block); });
        block.querySelector('[data-batch-clear]').addEventListener('click', () => { block.querySelectorAll('[data-batch-entry]').forEach(input => input.checked = false); updatePacket(block); });
        block.querySelector('[data-batch-open]').addEventListener('click', () => open(block));
    });
    destination.addEventListener('change', refreshPreview);
    mode.addEventListener('change', refreshPreview);
    byId('batch-transfer-refresh').addEventListener('click', refreshPreview);
    byId('batch-transfer-apply-category').addEventListener('click', () => {
        const value = byId('batch-transfer-category-all').value;
        if (value && context) { context.entries.forEach(entry => classifications[entry.id] = value); refreshPreview(); }
    });
    reason.addEventListener('input', () => { remember(); ready(); });
    modal.addEventListener('hide.bs.modal', event => { if (saving) event.preventDefault(); });
    modal.addEventListener('hidden.bs.modal', () => { ++sequence; sessionStorage.removeItem(key); endpoint = null; preview = null; });
    form.addEventListener('submit', async event => {
        event.preventDefault(); if (save.disabled || !form.reportValidity()) return;
        saving = true; ready(); tell('Saving the whole selection…');
        const categoryData = {};
        context.entries.forEach(entry => { if (classifications[entry.id]) categoryData[entry.id] = classifications[entry.id]; });
        try {
            const result = await request(endpoint, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value }, body: JSON.stringify({ entry_ids: ids, category_only: categoryOnly(), ...(categoryOnly() ? {} : { department_id: destination.value }), classifications: categoryData, version: preview.version, reason: reason.value.trim() }) });
            sessionStorage.removeItem(key);
            sessionStorage.setItem('project-department-transfer-{{ $project->id }}-success', preview.category_only ? `${result.count} entry categories corrected together. Each department and all hours were preserved.` : `${result.count} entries (${hours(result.hours)} hours) transferred together. Department and category changes were recorded.`);
            location.hash = 'entry-review-docket'; location.reload();
        } catch (error) { preview = null; saving = false; tell(error.message + ' Refresh the preview and check the current departments before trying again.', 'danger'); ready(); }
    });
    try {
        const restored = JSON.parse(sessionStorage.getItem(key) || 'null');
        const block = [...document.querySelectorAll('[data-weekly-transfer]')].find(item => item.dataset.batchEndpoint === restored?.endpoint);
        if (block && Array.isArray(restored.ids) && restored.ids.length) {
            setMode(block, true); block.querySelectorAll('[data-batch-entry]').forEach(input => input.checked = !input.disabled && restored.ids.map(Number).includes(Number(input.dataset.batchEntry))); updatePacket(block); open(block, restored);
        }
    } catch { sessionStorage.removeItem(key); }
})();
</script>
@endpush
