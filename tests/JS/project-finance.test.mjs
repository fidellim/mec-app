import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import vm from 'node:vm';
import test from 'node:test';
const headers = ['Project Code', 'Order Value', 'Invoice Submitted', 'Subcontractor Spent', 'Manhour Spent', 'Subcontractor Committed Value', 'Client Payments Received'];
function reader(fileHeaders = headers) {
    let message;
    const ctx = vm.createContext({ postMessage: value => { message = value; } }); ctx.self = ctx;
    ctx.importScripts = path => vm.runInContext(readFileSync(resolve('public/js', path), 'utf8'), ctx);
    vm.runInContext(readFileSync('public/js/project-finance-worker.js', 'utf8'), ctx);
    return (rows, projectCode = 'P26483', type = 'xlsx') => {
        const book = ctx.XLSX.utils.book_new();
        ctx.XLSX.utils.book_append_sheet(book, ctx.XLSX.utils.aoa_to_sheet([fileHeaders, ...rows]), 'Finance');
        ctx.onmessage({ data: { projectCode, buffer: ctx.XLSX.write(book, { type: 'array', bookType: type }) } });
        return message;
    };
}
test('matches only the current project, ignoring invalid financial cells for other projects', () => {
    for (const type of ['xlsx', 'xls']) {
        const read = reader();
        const message = read([['OTHER', 'invalid', -1, 'invalid', 'invalid'], [' p26483 ', 895000, 661335, 12600, '']], 'P26483', type);
        assert.equal(message.type, 'result');
        assert.equal(message.result.order, '89500000'); assert.equal(message.result.invoiced, '66133500');
        assert.equal(message.result.subcontractor, '1260000'); assert.equal(message.result.labour, null);
        assert.equal(Object.keys(message.result).length, 6);
        assert.equal(message.result.committed, null);
    }
});
test('client receipts preserve blank, zero, partial payments and advances, using only the matching project', () => {
    const read = reader();
    for (const type of ['xlsx', 'xls']) {
        for (const [value, expected] of [['', null], ['  ', null], [0, '0'], [15.25, '1525'], [200, '20000']]) {
            const message = read([['OTHER', 1, 1, 1, '', '', 'invalid'], ['P26483', 100, 60, 10, '', '', value]], 'P26483', type);
            assert.equal(message.type, 'result'); assert.equal(message.result.receipts, expected);
        }
    }
    for (const value of [-1, 'invalid', '0.001', 1000000000001]) assert.equal(read([['P26483', 100, 60, 10, '', '', value]]).type, 'error');
    assert.equal(reader(headers.slice(0, 5))([['P26483', 100, 60, 10, '']]).result.receipts, null);
    assert.equal(reader([...headers, ' Client Payments Received '])([['P26483', 1, 1, 1, '', '', 1, 2]]).type, 'error');
    const reordered = reader(['Client Payments Received', 'Project Code', 'Manhour Spent', 'Subcontractor Spent', 'Invoice Submitted', 'Order Value']);
    assert.equal(reordered([[25, 'P26483', '', 10, 60, 100]]).result.receipts, '2500');
});
test('commitment distinguishes unknown from zero and does not deduct payments from the imported approved value', () => {
    const read = reader();
    assert.equal(read([['P26483', 100, 20, 10, '', '']]).result.committed, null);
    assert.equal(read([['P26483', 100, 20, 10, '', 0]]).result.committed, '0');
    assert.equal(read([['P26483', 100, 20, 10, '', 50]]).result.committed, '5000');
    assert.equal(read([['P26483', 100, 20, 10, '', -1]]).type, 'error');
});
test('blank labour falls back; explicit zero and cents remain overrides', () => {
    const read = reader();
    assert.equal(read([['P26483', 0, 0, 0, '   ']]).result.labour, null);
    assert.equal(read([['P26483', 0, 0, 0, 0]]).result.labour, '0');
    assert.equal(read([['P26483', '100.01', '10.15', '1.99', '123.45']]).result.labour, '12345');
});
test('rejects missing, duplicate, invalid and excessive amounts without falling back to another project', () => {
    const read = reader();
    for (const rows of [
        [['OTHER', 1, 1, 1, 1]],
        [['P26483', 1, 1, 1, 1], [' p26483 ', 2, 2, 2, 2]],
        [['P26483', '', 1, 1, '']], [['P26483', 1, '', 1, '']], [['P26483', 1, 1, '', '']],
        [['P26483', 1, 1, -1, '']], [['P26483', 1, 1, 1, 'bad']],
        [['P26483', '100.001', 1, 1, '']], [['P26483', 1000000000001, 1, 1, '']],
    ]) assert.equal(read(rows).type, 'error');
    assert.equal(read([['P26483', 1, 1, 1, '']], '').type, 'error');
});
