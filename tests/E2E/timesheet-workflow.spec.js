import { expect, test } from '@playwright/test';

const employeeEmail = process.env.E2E_EMPLOYEE_EMAIL || 'carla@example.com';

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

async function expectNoOrphanedTooltips(page) {
  await page.mouse.move(0, 0);
  await expect(page.locator('.tooltip')).toHaveCount(0);
}

async function chooseFirstOpenPeriod(page) {
  const periodSelect = page.locator('select[name="period_id"]');

  if (!await waitForPeriodSelect(page)) {
    return;
  }

  const firstPeriodValue = await periodSelect.locator('option').nth(1).getAttribute('value');
  expect(firstPeriodValue).toBeTruthy();
  await setSelectValue(periodSelect, firstPeriodValue);
  await page.getByRole('button', { name: /continue/i }).click();
}

async function chooseOpenPeriodWithCreateForm(page) {
  const periodSelect = page.locator('select[name="period_id"]');

  if (!await waitForPeriodSelect(page)) {
    return;
  }

  const optionValues = await periodSelect.locator('option').evaluateAll((options) => options
    .map((option) => option.value)
    .filter(Boolean));

  for (const value of optionValues) {
    await setSelectValue(periodSelect, value);
    await page.getByRole('button', { name: /continue/i }).click();

    if (await page.locator('select[name*="[project_id]"]').first().waitFor({ state: 'attached', timeout: 3000 }).then(() => true).catch(() => false)) {
      return;
    }

    await page.goto('/my-timesheets/create');
  }
}

async function waitForPeriodSelect(page) {
  const periodSelect = page.locator('select[name="period_id"]');
  await periodSelect.waitFor({ state: 'attached', timeout: 2000 }).catch(() => null);

  return (await periodSelect.count()) > 0;
}

async function setSelectValue(select, value) {
  await select.evaluate((element, selectedValue) => {
    element.tomselect?.setValue(selectedValue);
    element.value = selectedValue;
    element.dispatchEvent(new Event('change', { bubbles: true }));
  }, value);
}

