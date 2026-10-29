/* Financial state stays in this closure and the short-lived local reader worker. */
(() => {
    'use strict';
    const panel = () => document.querySelector('[data-finance-panel]');
    if (!panel()) return;
    const find = selector => panel()?.querySelector(selector);
    let figures = null, labour = null, worker = null, expiry = null, timeout = null, deadline = 0, epoch = 0, importedCutoff = null;
    const cutoff = () => panel()?.dataset.cutoff;
    const dateLabel = () => new Date(`${cutoff()}T00:00:00`).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' });
    const money = value => {
        if (value == null) return 'Awaiting labour cost';
        const n = BigInt(value), a = n < 0n ? -n : n;
        return `AED ${n < 0n ? '-' : ''}${(a / 100n).toLocaleString()}.${String(a % 100n).padStart(2, '0')}`;
    };
    const status = text => { if (find('[data-finance-status]')) find('[data-finance-status]').textContent = text; };
    function stopReader() { worker?.terminate(); worker = null; clearTimeout(timeout); timeout = null; }
    function clear(message = 'Project figures cleared. Load the workbook again when needed.') {
        epoch++; stopReader(); clearTimeout(expiry); expiry = null; figures = null; deadline = 0; importedCutoff = null;
        if (!panel()) return;
        find('[data-finance-file]').value = ''; find('[data-finance-file]').disabled = false;
        find('[data-finance-clear]').disabled = true; find('[data-finance-results]').hidden = true;
        find('[data-finance-confirm]').checked = false; find('[data-finance-import]').open = true;
        panel().querySelectorAll('[data-finance-value]').forEach(el => { el.textContent = '—'; });
        find('[data-finance-billing]').replaceChildren(); find('[data-finance-spend]').replaceChildren(); find('[data-finance-note]').textContent = '';
        find('[data-finance-collection-note]').textContent = '';
        find('[data-finance-commitment]').replaceChildren(); find('[data-finance-commitment-warning]').textContent = ''; find('[data-finance-commitment-warning]').hidden = true;
        status(message);
    }
    function bars(target, items) {
        const list = document.createElement('dl'); list.className = 'analytics-finance-bars';
        const max = items.reduce((n, item) => item.value != null && item.value > n ? item.value : n, 0n);
        for (const item of items) {
            const row = document.createElement('div'), label = document.createElement('dt'), value = document.createElement('dd');
            label.textContent = item.label; value.textContent = item.value == null ? (item.emptyLabel || 'Awaiting labour cost') : money(item.value);
            value.dataset.financeValue = item.key;
            const track = document.createElement('div'); track.className = 'analytics-finance-track'; track.setAttribute('aria-hidden', 'true');
            if (item.value == null) track.classList.add('finance-track-unknown');
            const fill = document.createElement('span'); fill.className = item.kind || '';
            fill.style.width = `${item.value != null && item.value > 0n ? Number(item.value * 10000n / (max || 1n)) / 100 : 0}%`;
            track.append(fill); row.append(label, value, track); list.append(row);
        }
        target.replaceChildren(list);
        const scale = document.createElement('div'); scale.className = 'finance-scale'; scale.setAttribute('aria-hidden', 'true');
        for (const tick of ['AED 0', money(max / 2n), money(max)]) { const span = document.createElement('span'); span.textContent = tick; scale.append(span); }
        target.append(scale);
    }
    function breakdown(target, items, total, emptyText = 'No cost recorded.') {
        const fragment = document.createDocumentFragment();
        if (total !== null && total > 0n) {
            const stack = document.createElement('div'); stack.className = 'finance-stack'; stack.setAttribute('aria-hidden', 'true');
            for (const item of items) {
                const segment = document.createElement('span'); segment.className = item.kind || '';
                segment.style.width = `${Number(item.value * 100000n / total) / 1000}%`; stack.append(segment);
            }
            fragment.append(stack);
        }
        const list = document.createElement('dl'); list.className = 'finance-breakdown';
        for (const item of items) {
            const row = document.createElement('div'), label = document.createElement('dt'), value = document.createElement('dd');
            const key = document.createElement('span'); key.className = `finance-key ${item.kind || ''}`; key.setAttribute('aria-hidden', 'true');
            label.append(key, document.createTextNode(item.label)); value.textContent = item.value === null ? 'Not available' : money(item.value);
            row.append(label, value); list.append(row);
        }
        fragment.append(list);
        if (total === 0n) { const note = document.createElement('p'); note.className = 'small text-muted'; note.textContent = emptyText; fragment.append(note); }
        target.replaceChildren(fragment);
    }
    function render() {
        if (!figures || !panel()) return;
        if (importedCutoff !== cutoff()) { clear('The reporting cutoff changed. Confirm the new To date and import matching finance totals again.'); return; }
        if (Date.now() >= deadline) { clear('The 15-minute finance session expired. Reload your workbook.'); return; }
        const order = BigInt(figures.order), invoiced = BigInt(figures.invoiced), subcontractor = BigInt(figures.subcontractor);
        const overhead = (invoiced * 8n + 50n) / 100n, balance = order - invoiced;
        const ready = labour?.cutoff === cutoff() && labour.currency === 'AED' && labour.result && BigInt(labour.result.unpricedHours) === 0n;
        const cost = figures.labour !== null ? BigInt(figures.labour) : ready ? BigInt(labour.result.approved) : null;
        const total = cost === null ? null : cost + subcontractor + overhead;
        const committed = figures.committed == null ? null : BigInt(figures.committed), remaining = committed === null ? null : committed - subcontractor;
        const receipts = figures.receipts == null ? null : BigInt(figures.receipts);
        const values = { balance, orderLessCost: total === null ? null : order - total, invoiceLessCost: total === null ? null : invoiced - total,
            collectionGap: receipts === null ? null : invoiced - receipts,
            exposure: total === null || remaining === null || remaining < 0n ? null : total + remaining };
        for (const [key, value] of Object.entries(values)) {
            const el = find(`[data-finance-value="${key}"]`); if (!el) continue;
            el.textContent = key === 'collectionGap' && value === null ? 'Receipts not provided' : value === null && key === 'exposure' ? (total === null ? 'Awaiting labour cost' : remaining === null ? 'Commitment not provided' : 'Review commitment') : money(value);
            el.classList.toggle('text-danger-emphasis', key !== 'collectionGap' && value !== null && value < 0n);
        }
        find('[data-finance-collection-note]').textContent = receipts === null ? 'Enter Client Payments Received to compare invoicing with collections. Blank means unknown; enter 0 only when confirmed as none.' : receipts > invoiced ? 'Receipts exceed invoicing. Advance payments may explain this difference; it does not establish an overpayment.' : 'Invoice submitted minus actual receipts. Advances, retention and recovery can affect the gap; it is not unpaid invoices.';
        let unavailable = 'Labour unavailable: load your employee rate workbook below, with AED selected.';
        if (labour?.result && labour.cutoff !== cutoff()) unavailable = 'Updating approved labour for the selected reporting cutoff…';
        else if (!labour?.result && !document.querySelector('[data-cost-results]')?.hidden) unavailable = 'Employee rates are loaded, but the project labour calculation was not received. Refresh this page and reload both workbooks. If this continues, the application files need updating.';
        else if (labour && labour.currency !== 'AED') unavailable = `The loaded employee rates use ${labour.currency || 'no currency'}. Select AED below and confirm the rates are in AED; no currency conversion is applied.`;
        else if (labour?.result && BigInt(labour.result.unpricedHours) > 0n) {
            const hours = (Number(labour.result.unpricedHours) / 100).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            unavailable = `${hours} approved project hours have no matching rate across the full project history. Missing Employee IDs: ${labour.result.missing.slice(0, 10).join(', ')}${labour.result.missing.length > 10 ? '…' : ''}. Add these rates and reload the rate workbook.`;
        }
        const notes = [figures.labour !== null ? `Labour source: imported Manhour Spent, confirmed through ${dateLabel()}.` : ready ? `Labour source: all approved platform hours through ${dateLabel()} × AED employee rates, including overtime at 1.25.` : `${unavailable} Total cost remains unavailable; you can also enter Manhour Spent in the finance workbook.`];
        if (balance < 0n) notes.push('Invoice submitted exceeds the order value. Review the order and invoice figures.');
        find('[data-finance-note]').textContent = notes.join(' ');
        find('[data-finance-note]').classList.toggle('text-warning-emphasis', cost === null || balance < 0n);
        bars(find('[data-finance-billing]'), [{ key: 'order', label: 'Order value', value: order, kind: 'finance-order' }, { key: 'invoiced', label: 'Invoice submitted', value: invoiced, kind: 'finance-invoice' }, { key: 'receipts', label: 'Client payments received', value: receipts, kind: 'finance-receipts', emptyLabel: 'Not provided' }, { key: 'total', label: 'Total cost spent', value: total, kind: 'finance-spent' }]);
        breakdown(find('[data-finance-spend]'), [{ label: 'Manhour spent', value: cost, kind: 'finance-labour' }, { label: 'Subcontractor paid', value: subcontractor, kind: 'finance-subcontractor' }, { label: 'Overhead · 8%', value: overhead, kind: 'finance-overhead' }], total);
        const items = [{ label: 'Payments made', value: subcontractor, kind: 'finance-subcontractor' }, { label: 'Remaining commitment', value: remaining === null || remaining < 0n ? null : remaining, kind: 'finance-remaining' }];
        breakdown(find('[data-finance-commitment]'), items, committed !== null && remaining >= 0n ? committed : null, 'No subcontractor commitment recorded.');
        const commitmentList = find('[data-finance-commitment] dl');
        for (const [key, label, value] of [['committed', 'Approved committed value', committed], ['remaining', 'Remaining commitment', remaining]]) {
            const row = document.createElement('div'), dt = document.createElement('dt'), dd = document.createElement('dd');
            dt.textContent = label;
            if (key === 'remaining') { const swatch = document.createElement('span'); swatch.className = 'finance-key finance-remaining'; swatch.setAttribute('aria-hidden', 'true'); dt.prepend(swatch); }
            dd.textContent = value === null ? 'Not provided' : money(value); dd.dataset.financeValue = key;
            if (value !== null && value < 0n) dd.classList.add('text-danger-emphasis');
            row.append(dt, dd);
            if (key === 'committed') commitmentList.prepend(row); else commitmentList.lastElementChild.replaceWith(row);
        }
        const warning = find('[data-finance-commitment-warning]'); warning.hidden = remaining === null || remaining >= 0n;
        warning.textContent = remaining !== null && remaining < 0n ? `Payments exceed the approved commitment by ${money(-remaining)}. Review the committed value and payments before combining these figures.` : '';
        if (committed === null) { const note = document.createElement('p'); note.className = 'small text-muted'; note.textContent = 'Enter Subcontractor Committed Value to show the unpaid approved balance. Blank means unknown; enter 0 only when confirmed as none.'; find('[data-finance-commitment]').append(note); }
        find('[data-finance-results]').hidden = false; find('[data-finance-clear]').disabled = false;
        find('[data-finance-confirm]').checked = true;
        status(`${panel().dataset.projectCode} · Finance totals confirmed through ${dateLabel()}. Other projects were ignored.`);
    }
    async function load(input) {
        const file = input.files?.[0]; input.value = ''; if (!file) return;
        if (document.getElementById('date_to')?.value !== cutoff()) { clear('Apply the To date filter first, then confirm the cutoff and load your workbook.'); return; }
        if (!find('[data-finance-confirm]').checked) { status('Confirm that your Excel totals are cumulative through the displayed reporting date, then select your workbook again.'); return; }
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
                figures = data.result; importedCutoff = cutoff(); deadline = Date.now() + 15 * 60 * 1000;
                expiry = setTimeout(() => clear('The 15-minute finance session expired. Reload your workbook.'), 15 * 60 * 1000);
                render(); find('[data-finance-import]').open = false;
            };
            worker.onerror = () => clear('The local reader could not run. Refresh and try again.');
            timeout = setTimeout(() => clear('Reading took too long. Use a smaller workbook and try again.'), 30000);
            const buffer = await file.arrayBuffer(); if (operation !== epoch) return;
            worker.postMessage({ buffer, projectCode: panel().dataset.projectCode }, [buffer]);
        } catch { if (operation === epoch) clear('The workbook could not be read locally. Use a valid, unencrypted Excel workbook.'); }
    }
    document.addEventListener('change', event => {
        if (event.target.matches('[data-finance-file]')) load(event.target);
        if (event.target.matches('[data-finance-confirm]') && !event.target.checked && figures) clear('Cutoff confirmation removed. Confirm the reporting date and import matching finance totals again.');
        if (event.target.id === 'date_to' && (figures || worker) && event.target.value !== cutoff()) clear('To date changed. Apply the filter, then confirm the new cutoff and import matching finance totals again.');
    });
    document.addEventListener('click', event => {
        if (event.target.closest('[data-finance-clear], [data-cost-clear]')) clear();
    });
    document.addEventListener('project-labour:updated', event => { labour = event.detail; render(); });
    document.addEventListener('project-finance:clear', () => clear('Financial data cleared. Correct the filters or sign in again, then reload your workbook.'));
    document.addEventListener('project-analytics:updated', () => {
        // Filters replace the DOM; discard an in-flight file read rather than retain stale controls.
        if (worker) clear('Filters changed during import. Load the finance workbook again.');
        else { render(); if (figures) find('[data-finance-import]').open = false; }
    });
    document.addEventListener('submit', event => { if (event.target.action && new URL(event.target.action, location.href).pathname.endsWith('/logout')) { labour = null; clear(); } });
    window.addEventListener('pagehide', () => { labour = null; clear(); });
    window.addEventListener('pageshow', event => { if (event.persisted) { labour = null; clear(); } });
    document.addEventListener('visibilitychange', () => { if (deadline && Date.now() >= deadline) clear('The finance session expired. Reload your workbook.'); });
})();
