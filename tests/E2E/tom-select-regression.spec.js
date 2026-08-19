import { expect, test } from '@playwright/test';

async function login(page, email, password = 'password123') {
  await page.goto('/login');
  await page.locator('input[name="email"]').fill(email);
  await page.locator('input[name="password"]').fill(password);
  await page.getByRole('button', { name: /login/i }).click();
  await expect(page).toHaveURL(/\/$/);
}

function collectClientErrors(page) {
  const errors = [];

  page.on('pageerror', (error) => errors.push(`pageerror: ${error.stack || error.message}`));
  page.on('console', (message) => {
    if (message.type() === 'error') {
      errors.push(`console: ${message.text()}`);
    }
  });

  return errors;
}

async function expectNoDocumentOverflow(page) {
  const dimensions = await page.evaluate(() => ({
    body: document.body.scrollWidth,
    document: document.documentElement.scrollWidth,
    viewport: window.innerWidth,
  }));

  expect(dimensions.body).toBeLessThanOrEqual(dimensions.viewport + 1);
  expect(dimensions.document).toBeLessThanOrEqual(dimensions.viewport + 1);
}

async function expectTomSelectAssets(page) {
  await page.waitForFunction(() => typeof window.TomSelect === 'function');
  await expect(page.locator('link[href*="tom-select@2.6.2/dist/css/tom-select.bootstrap5.min.css"]')).toHaveCount(1);
  await expect(page.locator('script[src*="tom-select@2.6.2/dist/js/tom-select.complete.min.js"]')).toHaveCount(1);
}

async function chooseOpenPeriodIfNeeded(page) {
  const periodSelect = page.locator('select[name="period_id"]');
  await periodSelect.waitFor({ state: 'attached', timeout: 2_000 }).catch(() => null);

  if ((await periodSelect.count()) === 0) {
    return;
  }

  const periodValue = await periodSelect.locator('option').nth(1).getAttribute('value');
  expect(periodValue).toBeTruthy();

  await periodSelect.evaluate((select, value) => {
    select.tomselect?.setValue(value);
    select.value = value;
    select.dispatchEvent(new Event('change', { bubbles: true }));
  }, periodValue);
  await page.getByRole('button', { name: /continue/i }).click();
}

test('single selects preserve values, APIs, focus, scroll, themes, and responsive layout', async ({ page }) => {
  const errors = collectClientErrors(page);
  await login(page, 'superadmin@example.com');
  await page.goto('/manage/users/create');
  await expectTomSelectAssets(page);
  await page.waitForFunction(() => Boolean(document.getElementById('gender')?.tomselect));

  const gender = page.locator('#gender');
  const genderControl = page.locator('#gender + .ts-wrapper .ts-control');
  await gender.evaluate((select) => {
    select.dataset.regressionChanges = '0';
    select.addEventListener('change', () => {
      select.dataset.regressionChanges = String(Number(select.dataset.regressionChanges) + 1);
    });
  });

  await genderControl.click();
  await page.locator('.ts-dropdown .option', { hasText: 'Female' }).click();
  await expect(gender).toHaveValue('female');
  expect(Number(await gender.getAttribute('data-regression-changes'))).toBeGreaterThan(0);

  const apiState = await page.locator('#marital_status').evaluate((select) => {
    const instance = select.tomselect;
    instance.setValue('married');
    const selectedValue = instance.getValue();
    instance.clear();
    const clearedValue = instance.getValue();
    instance.disable();
    const disabled = instance.isDisabled && instance.wrapper.classList.contains('disabled');
    instance.enable();

    return {
      clearedValue,
      disabled,
      enabled: !instance.isDisabled,
      selectedValue,
    };
  });

  expect(apiState).toEqual({
    clearedValue: '',
    disabled: true,
    enabled: true,
    selectedValue: 'married',
  });
  await expect(page.locator('#marital_status')).toHaveValue('');

  const reinitialized = await page.locator('#marital_status').evaluate((select) => {
    select.tomselect.destroy();
    const instance = new window.TomSelect(select, {
      allowEmptyOption: true,
      create: false,
      dropdownParent: 'body',
      maxOptions: null,
      searchField: ['text'],
      sortField: [{ field: '$order' }],
    });
    instance.setValue('single', true);

    return {
      initialized: select.tomselect === instance,
      value: instance.getValue(),
    };
  });

  expect(reinitialized).toEqual({ initialized: true, value: 'single' });

  const focusResult = await page.evaluate(async () => {
    const instances = Array.from(document.querySelectorAll('select.form-select'))
      .map((select) => select.tomselect)
      .filter(Boolean);
    const first = instances[0];
    const second = instances.at(-1);
    const scrollSamples = [];
    let scrollEvents = 0;
    const recordScroll = () => { scrollEvents += 1; };

    window.addEventListener('scroll', recordScroll, { passive: true });
    first.focus();
    second.focus();

    for (let index = 0; index < 12; index += 1) {
      await new Promise((resolve) => window.setTimeout(resolve, 25));
      scrollSamples.push(window.scrollY);
    }

    window.removeEventListener('scroll', recordScroll);
    const finalSamples = scrollSamples.slice(-5);
    const activeElement = document.activeElement;

    return {
      activeIsSecond: activeElement === second.focus_node || activeElement === second.control_input,
      finalScrollRange: Math.max(...finalSamples) - Math.min(...finalSamples),
      focusedControls: document.querySelectorAll('.ts-wrapper.focus').length,
      openControls: document.querySelectorAll('.ts-wrapper.dropdown-active').length,
      scrollEvents,
    };
  });

  expect(focusResult.activeIsSecond).toBe(true);
  expect(focusResult.focusedControls).toBe(1);
  expect(focusResult.openControls).toBeLessThanOrEqual(1);
  expect(focusResult.finalScrollRange).toBeLessThanOrEqual(1);
  expect(focusResult.scrollEvents).toBeLessThan(20);

  const focusedControl = page.locator('.ts-wrapper.focus .ts-control');
  await expect(focusedControl).toBeVisible();
  expect(await focusedControl.evaluate((element) => getComputedStyle(element).boxShadow)).not.toBe('none');

  await page.locator('[data-theme-toggle]').first().click();
  await expect(page.locator('html')).toHaveAttribute('data-bs-theme', 'dark');
  const darkColors = await genderControl.evaluate((element) => {
    const style = getComputedStyle(element);
    return { background: style.backgroundColor, color: style.color };
  });
  expect(darkColors.background).not.toBe(darkColors.color);

  await page.setViewportSize({ width: 390, height: 844 });
  await expectNoDocumentOverflow(page);
  expect(errors).toEqual([]);
});

