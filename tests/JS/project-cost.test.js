import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import vm from 'node:vm';
import test from 'node:test';

function worker() {
    const messages = [];
    const context = vm.createContext({ postMessage: message => messages.push(message) });
    context.self = context;
    context.importScripts = (...paths) => paths.forEach(path => vm.runInContext(readFileSync(resolve('public/js', path), 'utf8'), context));
    vm.runInContext(readFileSync('public/js/project-cost-worker.js', 'utf8'), context);
    return { context, send: data => { context.onmessage({ data }); return messages.at(-1); } };
}
function workbook(context, rows, format = 'xlsx') {
    const book = context.XLSX.utils.book_new();
    context.XLSX.utils.book_append_sheet(book, context.XLSX.utils.aoa_to_sheet(rows), 'Rates');
    return context.XLSX.write(book, { type: 'array', bookType: format });
}
const headers = ['Employee ID', 'Initials', 'Employee Name', 'Rate/Manhour'];
const row = (code, status, regular, overtime, extras = {}) => ({ employeeCode: code, employeeName: code === '001' ? 'Employee One' : 'Employee Two', department: 'Engineering', category: 'Engineer', month: '2026-08', status, regular, overtime, ...extras });

test('management labour result retains its own reporting cutoff independently of selected-period totals', () => {
    const { context, send } = worker();
    const message = send({ type: 'import', buffer: workbook(context, [headers, ['001', 'EO', 'Employee One', 100]]), basis: [], managementBasis: [row('001', 'approved', '2', '1')], managementCutoff: '2026-09-30' });
    assert.equal(message.result.total, '0'); assert.equal(message.management.approved, '32500');
    assert.equal(message.managementCutoff, '2026-09-30');
    const next = send({ type: 'calculate', basis: [], managementBasis: [], managementCutoff: '2026-08-31' });
    assert.equal(next.management.approved, '0'); assert.equal(next.managementCutoff, '2026-08-31');
});

test('real XLSX and XLS parsing prices each employee and status independently, and reconciles groups', () => {
    for (const format of ['xlsx', 'xls']) {
        const { context, send } = worker();
        const basis = [row('001', 'approved', '8.00', '2.00'), row('002', 'submitted', '4.00', '1.00', { department: 'Operations', category: 'Designer', month: '2026-09' })];
        const message = send({ type: 'import', buffer: workbook(context, [headers, ['001', 'EO', 'Employee One', 100], ['002', 'ET', 'Employee Two', 80]], format), basis });
        assert.equal(message.type, 'result');
        assert.equal(message.result.approved, '105000');
        assert.equal(message.result.pending, '42000');
        assert.equal(message.result.total, '147000');
        assert.equal(message.result.overtime, '35000');
        assert.equal(message.result.premium, '7000');
        assert.equal(message.result.pricedHours, '1500');
        assert.equal(message.result.unpricedHours, '0');
        for (const key of ['months', 'departments', 'categories', 'employees']) assert.equal(message.result[key].reduce((sum, item) => sum + BigInt(item.total), 0n), 147000n);
    }
});

test('keeps six-decimal rates and uses decimal half-up rounding', () => {
    const { context, send } = worker();
    const message = send({ type: 'import', buffer: workbook(context, [headers, ['001', 'EO', 'Employee One', 123.456789]]), basis: [row('001', 'approved', '1.23', '0.75')] });
    // Regular 151.85185047 -> 151.85; OT 115.7407396875 -> 115.74.
    assert.equal(message.result.total, '26759');
    assert.equal(message.result.premium, '2315');
    assert.equal(context.MecProjectCost.rate('0.005000'), 5000n);
    assert.throws(() => context.MecProjectCost.validateRates([{ row: 2, employeeCode: '001', employeeName: 'Employee One', rate: '.005' }]));
    const half = context.MecProjectCost.validateRates([{ row: 2, employeeCode: '001', employeeName: 'Employee One', rate: '0.005' }]);
    assert.equal(context.MecProjectCost.calculate([row('001', 'approved', '1', '0')], half).total, '1');
});

test('missing rates are excluded and explicitly accounted for; zero and empty hours remain safe', () => {
    const { context, send } = worker();
    const rates = workbook(context, [headers, ['001', 'EO', 'Wrong Name', 100], ['UNUSED', 'U', 'Unused', 1]]);
    const { result } = send({ type: 'import', buffer: rates, basis: [row('001', 'approved', '8', '0'), row('MISSING', 'submitted', '2', '1'), row(null, 'approved', '1', '0')] });
    assert.equal(result.total, '80000'); assert.equal(result.unpricedHours, '400');
    assert.equal(result.matched, 1); assert.equal(result.imported, 2);
    assert.deepEqual([...result.mismatched], ['001']); assert.equal(result.missing.length, 2);
    const updated = send({ type: 'calculate', basis: [] });
    assert.equal(updated.result.total, '0'); assert.equal(updated.result.pricedHours, '0');
});

test('rejects duplicates, missing headings, bad rates and corrupt workbooks without retaining prior rates', () => {
    const { context, send } = worker();
    for (const rows of [
        [headers, ['001', 'A', 'One', 100], [' 001 ', 'A', 'One', 200]],
        [['Employee ID', 'Wrong heading']],
        [headers, ['001', 'A', 'One', -1]],
        [headers, ['001', 'A', 'One', 0]],
        [headers, ['001', 'A', 'One', 'not a number']],
        [headers, ['', 'A', 'One', 100]],
    ]) {
        assert.equal(send({ type: 'import', buffer: workbook(context, rows), basis: [] }).type, 'error');
        assert.equal(send({ type: 'calculate', basis: [] }).type, 'error');
    }
    assert.equal(send({ type: 'import', buffer: new ArrayBuffer(0), basis: [] }).type, 'error');
});

test('identifiers normalize whitespace/case, preserve leading zeros and are never matched by name', () => {
    const { context, send } = worker();
    const message = send({ type: 'import', buffer: workbook(context, [headers, [' emp-001 ', 'E', 'Employee One', 100], ['001', 'E', 'Employee One', 50]]), basis: [row('EMP-001', 'approved', '1', '0'), row('001', 'approved', '1', '0'), row('1', 'approved', '1', '0')] });
    assert.equal(message.result.total, '15000'); assert.equal(message.result.unpricedHours, '100');
});

test('headings may start below row one and formatted numeric identifiers retain leading zeros', () => {
    const { context, send } = worker();
    const book = context.XLSX.utils.book_new();
    const sheet = context.XLSX.utils.aoa_to_sheet([headers, [1, 'EO', 'Employee One', 100]]);
    // Move the table to C5, like an existing workbook with margins/title space.
    const shifted = {};
    for (const [address, cell] of Object.entries(sheet)) {
        if (address.startsWith('!')) continue;
        const point = context.XLSX.utils.decode_cell(address);
        shifted[context.XLSX.utils.encode_cell({ r: point.r + 4, c: point.c + 2 })] = cell;
    }
    shifted.C6.z = '000'; shifted['!ref'] = 'C5:F6';
    context.XLSX.utils.book_append_sheet(book, shifted, 'Rates');
    const buffer = context.XLSX.write(book, { type: 'array', bookType: 'xlsx' });
    const message = send({ type: 'import', buffer, basis: [row('001', 'approved', '1', '0')] });
    assert.equal(message.type, 'result'); assert.equal(message.result.total, '10000');
});
