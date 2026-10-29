/* Pure local calculations. No storage, DOM, or networking. */
(function (scope) {
    'use strict';
    const code = value => String(value ?? '').trim().toUpperCase();
    const name = value => String(value ?? '').trim().replace(/\s+/g, ' ').toUpperCase();
    function scaled(value, places, maximum) {
        let text = typeof value === 'number' && Number.isFinite(value) ? value.toFixed(places).replace(/\.?0+$/, '') : String(value ?? '').trim();
        if (text === '') text = typeof value === 'number' && value === 0 ? '0' : '';
        const match = text.match(/^(\d+)(?:\.(\d+))?$/);
        if (!match || (match[2]?.length ?? 0) > places) throw new Error('Invalid decimal');
        const result = BigInt(match[1]) * 10n ** BigInt(places) + BigInt((match[2] ?? '').padEnd(places, '0'));
        if (result > maximum) throw new Error('Decimal exceeds limit');
        return result;
    }
    const rate = value => scaled(value, 6, 1000000000000000n);
    const hours = value => scaled(value, 2, 100000000000000n);
    const round = (value, divisor) => (value + divisor / 2n) / divisor;
    function validateRates(rows, dated = false) {
        const rates = new Map();
        const errors = [];
        for (const row of rows) {
            const key = code(row.employeeCode);
            if (!key) { errors.push(`Row ${row.row}: Employee ID is missing.`); continue; }
            if (rates.has(key)) { errors.push(`Row ${row.row}: duplicate Employee ID.`); continue; }
            if (dated) {
                const entry = { dated: true, name: name(row.employeeName), before: null, after: null };
                for (const [field, value, title] of [['before', row.beforeRate, 'Rate/Manhour Until Apr 2026'], ['after', row.afterRate, 'Rate/Manhour From May 2026']]) {
                    if (value == null || String(value).trim() === '') continue;
                    try { entry[field] = rate(value); if (entry[field] <= 0n) throw new Error('Nonpositive rate'); }
                    catch { errors.push(`Row ${row.row}: ${title} must be a positive number with up to 6 decimal places, or blank when unknown.`); }
                }
                rates.set(key, entry);
                continue;
            }
            try {
                const amount = rate(row.rate);
                if (amount <= 0n) throw new Error('Nonpositive rate');
                rates.set(key, { amount, name: name(row.employeeName) });
            } catch {
                errors.push(`Row ${row.row}: Rate/Manhour must be a positive number with up to 6 decimal places.`);
            }
        }
        if (!rows.length) errors.push('No employee rates found. Add data below the column headings.');
        if (errors.length) throw new Error(errors.slice(0, 8).join(' ') + (errors.length > 8 ? ` Plus ${errors.length - 8} other errors.` : ''));
        return rates;
    }
    function calculate(basis, rates) {
        const result = { approved: 0n, pending: 0n, total: 0n, overtime: 0n, premium: 0n, pricedHours: 0n, unpricedHours: 0n };
        const groups = { months: new Map(), departments: new Map(), categories: new Map(), employees: new Map() };
        const matched = new Set(), mismatched = new Set(), missing = new Set(), missingPeriods = new Map();
        const dated = rates.values().next().value?.dated === true;
        for (const row of basis) {
            const regular = hours(row.regular), overtime = hours(row.overtime);
            const key = code(row.employeeCode), employeeRate = rates.get(key);
            if (dated && !/^\d{4}-(0[1-9]|1[0-2])$/.test(row.month)) throw new Error('Work month is required for dated rates');
            const period = row.month < '2026-05' ? 'Until Apr 2026' : 'From May 2026';
            const amount = employeeRate ? (dated ? employeeRate[row.month < '2026-05' ? 'before' : 'after'] : employeeRate.amount) : null;
            if (amount === null) {
                result.unpricedHours += regular + overtime;
                if (regular + overtime > 0n) {
                    missing.add(key || '(missing Employee ID)');
                    if (dated) {
                        const periodKey = `${key}|${period}`, item = missingPeriods.get(periodKey) ?? { employeeCode: key || '(missing Employee ID)', period, hours: 0n };
                        item.hours += regular + overtime; missingPeriods.set(periodKey, item);
                    }
                }
            }
            if (employeeRate) {
                matched.add(key);
                if (employeeRate.name && employeeRate.name !== name(row.employeeName)) mismatched.add(key);
            }
            const regularCost = amount !== null ? round(regular * amount, 1000000n) : 0n;
            const overtimeCost = amount !== null ? round(overtime * amount * 5n, 4000000n) : 0n;
            const premium = amount !== null ? round(overtime * amount, 4000000n) : 0n;
            const total = regularCost + overtimeCost;
            if (amount !== null) result.pricedHours += regular + overtime;
            result[row.status === 'approved' ? 'approved' : 'pending'] += total;
            result.total += total; result.overtime += overtimeCost; result.premium += premium;
            for (const [type, label] of [['months', row.month], ['departments', row.department], ['categories', row.category], ['employees', `${row.employeeName} (${row.employeeCode ?? 'No ID'})`]]) {
                const group = groups[type].get(label) ?? { label, total: 0n, approved: 0n, pending: 0n, overtime: 0n, unpricedHours: 0n };
                group.total += total; group[row.status === 'approved' ? 'approved' : 'pending'] += total;
                group.overtime += overtimeCost;
                if (amount === null) group.unpricedHours += regular + overtime;
                groups[type].set(label, group);
            }
        }
        const serial = object => Object.fromEntries(Object.entries(object).map(([key, value]) => [key, typeof value === 'bigint' ? value.toString() : value]));
        return {
            ...serial(result), matched: matched.size, imported: rates.size,
            rateMode: dated ? 'dated' : 'single', missingPeriods: [...missingPeriods.values()].map(serial),
            mismatched: [...mismatched], missing: [...missing],
            ...Object.fromEntries(Object.entries(groups).map(([key, group]) => [key, [...group.values()].map(serial).sort((a, b) => key === 'months' ? a.label.localeCompare(b.label) : (BigInt(a.total) < BigInt(b.total) ? 1 : BigInt(a.total) > BigInt(b.total) ? -1 : a.label.localeCompare(b.label)))])),
        };
    }
    scope.MecProjectCost = { code, rate, validateRates, calculate };
})(typeof self === 'undefined' ? globalThis : self);
