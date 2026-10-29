/* Local-only project summary reader. No network, storage, or uploads. */
importScripts('vendor/xlsx-0.20.3.min.js');
const normalize = value => String(value ?? '').trim().toUpperCase();
const headings = ['project code', 'order value', 'invoice submitted', 'subcontractor spent', 'manhour spent'];
function amount(value, label, optional = false) {
    if (value == null || (typeof value === 'string' && value.trim() === '')) {
        if (optional) return null;
        throw new Error(`${label} is missing. Enter an amount, including 0 when confirmed as none.`);
    }
    const text = typeof value === 'number' && Number.isFinite(value) ? value.toFixed(2) : String(value).trim();
    const match = /^(\d+)(?:\.(\d{1,2}))?$/.exec(text);
    if (!match) throw new Error(`${label} must be a nonnegative number with up to 2 decimal places, without currency symbols.`);
    const cents = BigInt(match[1]) * 100n + BigInt((match[2] || '').padEnd(2, '0'));
    if (cents > 100000000000000n) throw new Error(`${label} exceeds the supported limit of AED 1 trillion.`);
    return cents.toString();
}
self.onmessage = ({ data }) => {
    try {
        if (!normalize(data.projectCode)) throw new Error('This project has no project code. Add a project code before importing finance figures.');
        const book = XLSX.read(data.buffer, { type: 'array', cellHTML: false, cellFormula: true, sheetRows: 20002 });
        const matches = [];
        let tables = 0;
        for (const sheetName of book.SheetNames) {
            const sheet = book.Sheets[sheetName];
            if (!sheet['!ref']) continue;
            const bounds = XLSX.utils.decode_range(sheet['!ref']);
            if (bounds.e.c > 255) throw new Error('Use a finance worksheet with no more than 256 columns.');
            const rows = XLSX.utils.sheet_to_json(sheet, { header: 1, raw: true, defval: '', range: { s: { r: 0, c: 0 }, e: bounds.e } });
            const header = rows.slice(0, 20).findIndex(row => headings.every(key => row.map(v => String(v).trim().toLowerCase()).includes(key)));
            if (header < 0) continue;
            tables++;
            if (sheet['!fullref'] && XLSX.utils.decode_range(sheet['!fullref']).e.r >= 20001) throw new Error('Use a finance worksheet with no more than 20,000 rows.');
            const titles = rows[header].map(v => String(v).trim().toLowerCase());
            if (headings.some(key => titles.filter(v => v === key).length !== 1)) throw new Error('The finance worksheet contains duplicate column headings.');
            const columns = headings.map(key => titles.indexOf(key));
            for (const row of rows.slice(header + 1)) {
                // Other projects' monetary figures are never returned to the page.
                if (normalize(row[columns[0]]) === normalize(data.projectCode)) matches.push(columns.map(i => row[i]));
            }
        }
        if (tables !== 1) throw new Error(tables ? 'Use one finance worksheet with the five required columns.' : 'Required headings not found in the first 20 rows: Project Code, Order Value, Invoice Submitted, Subcontractor Spent, Manhour Spent.');
        if (matches.length !== 1) throw new Error(matches.length ? 'Duplicate rows match this project code. Keep exactly one summary row per project.' : 'No row matches this project code. Check Project Code in the workbook.');
        const row = matches[0];
        self.postMessage({ type: 'result', result: {
            order: amount(row[1], 'Order Value'), invoiced: amount(row[2], 'Invoice Submitted'),
            subcontractor: amount(row[3], 'Subcontractor Spent'), labour: amount(row[4], 'Manhour Spent', true),
        } });
    } catch (error) {
        self.postMessage({ type: 'error', message: error instanceof Error && /missing|must be|limit|project code|worksheet|headings|matches|Duplicate/.test(error.message) ? error.message : 'The workbook could not be read. Use a valid, unencrypted Excel workbook with saved numeric values.' });
    }
};