test('leave-plan selects enable, select, clear, and disable through real interaction', async ({ page }) => {
  const errors = collectClientErrors(page);
  await login(page, 'carla@example.com');
  await page.goto('/my-leave-plans/create');
  await expectTomSelectAssets(page);
  await page.waitForFunction(() => Boolean(document.getElementById('duration_type')?.tomselect));

  const halfDayPeriod = page.locator('#half_day_period');
  expect(await halfDayPeriod.evaluate((select) => select.tomselect.isDisabled)).toBe(true);

  await page.locator('#duration_type + .ts-wrapper .ts-control').click();
  await page.locator('.ts-dropdown .option', { hasText: 'Half day' }).click();
  await expect(page.locator('#duration_type')).toHaveValue('half_day');
  expect(await halfDayPeriod.evaluate((select) => select.tomselect.isDisabled)).toBe(false);

  await page.locator('#half_day_period + .ts-wrapper .ts-control').click();
  await page.locator('.ts-dropdown .option', { hasText: 'Morning' }).click();
  await expect(halfDayPeriod).toHaveValue('morning');

  await page.locator('#duration_type + .ts-wrapper .ts-control').click();
  await page.locator('.ts-dropdown .option', { hasText: 'Full day' }).click();
  await expect(halfDayPeriod).toHaveValue('');
  expect(await halfDayPeriod.evaluate((select) => select.tomselect.isDisabled)).toBe(true);
  expect(errors).toEqual([]);
});

