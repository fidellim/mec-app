/* Financial inputs/results live only in this document and its dedicated worker.
 * Never attach them to forms, URLs, storage, telemetry, or network requests. */
(() => {
    'use strict';
    let root = document.getElementById('project-analytics');
    if (!root) return;
    let worker = null, result = null, expiry = null, expiresAt = 0, importTimer = null, busy = false;
    let epoch = 0, management = null;
    const find = selector => root.querySelector(selector);
    const panel = () => find('[data-financial-panel]');
    const status = message => { const element = find('[data-cost-status]'); if (element) element.textContent = message; };
    const hourText = value => `${(Number(value) / 100).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} hrs`;
    const money = value => {
        const cents = BigInt(value), absolute = cents < 0n ? -cents : cents;
        return `${find('[data-cost-currency]').value} ${cents < 0n ? '-' : ''}${(absolute / 100n).toLocaleString()}.${String(absolute % 100n).padStart(2, '0')}`;
    };
    function clear(message = 'Financial data cleared. Load your workbook again when needed.') {
        epoch++;
        worker?.terminate(); worker = null; result = null; busy = false; expiresAt = 0;
        management = null;
        document.dispatchEvent(new CustomEvent('project-labour:updated', { detail: null }));
        clearTimeout(expiry); clearTimeout(importTimer); expiry = null; importTimer = null;
        if (!panel()) return;
        find('[data-cost-file]').value = '';
        find('[data-cost-file]').disabled = false;
        find('[data-cost-clear]').disabled = true;
        find('[data-cost-badge]').textContent = 'Rates not loaded';
        find('[data-cost-results]').hidden = true;
        find('[data-cost-warning]').hidden = true;
        find('[data-cost-warning]').textContent = '';
        find('[data-cost-coverage]').textContent = '';
        find('[data-cost-matches]').textContent = '';
        root.querySelectorAll('[data-cost-value]').forEach(element => { element.textContent = '—'; });
        find('[data-cost-months]').replaceChildren(); find('[data-cost-drivers]').replaceChildren();
        status(message);
    }
    function table(rows, heading, showBars = false) {
        const table = document.createElement('table'); table.className = 'table table-sm analytics-cost-table mb-0';
        const caption = table.createCaption(); caption.className = 'visually-hidden'; caption.textContent = heading;
        const header = table.createTHead().insertRow();
        for (const title of [heading, 'Approved', 'Pending', 'Total']) {
            const th = document.createElement('th'); th.scope = 'col'; th.textContent = title;
            if (title !== heading) th.className = 'text-end'; header.append(th);
        }
        const body = table.createTBody();
        const max = rows.reduce((value, row) => BigInt(row.total) > value ? BigInt(row.total) : value, 1n);
        for (const item of rows) {
            const tr = body.insertRow(), th = document.createElement('th'); th.scope = 'row'; th.textContent = item.label; tr.append(th);
            if (showBars) {
                const bar = document.createElement('div'); bar.className = 'analytics-bar'; bar.setAttribute('aria-hidden', 'true');
                const fill = document.createElement('span'); fill.style.width = `${Number(BigInt(item.total) * 10000n / max) / 100}%`; bar.append(fill); th.append(bar);
            }
            if (BigInt(item.unpricedHours) > 0n) {
                const note = document.createElement('div'); note.className = 'small text-warning-emphasis fw-normal'; note.textContent = `${hourText(item.unpricedHours)} unpriced`; th.append(note);
            }
            for (const key of ['approved', 'pending', 'total']) { const td = tr.insertCell(); td.className = `text-end text-nowrap${key === 'total' ? ' fw-semibold' : ''}`; td.textContent = money(item[key]); }
        }
        if (!rows.length) { const cell = body.insertRow().insertCell(); cell.colSpan = 4; cell.className = 'text-muted py-3'; cell.textContent = 'No recorded hours in this period.'; }
        return table;
    }
    function render() {
        if (!result || !panel()) return;
        document.dispatchEvent(new CustomEvent('project-labour:updated', { detail: { result: management, currency: find('[data-cost-currency]').value } }));
        find('[data-cost-results]').hidden = false;
        find('[data-cost-badge]').textContent = BigInt(result.unpricedHours) ? 'Partially priced' : 'Rates loaded · local only';
        root.querySelectorAll('[data-cost-value]').forEach(element => { element.textContent = money(result[element.dataset.costValue]); });
        const total = BigInt(result.pricedHours) + BigInt(result.unpricedHours);
        const percentage = total ? Number(BigInt(result.pricedHours) * 10000n / total) / 100 : 0;
        find('[data-cost-coverage]').textContent = `Rate coverage: ${percentage.toFixed(2)}% of recorded hours`;
        find('[data-cost-matches]').textContent = `${result.matched} employees matched · ${result.imported} rates imported · ${hourText(result.unpricedHours)} unpriced`;
        const warnings = [];
        if (result.missing.length) warnings.push(`Missing rates for ${result.missing.length} employee identifiers. Monetary figures cover priced hours only. IDs: ${result.missing.slice(0, 10).join(', ')}${result.missing.length > 10 ? '…' : ''}.`);
        if (result.mismatched.length) warnings.push(`Names differ for ${result.mismatched.length} matched Employee IDs. Verify the workbook: ${result.mismatched.slice(0, 10).join(', ')}.`);
        find('[data-cost-warning]').hidden = !warnings.length;
        find('[data-cost-warning]').textContent = warnings.join(' ');
        find('[data-cost-months]').replaceChildren(table(result.months, 'Month', true));
        const group = find('[data-cost-group]').value;
        find('[data-cost-drivers]').replaceChildren(table(result[group], group === 'employees' ? 'Employee' : group === 'categories' ? 'Manpower category' : 'Department', true));
        find('[data-cost-clear]').disabled = false;
    }
    function basis() { return JSON.parse(find('#project-cost-basis').textContent); }
    async function loadFile(input) {
        const file = input.files?.[0]; input.value = '';
        if (!file) return;
        clear('Reading workbook locally…');
        if (!find('[data-cost-currency]').value) { status('Choose the rate currency, then select the workbook again.'); return; }
        if (!/\.(xlsx|xls)$/i.test(file.name) || file.size > 5 * 1024 * 1024 || file.size === 0) { status('Select a nonempty Excel .xlsx or .xls workbook up to 5 MB.'); return; }
        busy = true; input.disabled = true; find('[data-cost-clear]').disabled = false;
        const operation = epoch;
        try {
            worker = new Worker(panel().dataset.workerUrl);
            worker.onmessage = ({ data }) => {
                if (epoch !== operation) return;
                clearTimeout(importTimer); importTimer = null; busy = false; find('[data-cost-file]').disabled = false;
                if (data.type === 'error') { clear(data.message); return; }
                if (expiresAt && Date.now() >= expiresAt) { clear('The financial session expired. Reload your workbook to continue.'); return; }
                result = data.result;
                management = data.management;
                if (!expiresAt) {
                    expiresAt = Date.now() + 15 * 60 * 1000;
                    expiry = setTimeout(() => clear('The 15-minute financial session expired. Reload your workbook to continue.'), 15 * 60 * 1000);
                }
                render(); status('Rates loaded locally. Financial data clears after 15 minutes, or when you leave, refresh, log out, or choose Clear financial data.');
            };
            worker.onerror = () => clear('The local Excel reader could not run. Refresh the page and try again.');
            importTimer = setTimeout(() => clear('Reading the workbook took too long. Use a smaller workbook and try again.'), 30000);
            const buffer = await file.arrayBuffer();
            if (epoch !== operation) return;
            worker.postMessage({ type: 'import', buffer, basis: basis(), managementBasis: JSON.parse(find('#project-management-cost-basis').textContent) }, [buffer]);
        } catch { if (epoch === operation) clear('The workbook could not be read locally. Try a valid, unencrypted Excel workbook.'); }
    }
    // Only hours filters are transmitted. File and currency controls have no form or name.
    async function filter(form, resetUrl = null) {
        if (busy) { status('Wait for the local workbook check, or clear financial data before changing filters.'); return; }
        busy = true;
        const url = new URL(resetUrl || form.action, location.href);
        if (url.origin !== location.origin) { busy = false; return; }
        if (!resetUrl) url.search = new URLSearchParams(new FormData(form)).toString();
        const oldRoot = root, oldCurrency = find('[data-cost-currency]')?.value, operation = epoch;
        const submit = form.querySelector('button[type="submit"]'); if (submit) submit.disabled = true;
        root.setAttribute('aria-busy', 'true');
        root.querySelectorAll('[data-cost-currency], [data-cost-file], [data-finance-file]').forEach(input => { input.disabled = true; });
        try {
            const response = await fetch(url, { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'text/html' } });
            const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
            if (epoch !== operation) return;
            const next = doc.getElementById('project-analytics');
            const hasValidationErrors = !!next?.querySelector('.is-invalid');
            if (!response.ok || !next || (response.redirected && !hasValidationErrors) || (panel() && !next.querySelector('[data-financial-panel]'))) {
                document.dispatchEvent(new CustomEvent('project-finance:clear'));
                clear('Your filters could not be applied. Check the dates, or sign in again if your session expired.');
                return;
            }
            oldRoot.querySelectorAll('input').forEach(input => input._flatpickr?.destroy());
            oldRoot.replaceWith(next); root = next; history.replaceState(null, '', url);
            document.dispatchEvent(new CustomEvent('project-analytics:updated'));
            if (typeof initializeDatePickers === 'function') initializeDatePickers(root);
            if (panel()) {
                find('[data-cost-currency]').value = oldCurrency;
                if (hasValidationErrors) { document.dispatchEvent(new CustomEvent('project-finance:clear')); clear('Filters were not applied. Correct the highlighted fields, then reload your rate workbook.'); return; }
                if (worker && result) {
                    busy = true; status('Updating costs locally for the selected period…');
                    worker.postMessage({ type: 'calculate', basis: basis(), managementBasis: JSON.parse(find('#project-management-cost-basis').textContent) });
                } else busy = false;
            }
        } catch { busy = false; status('Filters could not be loaded. Check your connection and try again.'); }
        finally {
            root.removeAttribute('aria-busy'); if (submit) submit.disabled = false;
            root.querySelectorAll('[data-cost-currency], [data-cost-file], [data-finance-file]').forEach(input => { input.disabled = false; });
        }
    }
    document.addEventListener('change', event => {
        if (!root.contains(event.target)) return;
        if (event.target.matches('[data-cost-file]')) loadFile(event.target);
        if (event.target.matches('[data-cost-currency], [data-cost-group]')) {
            if (result && !find('[data-cost-currency]').value) clear('Choose a currency and reload your workbook.');
            else render();
        }
    });
    document.addEventListener('click', event => {
        if (!root.contains(event.target)) return;
        if (event.target.closest('[data-cost-clear]')) clear();
        const reset = event.target.closest('[data-analytics-reset]');
        if (reset && panel()) { event.preventDefault(); filter(find('#analytics-filters'), reset.href); }
    });
    document.addEventListener('submit', event => {
        if (event.target.id === 'analytics-filters' && panel()) { event.preventDefault(); filter(event.target); }
        // Logout is outside this section and must clear memory before submitting.
        else if (event.target.action && new URL(event.target.action, location.href).pathname.endsWith('/logout')) clear();
    });
    window.addEventListener('pagehide', () => clear());
    window.addEventListener('pageshow', event => { if (event.persisted) clear('Financial data cleared after leaving this page. Load your workbook again.'); });
    document.addEventListener('visibilitychange', () => { if (expiresAt && Date.now() >= expiresAt) clear('The financial session expired. Reload your workbook to continue.'); });
})();
