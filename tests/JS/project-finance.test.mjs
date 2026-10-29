import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import vm from 'node:vm';
import test from 'node:test';
const headers = ['Project Code', 'Order Value', 'Invoice Submitted', 'Subcontractor Spent', 'Manhour Spent'];
function reader() {
    let message;
    const ctx = vm.createContext({ postMessage: value => { message = value; } }); ctx.self = ctx;
    ctx.importScripts = path => vm.runInContext(readFileSync(resolve('public/js', path), 'utf8'), ctx);
    vm.runInContext(readFileSync('public/js/project-finance-worker.js', 'utf8'), ctx);
    return (rows, projectCode = 'P26483', type = 'xlsx') => {
        const book = ctx.XLSX.utils.book_new();
        ctx.XLSX.utils.book_append_sheet(book, ctx.XLSX.utils.aoa_to_sheet([headers, ...rows]), 'Finance');
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
        assert.equal(Object.keys(message.result).length, 4);
    }
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
