import { expect, test } from '@playwright/test';

async function login(page, email, password = 'password123') {
  await page.goto('/login');
  await page.getByLabel(/email/i).fill(email);
  await page.getByLabel(/password/i).fill(password);
  await page.getByRole('button', { name: /login/i }).click();
  await expect(page).toHaveURL(/\/$/);
}

test.describe('employee timesheet workflow', () => {
  test('employee can open timesheets and reach the weekly form or existing weekly record', async ({ page }) => {
    await login(page, 'aisha@example.com');

    await page.getByRole('link', { name: /my timesheets/i }).click();
    await expect(page.getByRole('heading', { name: /my timesheets/i })).toBeVisible();

    await page.getByRole('link', { name: /create weekly timesheet/i }).click();
    await expect(page).toHaveURL(/\/my-timesheets\/(create|\d+)/);
    await expect(page.getByText(/weekly period|timesheet summary/i)).toBeVisible();
  });

  test('employee timesheet form keeps dynamically added project rows usable', async ({ page }) => {
    await login(page, 'aisha@example.com');
    await page.goto('/my-timesheets/create');

    const addProjectButton = page.getByRole('button', { name: /add project/i }).first();

    if (await addProjectButton.isVisible()) {
      await addProjectButton.click();
      await expect(page.getByRole('button', { name: /remove/i }).first()).toBeVisible();
      await expect(page.locator('select[name*="[project_id]"]').nth(1)).toBeVisible();
    }
  });
});

test.describe('approval and admin workflows', () => {
  test('hod can view department approval pages', async ({ page }) => {
    await login(page, 'hod.engineering@example.com');

    await page.goto('/department/timesheets');
    await expect(page.getByRole('heading', { name: /department timesheets|pending approvals/i })).toBeVisible();

    await page.goto('/department/tracker');
    await expect(page.getByText(/submitted|approved|rejected|not submitted/i)).toBeVisible();
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