test.describe('employee timesheet workflow', () => {
  test('employee can open timesheets and reach the weekly form or existing weekly record', async ({ page }) => {
    await login(page, 'aisha@example.com');

    await page.getByRole('link', { name: /my timesheets/i }).click();
    await expect(page.getByRole('heading', { name: /my timesheets/i })).toBeVisible();

    await page.getByRole('link', { name: /create weekly timesheet/i }).click();
    await chooseFirstOpenPeriod(page);
    await expect(page).toHaveURL(/\/my-timesheets\/(create|\d+)/);
    await expect(page.getByText(/weekly period|timesheet summary/i)).toBeVisible();
  });

  test('employee timesheet form keeps dynamically added project rows usable', async ({ page }) => {
    const errors = collectClientErrors(page);
    await login(page, employeeEmail);
    await page.goto('/my-timesheets/create');
    await chooseOpenPeriodWithCreateForm(page);

    const firstProjectSelect = page.locator('select[name*="[project_id]"]').first();
    const assignedProject = firstProjectSelect.locator('option', { hasText: /^P300$/ });
    const unassignedProject = firstProjectSelect.locator('option', { hasText: /^P21008$/ });
    await expect(assignedProject).toHaveCount(1);
    await expect(unassignedProject).toHaveCount(0);
    const firstProjectValue = await assignedProject.getAttribute('value');
    expect(firstProjectValue).toBeTruthy();

    await firstProjectSelect.evaluate((select, value) => {
      select.tomselect?.setValue(value);
      select.value = value;
    }, firstProjectValue);

    const addProjectButton = page.getByRole('button', { name: /add project/i }).first();

    if (await addProjectButton.isVisible()) {
      const firstWorkDate = await page.locator('[data-entry-row]').first().getAttribute('data-work-date');
      expect(firstWorkDate).toBeTruthy();
      const firstDayRows = page.locator(`[data-entry-row][data-work-date="${firstWorkDate}"]`);

      await addProjectButton.click();
      await expect(page.getByRole('button', { name: /remove/i }).first()).toBeVisible();
      await expect(page.locator('select[name*="[project_id]"]').nth(1)).toHaveCount(1);
      await expect(firstProjectSelect).toHaveValue(firstProjectValue);
      await expect(page.locator('select[name*="[project_id]"]').nth(1)).toHaveValue('');
      await expect(firstDayRows).toHaveCount(2);
      await expect(firstDayRows.getByRole('button', { name: /add project/i })).toHaveCount(1);
      await expectNoOrphanedTooltips(page);

      await firstDayRows.first().locator('select[name*="[attendance_code]"]').evaluate((select) => {
        select.tomselect?.setValue('O100');
        select.value = 'O100';
      });
      await firstDayRows.first().locator('input[name*="[regular_hours]"]').fill('3.50');
      await firstDayRows.first().locator('input[name*="[overtime_hours]"]').fill('1.25');
      await firstDayRows.first().locator('input[name*="[remarks]"]').fill('Copied row');
      await firstDayRows.first().getByRole('button', { name: /duplicate/i }).click();
      await expect(firstDayRows).toHaveCount(3);
      await expectNoOrphanedTooltips(page);
      await expect(firstDayRows.nth(1).locator('select[name*="[attendance_code]"]')).toHaveValue('O100');
      await expect(firstDayRows.nth(1).locator('select[name*="[project_id]"]')).toHaveValue(firstProjectValue);
      await expect(firstDayRows.nth(1).locator('input[name*="[regular_hours]"]')).toHaveValue('3.50');
      await expect(firstDayRows.nth(1).locator('input[name*="[overtime_hours]"]')).toHaveValue('1.25');
      await expect(firstDayRows.nth(1).locator('input[name*="[remarks]"]')).toHaveValue('Copied row');

      await firstDayRows.getByRole('button', { name: /add project/i }).click();
      await expect(firstDayRows).toHaveCount(4);
      await expectNoOrphanedTooltips(page);
      await expect(firstDayRows.getByRole('button', { name: /add project/i })).toHaveCount(1);

      await firstDayRows.first().getByRole('button', { name: /remove/i }).click();
      await expect(firstDayRows).toHaveCount(3);
      await expectNoOrphanedTooltips(page);
      await expect(firstDayRows.getByRole('button', { name: /add project/i })).toHaveCount(1);
    }

    expect(errors).toEqual([]);
  });

  test('employee timesheet form shows full and ISO dates together', async ({ page }) => {
    await login(page, employeeEmail);
    await page.goto('/my-timesheets/create');
    await chooseOpenPeriodWithCreateForm(page);

    const firstDaySummary = page.locator('[data-day-summary-row]').first();
    await expect(firstDaySummary.locator('[data-date-label]')).toContainText(/^[A-Z][a-z]+ \d{1,2}, 20\d{2}$/);
    await expect(firstDaySummary).toContainText(/\(\d{4}-\d{2}-\d{2}\)/);
  });

  test('employee timesheet form does not submit when Enter is pressed in an entry field', async ({ page }) => {
    await login(page, employeeEmail);
    await page.goto('/my-timesheets/create');
    await chooseOpenPeriodWithCreateForm(page);

    const regularHours = page.locator('input[name*="[regular_hours]"]').first();
    await expect(regularHours).toBeVisible();

    const formUrl = page.url();
    await regularHours.fill('4.00');
    await regularHours.press('Enter');
    await page.waitForTimeout(500);

    expect(page.url()).toBe(formUrl);
    await expect(page.getByRole('button', { name: /save draft/i })).toBeVisible();
    await expect(page.getByRole('button', { name: /submit for approval/i })).toBeVisible();
    await expect(regularHours).toHaveValue('4.00');
  });

  test('employee can copy one day and overwrite selected target days', async ({ page }) => {
    const errors = collectClientErrors(page);
    await login(page, employeeEmail);
    await page.goto('/my-timesheets/create');
    await chooseOpenPeriodWithCreateForm(page);

    const projectSelect = page.locator('select[name*="[project_id]"]').first();
    const projectValue = await projectSelect.locator('option', { hasText: /^P300$/ }).getAttribute('value');
    expect(projectValue).toBeTruthy();

    const workDates = await page.locator('[data-day-summary-row]').evaluateAll((rows) => rows.map((row) => row.dataset.workDate));
    expect(workDates.length).toBeGreaterThan(1);

    const sourceDate = workDates[0];
    const targetDate = workDates[1];
    const sourceRows = page.locator(`[data-entry-row][data-work-date="${sourceDate}"]`);
    const targetRows = page.locator(`[data-entry-row][data-work-date="${targetDate}"]`);

    await sourceRows.first().locator('select[name*="[attendance_code]"]').evaluate((select) => {
      select.tomselect?.setValue('O100');
      select.value = 'O100';
    });
    await sourceRows.first().locator('select[name*="[project_id]"]').evaluate((select, value) => {
      select.tomselect?.setValue(value);
      select.value = value;
    }, projectValue);
    await sourceRows.first().locator('input[name*="[regular_hours]"]').fill('3.50');
    await sourceRows.first().locator('input[name*="[overtime_hours]"]').fill('1.25');
    await sourceRows.first().locator('input[name*="[remarks]"]').fill('Copied first row');

    await sourceRows.first().getByRole('button', { name: /add project/i }).click();
    await expect(sourceRows).toHaveCount(2);
    await sourceRows.nth(1).locator('select[name*="[attendance_code]"]').evaluate((select) => {
      select.tomselect?.setValue('O100');
      select.value = 'O100';
    });
    await sourceRows.nth(1).locator('select[name*="[project_id]"]').evaluate((select, value) => {
      select.tomselect?.setValue(value);
      select.value = value;
    }, projectValue);
    await sourceRows.nth(1).locator('input[name*="[regular_hours]"]').fill('2.00');
    await sourceRows.nth(1).locator('input[name*="[remarks]"]').fill('Copied second row');

    await targetRows.first().locator('input[name*="[regular_hours]"]').fill('9.00');
    await targetRows.first().locator('input[name*="[remarks]"]').fill('Will be replaced');

    await page.locator(`[data-day-summary-row][data-work-date="${sourceDate}"]`).getByRole('button', { name: /copy/i }).click();
    await expect(page.getByRole('heading', { name: /paste copied day/i })).toBeVisible();
    await expect(page.getByText(/replace all existing entries/i)).toBeVisible();
    await expect(page.locator('#copyDayPasteButton')).toBeDisabled();

    await page.locator(`#copyDayModal input[value="${targetDate}"]`).check();
    await expect(page.locator('#copyDayPasteButton')).toBeEnabled();
    await page.locator('#copyDayPasteButton').click();
    await expect(page.getByRole('heading', { name: /paste copied day/i })).toBeHidden();

    await expect(targetRows).toHaveCount(2);
    await expect(targetRows.first().locator('input[name*="[work_date]"]')).toHaveValue(targetDate);
    await expect(targetRows.nth(1).locator('input[name*="[work_date]"]')).toHaveValue(targetDate);
    await expect(targetRows.first().locator('select[name*="[attendance_code]"]')).toHaveValue('O100');
    await expect(targetRows.first().locator('select[name*="[project_id]"]')).toHaveValue(projectValue);
    await expect(targetRows.first().locator('input[name*="[regular_hours]"]')).toHaveValue('3.50');
    await expect(targetRows.first().locator('input[name*="[overtime_hours]"]')).toHaveValue('1.25');
    await expect(targetRows.first().locator('input[name*="[remarks]"]')).toHaveValue('Copied first row');
    await expect(targetRows.nth(1).locator('input[name*="[regular_hours]"]')).toHaveValue('2.00');
    await expect(targetRows.nth(1).locator('input[name*="[remarks]"]')).toHaveValue('Copied second row');
    await expect(page.locator(`[data-day-summary-row][data-work-date="${targetDate}"] [data-day-regular-total]`)).toContainText('RT 5.50');
    await expect(page.locator(`[data-day-summary-row][data-work-date="${targetDate}"] [data-day-overtime-total]`)).toContainText('OT 1.25');
    expect(errors).toEqual([]);
  });
});

