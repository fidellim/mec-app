/* This worker reads a transferred local file. It never sends requests with file data. */
importScripts('vendor/xlsx-0.20.3.min.js', 'project-cost-core.js');
let rates = null;
function inputError(message) {
    const error = new Error(message);
    error.safeToDisplay = true;
    return error;
}
self.onmessage = ({ data }) => {
    try {
        if (data.type === 'import') {
            rates = null;
            let book;
            try { book = XLSX.read(data.buffer, { type: 'array', cellHTML: false, cellFormula: false, sheetRows: 20002 }); }
            catch { throw inputError('The workbook could not be read. Use a valid, unencrypted Excel workbook.'); }
            const required = ['employee id', 'initials', 'employee name', 'rate/manhour'];
            const candidates = [];
            for (const sheetName of book.SheetNames) {
                const sheet = book.Sheets[sheetName];
                if (sheet['!ref'] && XLSX.utils.decode_range(sheet['!ref']).e.c > 255) throw inputError('Use a rate worksheet with no more than 256 columns.');
                const range = sheet['!ref'] ? { s: { r: 0, c: 0 }, e: XLSX.utils.decode_range(sheet['!ref']).e } : null;
                const rows = range ? XLSX.utils.sheet_to_json(sheet, { header: 1, defval: '', raw: true, blankrows: true, range }) : [];
                const header = rows.slice(0, 20).findIndex(row => required.every(column => row.map(v => String(v).trim().toLowerCase()).includes(column)));
                if (header >= 0) candidates.push({ rows, header, sheet });
            }
            if (candidates.length !== 1) throw inputError(candidates.length ? 'More than one rate worksheet found. Use a workbook with one rate worksheet.' : 'Required headings not found in the first 20 rows: Employee ID, Initials, Employee Name, Rate/Manhour.');
            const { rows, header, sheet } = candidates[0];
            if (sheet['!fullref'] && XLSX.utils.decode_range(sheet['!fullref']).e.r >= 20001) throw inputError('The worksheet exceeds 20,000 rows. Use a smaller rate workbook.');
            const headings = rows[header].map(value => String(value).trim().toLowerCase());
            if (required.some(column => headings.filter(value => value === column).length !== 1)) throw inputError('The rate worksheet contains duplicate column headings.');
            const id = headings.indexOf('employee id'), employeeName = headings.indexOf('employee name'), amount = headings.indexOf('rate/manhour');
            const entries = rows.slice(header + 1).map((row, index) => ({ values: row, row: index + header + 2 }))
                .filter(({ values }) => values.some(value => String(value).trim() !== ''))
                .map(({ values, row }) => {
                    // Preserve formatted identifiers such as 000123 rather than coercing them to numbers.
                    const idCell = sheet[XLSX.utils.encode_cell({ r: row - 1, c: id })];
                    return { employeeCode: idCell ? XLSX.utils.format_cell(idCell) : '', employeeName: values[employeeName], rate: values[amount], row };
                });
            if (entries.length > 20000) throw inputError('Use a workbook with no more than 20,000 employee rates.');
            try { rates = MecProjectCost.validateRates(entries); }
            catch (error) { throw inputError(error.message); }
        }
        if (!rates) throw new Error('Load the rate workbook again.');
        self.postMessage({ type: 'result', result: MecProjectCost.calculate(data.basis, rates) });
    } catch (error) {
        rates = null;
        self.postMessage({ type: 'error', message: data.type === 'import' && error.safeToDisplay ? error.message : 'Financial calculations could not be completed. Clear the data and reload the workbook.' });
    }
};
