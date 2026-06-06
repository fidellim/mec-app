import { expect, test } from '@playwright/test';

const employeeEmail = process.env.E2E_EMPLOYEE_EMAIL || 'carla@example.com';

async function login(page, email, password = 'password123') {
  await page.goto('/login');
  await page.locator('input[name="email"]').fill(email);
  await page.locator('input[name="password"]').fill(password);
  await page.getByRole('button', { name: /login/i }).click();
  await expect(page).toHaveURL(/\/$/);
}

async function chooseFirstOpenPeriod(page) {
  const periodSelect = page.locator('select[name="period_id"]');

  if (await periodSelect.isVisible()) {
    const firstPeriodValue = await periodSelect.locator('option').nth(1).getAttribute('value');
    expect(firstPeriodValue).toBeTruthy();
    await periodSelect.selectOption(firstPeriodValue);
    await page.getByRole('button', { name: /continue/i }).click();
  }
}

async function chooseOpenPeriodWithCreateForm(page) {
  const periodSelect = page.locator('select[name="period_id"]');

  if (! await periodSelect.isVisible()) {
    return;
  }

  const optionValues = await periodSelect.locator('option').evaluateAll((options) => options
    .map((option) => option.value)
    .filter(Boolean));

  for (const value of optionValues) {
    await periodSelect.selectOption(value);
    await page.getByRole('button', { name: /continue/i }).click();

    if (await page.locator('select[name*="[project_id]"]').first().isVisible()) {
      return;
    }

    await page.goto('/my-timesheets/create');
  }
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
    await login(page, employeeEmail);
    await page.goto('/my-timesheets/create');
    await chooseOpenPeriodWithCreateForm(page);

    const firstProjectSelect = page.locator('select[name*="[project_id]"]').first();
    const firstProjectValue = await firstProjectSelect.locator('option').nth(1).getAttribute('value');
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
      await expect(page.locator('.tooltip')).toHaveCount(0);

      await firstDayRows.first().locator('select[name*="[attendance_code]"]').evaluate((select) => {
        select.tomselect?.setValue('O100');
        select.value = 'O100';
      });
      await firstDayRows.first().locator('input[name*="[regular_hours]"]').fill('3.50');
      await firstDayRows.first().locator('input[name*="[overtime_hours]"]').fill('1.25');
      await firstDayRows.first().locator('input[name*="[remarks]"]').fill('Copied row');
      await firstDayRows.first().getByRole('button', { name: /duplicate/i }).click();
      await expect(firstDayRows).toHaveCount(3);
      await expect(page.locator('.tooltip')).toHaveCount(0);
      await expect(firstDayRows.nth(1).locator('select[name*="[attendance_code]"]')).toHaveValue('O100');
      await expect(firstDayRows.nth(1).locator('select[name*="[project_id]"]')).toHaveValue(firstProjectValue);
      await expect(firstDayRows.nth(1).locator('input[name*="[regular_hours]"]')).toHaveValue('3.50');
      await expect(firstDayRows.nth(1).locator('input[name*="[overtime_hours]"]')).toHaveValue('1.25');
      await expect(firstDayRows.nth(1).locator('input[name*="[remarks]"]')).toHaveValue('Copied row');

      await firstDayRows.getByRole('button', { name: /add project/i }).click();
      await expect(firstDayRows).toHaveCount(4);
      await expect(page.locator('.tooltip')).toHaveCount(0);
      await expect(firstDayRows.getByRole('button', { name: /add project/i })).toHaveCount(1);

      await firstDayRows.first().getByRole('button', { name: /remove/i }).click();
      await expect(firstDayRows).toHaveCount(3);
      await expect(page.locator('.tooltip')).toHaveCount(0);
      await expect(firstDayRows.getByRole('button', { name: /add project/i })).toHaveCount(1);
    }
  });

  test('employee timesheet form shows full and ISO dates together', async ({ page }) => {
    await login(page, employeeEmail);
    await page.goto('/my-timesheets/create');
    await chooseOpenPeriodWithCreateForm(page);

    const firstDaySummary = page.locator('[data-day-summary-row]').first();
    await expect(firstDaySummary.locator('[data-date-label]')).toContainText(/^[A-Z][a-z]+ \d{1,2}, 20\d{2}$/);
    await expect(firstDaySummary).toContainText(/\(\d{4}-\d{2}-\d{2}\)/);
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

  test('admin can view records and request an export', async ({ page }) => {
    await login(page, 'admin@example.com');

    await page.goto('/admin/timesheets');
    await expect(page.getByRole('heading', { name: /timesheets/i })).toBeVisible();

    const downloadPromise = page.waitForEvent('download');
    await page.getByRole('link', { name: /export/i }).click();
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