test('async multi-select loads, selects, removes, refreshes, and handles scrolling', async ({ page }) => {
  const errors = collectClientErrors(page);
  await login(page, 'superadmin@example.com');
  await page.goto('/manage/users');
  await expectTomSelectAssets(page);
  await page.waitForFunction(() => Boolean(document.getElementById('search')?.tomselect));

  const search = page.locator('#search');
  const controlInput = page.locator('#search + .ts-wrapper .ts-control input');
  const responsePromise = page.waitForResponse((response) => response.url().includes('user_lookup=1'));
  await controlInput.click();
  await controlInput.fill('Aisha');
  await responsePromise;
  const aishaOption = page.locator('.ts-dropdown .option', { hasText: 'Aisha Khan' }).first();
  await expect(aishaOption).toBeVisible();
  await aishaOption.click();
  await expect(search.locator('option:checked')).toHaveText('Aisha Khan');

  await page.locator('#search + .ts-wrapper .remove').click();
  await expect(search.locator('option:checked')).toHaveCount(0);

  const refreshed = await search.evaluate((select) => {
    const instance = select.tomselect;
    instance.addOption({ value: 'regression-user', text: 'Regression User' });
    instance.refreshOptions(false);
    instance.dropdown_content.scrollTop = instance.dropdown_content.scrollHeight;
    instance.dropdown_content.dispatchEvent(new Event('scroll', { bubbles: true }));
    const hasOption = Boolean(instance.options['regression-user']);
    instance.clear();
    return { cleared: instance.getValue(), hasOption };
  });
  expect(refreshed).toEqual({ cleared: [], hasOption: true });

  await page.goto('/admin/timesheets');
  const nativeSelectState = await page.locator('#corrections').evaluate((select) => ({
    hasTomSelect: Boolean(select.tomselect),
    searchable: select.dataset.searchable,
  }));
  expect(nativeSelectState).toEqual({ hasTomSelect: false, searchable: 'false' });
  await page.locator('#corrections').selectOption('open');
  await expect(page.locator('#corrections')).toHaveValue('open');
  await expectNoDocumentOverflow(page);
  expect(errors).toEqual([]);
});

test('timesheet validation and dynamic row workflows remain synchronized', async ({ page }) => {
  const errors = collectClientErrors(page);
  await login(page, 'carla@example.com');
  await page.goto('/my-timesheets/create');
  await chooseOpenPeriodIfNeeded(page);
  await expectTomSelectAssets(page);

  const sourceRow = page.locator('[data-entry-row]').first();
  await expect(sourceRow).toBeVisible();
  const sourceDate = await sourceRow.getAttribute('data-work-date');
  const sourceRows = page.locator(`[data-entry-row][data-work-date="${sourceDate}"]`);
  const attendance = sourceRow.locator('select[name*="[attendance_code]"]');
  await attendance.evaluate((select) => select.tomselect.clear());
  await sourceRow.locator('select[name*="[project_id]"]').evaluate((select) => select.tomselect.clear());
  await sourceRow.locator('input[name*="[regular_hours]"]').fill('2.50');
  await page.getByRole('button', { name: /save draft/i }).click();
  await expect(sourceRow).toHaveClass(/timesheet-entry-row-client-invalid/);
  await expect(sourceRow.locator('.attendance-select + .ts-wrapper')).toHaveClass(/is-invalid/);

  const attendanceInput = sourceRow.locator('.attendance-select + .ts-wrapper .ts-control input');
  await attendanceInput.click();
  await attendanceInput.fill('O100');
  await attendanceInput.press('ArrowDown');
  await attendanceInput.press('Enter');
  await expect(attendance).toHaveValue('O100');
  await expect(sourceRow).not.toHaveClass(/timesheet-entry-row-client-invalid/);

  await sourceRow.getByRole('button', { name: /add project/i }).click();
  await expect(sourceRows).toHaveCount(2);
  await sourceRow.locator('input[name*="[remarks]"]').fill('Regression copy');
  await sourceRow.getByRole('button', { name: /duplicate/i }).click();
  await expect(sourceRows).toHaveCount(3);
  await expect(sourceRows.nth(1).locator('select[name*="[attendance_code]"]')).toHaveValue('O100');
  await expect(sourceRows.nth(1).locator('input[name*="[remarks]"]')).toHaveValue('Regression copy');
  await sourceRows.nth(1).getByRole('button', { name: /remove/i }).click();
  await expect(sourceRows).toHaveCount(2);

  const workDates = await page.locator('[data-day-summary-row]').evaluateAll((rows) => rows.map((row) => row.dataset.workDate));
  const targetDate = workDates.find((date) => date !== sourceDate);
  expect(targetDate).toBeTruthy();
  await page.locator(`[data-day-summary-row][data-work-date="${sourceDate}"]`).getByRole('button', { name: /copy/i }).click();
  await page.locator(`#copyDayModal input[value="${targetDate}"]`).check();
  await page.locator('#copyDayPasteButton').click();

  const targetRows = page.locator(`[data-entry-row][data-work-date="${targetDate}"]`);
  await expect(targetRows).toHaveCount(2);
  await expect(targetRows.first().locator('select[name*="[attendance_code]"]')).toHaveValue('O100');
  await expect(targetRows.first().locator('input[name*="[regular_hours]"]')).toHaveValue('2.50');
  await expectNoDocumentOverflow(page);
  expect(errors).toEqual([]);
});
