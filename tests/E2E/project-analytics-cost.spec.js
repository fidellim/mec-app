import { expect, test } from '@playwright/test';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

// Synthetic rates only. No real salary data is used by this suite.
const basis = [
  { employeeCode: 'COST-001', employeeName: 'Synthetic One', department: 'Engineering', category: 'Engineer', month: '2026-08', status: 'approved', regular: '8.00', overtime: '2.00' },
  { employeeCode: 'COST-002', employeeName: 'Synthetic Two', department: 'Operations', category: 'Designer', month: '2026-09', status: 'submitted', regular: '4.00', overtime: '1.00' },
];
function workbook(rows) {
  const context = vm.createContext({});
  vm.runInContext(readFileSync('public/js/vendor/xlsx-0.20.3.min.js', 'utf8'), context);
  const book = context.XLSX.utils.book_new();
  context.XLSX.utils.book_append_sheet(book, context.XLSX.utils.aoa_to_sheet([['Employee ID', 'Initials', 'Employee Name', 'Rate/Manhour'], ...rows]), 'Rates');
  return Buffer.from(context.XLSX.write(book, { type: 'array', bookType: 'xlsx' }));
}
const rateFile = rows => ({ name: 'synthetic-confidential-rates.xlsx', mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', buffer: workbook(rows) });
const validRows = [['COST-001', 'SO', 'Synthetic One', 100], ['COST-002', 'ST', 'Synthetic Two', 80]];

async function openAnalytics(page) {
  await page.goto('/login');
  await page.locator('input[name="email"]').fill(process.env.E2E_SUPER_ADMIN_EMAIL || 'superadmin@example.com');
  await page.locator('input[name="password"]').fill(process.env.E2E_SUPER_ADMIN_PASSWORD || 'password123');
  await page.getByRole('button', { name: /login/i }).click();
  await expect(page).toHaveURL(/\/$/);
  await page.route('**/projects/*/utilization?*', async route => {
    const response = await route.fetch();
    let html = await response.text();
    html = html.replace(/(<script type="application\/json" id="project-cost-basis">)[\s\S]*?(<\/script>)/, `$1${JSON.stringify(basis)}$2`);
    html = html.replace(/(<script type="application\/json" id="project-management-cost-basis">)[\s\S]*?(<\/script>)/, `$1${JSON.stringify(basis.filter(row => row.status === 'approved'))}$2`);
    await route.fulfill({ response, body: html });
  });
  await page.goto('/projects/1/utilization?tab=analytics');
  await expect(page.getByRole('heading', { name: 'Confidential labour costs' })).toBeVisible();
  await page.getByLabel('Rate currency', { exact: true }).selectOption('AED');
}

test('local workbook costs reconcile; filters keep rates local; clear and refresh discard them', async ({ page }) => {
  await openAnalytics(page);
  const requests = [];
  page.on('request', request => requests.push({ url: request.url(), method: request.method(), body: request.postData() }));
  await page.locator('#cost-workbook').setInputFiles(rateFile(validRows));
  await expect(page.locator('[data-cost-value="approved"]')).toHaveText('AED 1,050.00');
  await expect(page.locator('[data-cost-value="pending"]')).toHaveText('AED 420.00');
  await expect(page.locator('[data-cost-value="total"]')).toHaveText('AED 1,470.00');
  await expect(page.locator('[data-cost-value="premium"]')).toHaveText('AED 70.00');
  await expect(page.locator('[data-cost-value="overtime"]')).toHaveText('AED 350.00');
  await expect(page.locator('[data-cost-coverage]')).toContainText('100.00%');
  await expect(page.locator('#cost-workbook')).toHaveValue('');
  await page.getByRole('button', { name: 'Apply filters', exact: true }).click();
  await expect(page.locator('[data-cost-status]')).toContainText('Rates loaded locally');
  await expect(page.locator('[data-cost-value="total"]')).toHaveText('AED 1,470.00');
  await page.getByLabel('Group costs by').selectOption('employees');
  await expect(page.locator('[data-cost-drivers]')).toContainText('Synthetic One');
  expect(requests.every(request => request.method === 'GET' && !request.body)).toBe(true);
  expect(requests.some(request => /synthetic-confidential|COST-001|1470|1050|Rate%2FManhour/i.test(request.url))).toBe(false);
  const storage = await page.evaluate(() => ({ local: JSON.stringify(localStorage), session: JSON.stringify(sessionStorage) }));
  expect(JSON.stringify(storage)).not.toMatch(/COST-001|Synthetic One|147000|Rate\/Manhour/);
  await page.getByRole('button', { name: 'Clear financial data' }).click();
  await expect(page.locator('[data-cost-results]')).toBeHidden();
  await expect(page.locator('[data-cost-drivers]')).toBeEmpty();
  await page.locator('#cost-workbook').setInputFiles(rateFile(validRows));
  await expect(page.locator('[data-cost-results]')).toBeVisible();
  await page.reload();
  await expect(page.locator('[data-cost-results]')).toBeHidden();
  await expect(page.locator('[data-cost-badge]')).toHaveText('Rates not loaded');
});

test('partial pricing and invalid imports are explicit, and financial data expires', async ({ page }) => {
  await openAnalytics(page);
  await page.clock.install();
  await page.locator('#cost-workbook').setInputFiles(rateFile([validRows[0]]));
  await expect(page.locator('[data-cost-value="total"]')).toHaveText('AED 1,050.00');
  await expect(page.locator('[data-cost-warning]')).toContainText('Missing rates');
  await expect(page.locator('[data-cost-matches]')).toContainText('5.00 hrs unpriced');
  await page.clock.fastForward(15 * 60 * 1000 + 1);
  await expect(page.locator('[data-cost-results]')).toBeHidden();
  await expect(page.locator('[data-cost-status]')).toContainText('expired');
  await page.locator('#cost-workbook').setInputFiles(rateFile([validRows[0], validRows[0]]));
  await expect(page.locator('[data-cost-status]')).toContainText('duplicate Employee ID');
  await expect(page.locator('[data-cost-results]')).toBeHidden();
});

test('layout stays within viewport in both themes and at mobile width', async ({ page }, testInfo) => {
  await openAnalytics(page);
  await page.locator('#cost-workbook').setInputFiles(rateFile(validRows));
  await expect(page.locator('[data-cost-results]')).toBeVisible();
  for (const width of [1512, 768, 390]) {
    await page.setViewportSize({ width, height: 900 });
    for (const theme of ['light', 'dark']) {
      await page.evaluate(theme => document.documentElement.setAttribute('data-bs-theme', theme), theme);
      expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
      const heights = await page.locator('.analytics-chart-scroll svg').evaluateAll(elements => elements.map(element => element.getBoundingClientRect().height));
      expect(heights.every(height => height <= 260)).toBe(true);
      const scales = await page.locator('.analytics-chart-scroll svg').evaluateAll(elements => elements.map(element => {
        const matrix = element.getScreenCTM();
        return { horizontal: matrix.a, vertical: matrix.d };
      }));
      for (const scale of scales) {
        expect(scale.horizontal).toBeCloseTo(1, 2);
        expect(scale.vertical).toBeCloseTo(1, 2);
      }
      if (width !== 768) await page.screenshot({ path: testInfo.outputPath(`analytics-${theme}-${width}.png`), fullPage: true });
      if (width === 1512 && theme === 'light') await page.locator('[data-financial-panel]').screenshot({ path: testInfo.outputPath('financial-panel-synthetic.png') });
    }
  }
});

test('long graph ranges scroll locally with round markers after filtering', async ({ page }, testInfo) => {
  await openAnalytics(page);
  await page.locator('#date_from').fill('2025-01-01');
  await page.locator('#date_to').fill('2026-12-31');
  await page.getByRole('button', { name: 'Apply filters', exact: true }).click();
  await expect(page.locator('[data-chart-months="24"]')).toHaveCount(2);
  for (const width of [1512, 390]) {
    await page.setViewportSize({ width, height: 900 });
    await expect.poll(async () => page.locator('.analytics-chart-scroll').first().evaluate(element => element.scrollWidth > element.clientWidth)).toBe(true);
    const geometry = await page.locator('.analytics-chart-scroll svg').evaluateAll(elements => elements.map(svg => {
      const matrix = svg.getScreenCTM();
      const mark = svg.querySelector('circle');
      const bounds = mark?.getBoundingClientRect();
      return { x: matrix.a, y: matrix.d, markerWidth: bounds?.width, markerHeight: bounds?.height };
    }));
    for (const item of geometry) {
      expect(item.x).toBeCloseTo(1, 2);
      expect(item.y).toBeCloseTo(1, 2);
      if (item.markerWidth) expect(item.markerWidth).toBeCloseTo(item.markerHeight, 2);
    }
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
    if (width === 1512) await page.locator('.analytics-charts').last().screenshot({ path: testInfo.outputPath('graphs-long-range.png') });
  }
});

test('leaving and returning does not restore the financial view', async ({ page }) => {
  await openAnalytics(page);
  await page.locator('#cost-workbook').setInputFiles(rateFile(validRows));
  await expect(page.locator('[data-cost-results]')).toBeVisible();
  await page.goto('/');
  await page.goBack();
  await expect(page.locator('[data-cost-results]')).toBeHidden();
});

test('invalid filters retain Laravel validation messages and clear financial data', async ({ page }) => {
  await openAnalytics(page);
  await page.locator('#cost-workbook').setInputFiles(rateFile(validRows));
  await expect(page.locator('[data-cost-results]')).toBeVisible();
  await page.locator('#date_from').fill('2026-09-01');
  await page.locator('#date_to').fill('2026-08-01');
  await page.getByRole('button', { name: 'Apply filters', exact: true }).click();
  await expect(page.locator('#date_to')).toHaveClass(/is-invalid/);
  await expect(page.getByText('The to date must be on or after the from date.', { exact: true })).toBeVisible();
  await expect(page.locator('[data-cost-results]')).toBeHidden();
});

function financeFile(code, labour = '', duplicate = false) {
  const context = vm.createContext({});
  vm.runInContext(readFileSync('public/js/vendor/xlsx-0.20.3.min.js', 'utf8'), context);
  const book = context.XLSX.utils.book_new();
  const rows = [['Project Code', 'Order Value', 'Invoice Submitted', 'Subcontractor Spent', 'Manhour Spent'], ['UNRELATED-SECRET', 9999999, 8888888, 7777777, 6666666], [code, 100000, 60000, 12000, labour]];
  if (duplicate) rows.push([code, 1, 1, 1, 1]);
  context.XLSX.utils.book_append_sheet(book, context.XLSX.utils.aoa_to_sheet(rows), 'Finance');
  return { name: 'synthetic-finance.xlsx', mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', buffer: Buffer.from(context.XLSX.write(book, { type: 'array', bookType: 'xlsx' })) };
}

test('finance isolates project figures, approved labour fallback and zero override; stays local across filters', async ({ page }, testInfo) => {
  await openAnalytics(page);
  const code = await page.locator('[data-finance-panel]').getAttribute('data-project-code');
  const requests = [];
  page.on('request', request => requests.push({ url: request.url(), method: request.method(), body: request.postData() }));
  await page.locator('#finance-workbook').setInputFiles(financeFile(code));
  await expect(page.locator('[data-finance-value="balance"]')).toHaveText('AED 40,000.00');
  await expect(page.locator('[data-finance-value="total"]')).toHaveText('Awaiting labour cost');
  await page.locator('#cost-workbook').setInputFiles(rateFile([validRows[1]]));
  await expect(page.locator('[data-cost-warning]')).toContainText('Missing rates');
  await expect(page.locator('[data-finance-value="total"]')).toHaveText('Awaiting labour cost');
  await page.locator('#cost-workbook').setInputFiles(rateFile([validRows[0]]));
  await expect(page.locator('[data-finance-value="total"]')).toHaveText('AED 17,850.00');
  await expect(page.locator('[data-finance-spend]')).toContainText('AED 4,800.00');
  await page.getByRole('button', { name: 'Apply filters', exact: true }).click();
  await expect(page.locator('#project-analytics')).not.toHaveAttribute('aria-busy', 'true');
  await expect(page.locator('[data-finance-value="total"]')).toHaveText('AED 17,850.00');
  await page.getByLabel('Rate currency', { exact: true }).selectOption('USD');
  await expect(page.locator('[data-finance-value="total"]')).toHaveText('Awaiting labour cost');
  await page.locator('#finance-workbook').setInputFiles(financeFile(code, 0));
  await expect(page.locator('[data-finance-value="total"]')).toHaveText('AED 16,800.00');
  await expect(page.locator('[data-finance-note]')).toContainText('imported Manhour Spent');
  for (const width of [1512, 390]) {
    await page.setViewportSize({ width, height: 900 });
    for (const theme of ['light', 'dark']) {
      await page.evaluate(theme => document.documentElement.setAttribute('data-bs-theme', theme), theme);
      expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
      await page.locator('[data-finance-panel]').screenshot({ path: testInfo.outputPath(`finance-${theme}-${width}.png`) });
    }
  }
  expect(requests.every(request => request.method === 'GET' && !request.body)).toBe(true);
  expect(requests.some(request => /UNRELATED-SECRET|synthetic-finance|60000|12000/.test(request.url))).toBe(false);
  expect(await page.evaluate(() => JSON.stringify([localStorage, sessionStorage]))).not.toMatch(/UNRELATED-SECRET|6000000|1200000/);
  await page.getByRole('button', { name: 'Clear financial data', exact: true }).click();
  await expect(page.locator('[data-finance-results]')).toBeHidden();
  await expect(page.locator('[data-finance-billing]')).toBeEmpty();
});

test('finance rejects duplicate and missing projects, clears on refresh and expires', async ({ page }) => {
  await openAnalytics(page);
  await page.clock.install();
  const code = await page.locator('[data-finance-panel]').getAttribute('data-project-code');
  await page.locator('#finance-workbook').setInputFiles(financeFile(code, 1, true));
  await expect(page.locator('[data-finance-status]')).toContainText('Duplicate rows');
  await page.locator('#finance-workbook').setInputFiles(financeFile('MISSING', 1));
  await expect(page.locator('[data-finance-status]')).toContainText('No row matches');
  await page.locator('#finance-workbook').setInputFiles(financeFile(code, 123));
  await expect(page.locator('[data-finance-value="total"]')).toHaveText('AED 16,923.00');
  await page.clock.fastForward(15 * 60 * 1000 + 1);
  await expect(page.locator('[data-finance-results]')).toBeHidden();
  await expect(page.locator('[data-finance-status]')).toContainText('expired');
  await page.locator('#finance-workbook').setInputFiles(financeFile(code, 123));
  await expect(page.locator('[data-finance-results]')).toBeVisible();
  await page.reload();
  await expect(page.locator('[data-finance-results]')).toBeHidden();
});

test('rates-first import supplies project labour and versioned workers use the matching calculation core', async ({ page }) => {
  await openAnalytics(page);
  const urls = [];
  page.on('request', request => { if (/project-cost-(worker|core)\.js/.test(request.url())) urls.push(request.url()); });
  await page.locator('#cost-workbook').setInputFiles(rateFile(validRows));
  await expect(page.locator('[data-cost-coverage]')).toContainText('100.00%');
  const code = await page.locator('[data-finance-panel]').getAttribute('data-project-code');
  await page.locator('#finance-workbook').setInputFiles(financeFile(code));
  await expect(page.locator('[data-finance-value="total"]')).toHaveText('AED 17,850.00');
  await expect(page.locator('[data-finance-note]')).toContainText('all approved platform hours');
  const script = await page.locator('script[src*="project-cost-view.js"]').getAttribute('src');
  const version = new URL(script).searchParams.get('v');
  expect(version).toMatch(/^[a-f0-9]{16}$/);
  expect(urls.filter(url => new URL(url).searchParams.get('v') === version)).toHaveLength(2);
});

test('an outdated calculation worker produces a refresh instruction instead of a missing-rates claim', async ({ page }, testInfo) => {
  await openAnalytics(page);
  await page.route('**/js/project-cost-worker.js*', async route => {
    const response = await route.fetch();
    const body = (await response.text()).replace(', management: MecProjectCost.calculate(data.managementBasis || [], rates)', '');
    await route.fulfill({ response, body });
  });
  await page.locator('#cost-workbook').setInputFiles(rateFile(validRows));
  await expect(page.locator('[data-cost-coverage]')).toContainText('100.00%');
  const code = await page.locator('[data-finance-panel]').getAttribute('data-project-code');
  await page.locator('#finance-workbook').setInputFiles(financeFile(code));
  await expect(page.locator('[data-finance-value="total"]')).toHaveText('Awaiting labour cost');
  await expect(page.locator('[data-finance-note]')).toContainText('project labour calculation was not received');
  await expect(page.locator('[data-finance-note]')).toContainText('Refresh this page');
  await page.locator('[data-finance-panel]').screenshot({ path: testInfo.outputPath('labour-refresh-instruction.png') });
});
