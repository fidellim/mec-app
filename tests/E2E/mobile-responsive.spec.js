import { expect, test } from '@playwright/test';

async function login(page, email, password = 'password123') {
  await page.goto('/login');
  await page.locator('input[name="email"]').fill(email);
  await page.locator('input[name="password"]').fill(password);
  await page.getByRole('button', { name: /login/i }).click();
  await expect(page).toHaveURL(/\/$/);
}

async function chooseOpenPeriodIfNeeded(page) {
  const periodSelect = page.locator('select[name="period_id"]');

  await periodSelect.waitFor({ state: 'attached', timeout: 2000 }).catch(() => null);

  if ((await periodSelect.count()) === 0) {
    return;
  }

  const periodValue = await periodSelect.locator('option').nth(1).getAttribute('value');
  expect(periodValue).toBeTruthy();

  await periodSelect.evaluate((element, selectedValue) => {
    element.tomselect?.setValue(selectedValue);
    element.value = selectedValue;
    element.dispatchEvent(new Event('change', { bubbles: true }));
  }, periodValue);
  await page.getByRole('button', { name: /continue/i }).click();
}

async function expectNoDocumentOverflow(page) {
  const size = await page.evaluate(() => ({
    viewportWidth: window.innerWidth,
    documentWidth: document.documentElement.scrollWidth,
    bodyWidth: document.body.scrollWidth,
  }));

  expect(size.documentWidth).toBeLessThanOrEqual(size.viewportWidth + 1);
  expect(size.bodyWidth).toBeLessThanOrEqual(size.viewportWidth + 1);
}

test.describe('mobile responsive layout', () => {
  test.use({
    viewport: { width: 390, height: 844 },
    isMobile: true,
    hasTouch: true,
  });

  test('authenticated navigation is collapsed and can be opened on phones', async ({ page }) => {
    await login(page, process.env.E2E_EMPLOYEE_EMAIL || 'carla@example.com');

    await expect(page.locator('[data-mobile-sidebar-toggle]')).toBeVisible();
    await expect(page.locator('.sidebar nav')).not.toBeVisible();
    await expectNoDocumentOverflow(page);

    await page.locator('[data-mobile-sidebar-toggle]').click();
    await expect(page.locator('.sidebar nav')).toBeVisible();
    await expect(page.locator('[data-mobile-sidebar-toggle]')).toHaveAttribute('aria-expanded', 'true');
    await expect(page.getByRole('link', { name: /my timesheets/i })).toBeVisible();
    await expectNoDocumentOverflow(page);
  });

  test('weekly timesheet form stacks entry fields without page overflow', async ({ page }) => {
    await login(page, process.env.E2E_EMPLOYEE_EMAIL || 'carla@example.com');
    await page.goto('/my-timesheets/create');
    await chooseOpenPeriodIfNeeded(page);

    await expect(page.locator('[data-entry-row]').first()).toBeVisible();
    await expect(page.locator('.timesheet-day-column-row').first()).toBeHidden();

    const rowDisplay = await page.locator('[data-entry-row]').first().evaluate((row) => getComputedStyle(row).display);
    const cellDisplay = await page.locator('[data-entry-row]').first().locator('td').first().evaluate((cell) => getComputedStyle(cell).display);

    expect(rowDisplay).toBe('block');
    expect(cellDisplay).toBe('block');
    await expectNoDocumentOverflow(page);
  });
});
