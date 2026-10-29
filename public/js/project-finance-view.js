/* Financial state stays in this closure and the short-lived local reader worker. */
(() => {
    'use strict';
    const panel = () => document.querySelector('[data-finance-panel]');
    if (!panel()) return;
    const find = selector => panel()?.querySelector(selector);
    let figures = null, labour = null, worker = null, expiry = null, timeout = null, deadline = 0, epoch = 0;
    const money = value => {
        if (value == null) return 'Awaiting labour cost';
        const n = BigInt(value), a = n < 0n ? -n : n;
        return `AED ${n < 0n ? '-' : ''}${(a / 100n).toLocaleString()}.${String(a % 100n).padStart(2, '0')}`;
    };
    const status = text => { if (find('[data-finance-status]')) find('[data-finance-status]').textContent = text; };
    function stopReader() { worker?.terminate(); worker = null; clearTimeout(timeout); timeout = null; }
    function clear(message = 'Project figures cleared. Load the workbook again when needed.') {
        epoch++; stopReader(); clearTimeout(expiry); expiry = null; figures = null; deadline = 0;
        if (!panel()) return;
        find('[data-finance-file]').value = ''; find('[data-finance-file]').disabled = false;
        find('[data-finance-clear]').disabled = true; find('[data-finance-results]').hidden = true;
        panel().querySelectorAll('[data-finance-value]').forEach(el => { el.textContent = '—'; });
        find('[data-finance-billing]').replaceChildren(); find('[data-finance-spend]').replaceChildren(); find('[data-finance-note]').textContent = '';
        status(message);
    }
    function bars(target, items) {
        const list = document.createElement('dl'); list.className = 'analytics-finance-bars';
        const max = items.reduce((n, item) => item.value != null && item.value > n ? item.value : n, 1n);
        for (const item of items) {
            const row = document.createElement('div'), label = document.createElement('dt'), value = document.createElement('dd');
            label.textContent = item.label; value.textContent = item.value == null ? 'Not available' : money(item.value);
            const track = document.createElement('div'); track.className = 'analytics-finance-track'; track.setAttribute('aria-hidden', 'true');
            const fill = document.createElement('span'); fill.className = item.kind || '';
            fill.style.width = `${item.value != null && item.value > 0n ? Number(item.value * 10000n / max) / 100 : 0}%`;
            track.append(fill); row.append(label, value, track); list.append(row);
        }
        target.replaceChildren(list);
    }
    function render() {
        if (!figures || !panel()) return;
        if (Date.now() >= deadline) { clear('The 15-minute finance session expired. Reload your workbook.'); return; }
        const order = BigInt(figures.order), invoiced = BigInt(figures.invoiced), subcontractor = BigInt(figures.subcontractor);
        const overhead = (invoiced * 8n + 50n) / 100n, balance = order - invoiced;
        const ready = labour?.currency === 'AED' && labour.result && BigInt(labour.result.unpricedHours) === 0n;
        const cost = figures.labour !== null ? BigInt(figures.labour) : ready ? BigInt(labour.result.approved) : null;
        const values = { order, invoiced, balance, total: cost === null ? null : cost + subcontractor + overhead };
        panel().querySelectorAll('[data-finance-value]').forEach(el => { el.textContent = money(values[el.dataset.financeValue]); });
        const notes = [figures.labour !== null ? 'Labour source: imported Manhour Spent.' : ready ? 'Labour source: all approved platform hours through today × AED employee rates, including overtime at 1.25.' : 'Labour unavailable: load AED employee rates covering every employee with approved hours, or enter Manhour Spent in the finance workbook. Total cost remains unavailable.'];
        if (balance < 0n) notes.push('Invoice submitted exceeds the order value. Review the order and invoice figures.');
        find('[data-finance-note]').textContent = notes.join(' ');
        find('[data-finance-note]').classList.toggle('text-warning-emphasis', cost === null || balance < 0n);
        bars(find('[data-finance-billing]'), [{ label: 'Order value', value: order, kind: 'finance-reference' }, { label: 'Invoice submitted', value: invoiced }, { label: balance < 0n ? 'Over-invoiced amount' : 'Balance for invoicing', value: balance < 0n ? -balance : balance, kind: balance < 0n ? 'finance-warning' : 'finance-secondary' }]);
        bars(find('[data-finance-spend]'), [{ label: 'Manhour spent', value: cost }, { label: 'Subcontractor spent', value: subcontractor, kind: 'finance-secondary' }, { label: 'Overhead · 8%', value: overhead, kind: 'finance-reference' }]);
        find('[data-finance-results]').hidden = false; find('[data-finance-clear]').disabled = false;
        status(`One row matched ${panel().dataset.projectCode}. Other projects were ignored. Figures replace the previous import for this session.`);
    }
    async function load(input) {
        const file = input.files?.[0]; input.value = ''; if (!file) return;
        clear('Reading project figures locally…');
        if (!/\.(xlsx|xls)$/i.test(file.name) || !file.size || file.size > 5 * 1024 * 1024) { status('Select a nonempty Excel .xlsx or .xls workbook up to 5 MB.'); return; }
        input.disabled = true; find('[data-finance-clear]').disabled = false;
        const operation = epoch;
        try {
            worker = new Worker(panel().dataset.workerUrl);
            worker.onmessage = ({ data }) => {
                if (operation !== epoch) return;
                stopReader(); input.disabled = false;
                if (data.type === 'error') { clear(data.message); return; }
                figures = data.result; deadline = Date.now() + 15 * 60 * 1000;
                expiry = setTimeout(() => clear('The 15-minute finance session expired. Reload your workbook.'), 15 * 60 * 1000);
                render();
            };
            worker.onerror = () => clear('The local reader could not run. Refresh and try again.');
            timeout = setTimeout(() => clear('Reading took too long. Use a smaller workbook and try again.'), 30000);
            const buffer = await file.arrayBuffer(); if (operation !== epoch) return;
            worker.postMessage({ buffer, projectCode: panel().dataset.projectCode }, [buffer]);
        } catch { if (operation === epoch) clear('The workbook could not be read locally. Use a valid, unencrypted Excel workbook.'); }
    }
    document.addEventListener('change', event => { if (event.target.matches('[data-finance-file]')) load(event.target); });
    document.addEventListener('click', event => {
        if (event.target.closest('[data-finance-clear], [data-cost-clear]')) clear();
    });
    document.addEventListener('project-labour:updated', event => { labour = event.detail; render(); });
    document.addEventListener('project-finance:clear', () => clear('Financial data cleared. Correct the filters or sign in again, then reload your workbook.'));
    document.addEventListener('project-analytics:updated', () => {
        // Filters replace the DOM; discard an in-flight file read rather than retain stale controls.
        if (worker) clear('Filters changed during import. Load the finance workbook again.');
        else render();
    });
    document.addEventListener('submit', event => { if (event.target.action && new URL(event.target.action, location.href).pathname.endsWith('/logout')) { labour = null; clear(); } });
    window.addEventListener('pagehide', () => { labour = null; clear(); });
    window.addEventListener('pageshow', event => { if (event.persisted) { labour = null; clear(); } });
    document.addEventListener('visibilitychange', () => { if (deadline && Date.now() >= deadline) clear('The finance session expired. Reload your workbook.'); });
})();