test.describe('approval and admin workflows', () => {
  test('hod can view department approval pages', async ({ page }, testInfo) => {
    await login(page, 'eng.hod@example.com');

    await page.goto('/department/timesheets');
    await expect(page.getByRole('heading', { name: /department timesheets|pending approvals/i })).toBeVisible();

    await page.goto('/department/tracker');
    await expect(page.getByRole('heading', { name: /submission tracker/i })).toBeVisible();
    await expect(page.getByText(/submitted|approved|rejected|not submitted/i).first()).toBeVisible();
  });

  test('admin export without a year is blocked without starting a download', async ({ page }) => {
    await login(page, 'admin@example.com');

    await page.goto('/admin/timesheets');
    await expect(page.getByRole('heading', { name: /timesheets/i })).toBeVisible();

    let downloadCount = 0;
    page.on('download', () => { downloadCount += 1; });
    await page.getByRole('link', { name: /export excel/i }).click();
    await expect(page.locator('#appToastContainer').getByText('Timesheet Excel exports are limited to one year at a time. Please select a year before exporting.')).toBeVisible();
    expect(downloadCount).toBe(0);
  });

  test('admin can filter by year and download an Excel export', async ({ page }) => {
    await login(page, 'admin@example.com');

    await page.goto('/admin/timesheets');
    await page.locator('#year').fill(String(new Date().getFullYear()));
    await page.getByRole('button', { name: /apply filters/i }).click();
    await expect(page.locator('#year')).toHaveValue(String(new Date().getFullYear()));

    const downloadPromise = page.waitForEvent('download');
    await page.getByRole('link', { name: /export excel/i }).click();
    const download = await downloadPromise;
    expect(download.suggestedFilename()).toMatch(/\.xlsx$/);
  });
});

test.describe('super admin management', () => {
  test('super admin can open management pages', async ({ page }) => {
    await login(page, 'superadmin@example.com');

    await page.goto('/manage/users');
    await expect(page.getByRole('heading', { name: /users/i })).toBeVisible();

    await page.goto('/manage/departments');
    await expect(page.getByRole('heading', { name: /departments/i })).toBeVisible();

    await page.goto('/manage/projects');
    await expect(page.getByRole('heading', { name: /projects/i })).toBeVisible();

    await page.goto('/manage/audit-logs');
    await expect(page.getByRole('heading', { name: /audit logs/i })).toBeVisible();
  });
});
