import { expect, test } from '@playwright/test';

async function login(page, email = 'admin@example.com', password = 'password123') {
  await page.goto('/login');
  await page.locator('input[name="email"]').fill(email);
  await page.locator('input[name="password"]').fill(password);
  await page.getByRole('button', { name: /login/i }).click();
  await expect(page).toHaveURL(/\/$/);
}

async function openHolidayForm(page, theme) {
  await page.addInitScript((selectedTheme) => {
    window.localStorage.setItem('theme', selectedTheme);
  }, theme);

  await login(page);
  await page.goto('/manage/holidays/create');
  await expect(page.getByRole('heading', { name: /new holiday/i })).toBeVisible();
  await expect(page.locator('html')).toHaveAttribute('data-bs-theme', theme);
  await page.waitForFunction(() => Boolean(document.getElementById('holiday_date')?._flatpickr));

  const startDate = page.locator('input[name="holiday_date"]');
  const endDate = page.locator('input[name="holiday_end_date"]');

  return { endDate, startDate };
}

async function openHolidayPicker(page, theme) {
  const { endDate, startDate } = await openHolidayForm(page, theme);
  const usesNativeMobileInput = await startDate.evaluate((input) => input._flatpickr.isMobile);
  test.skip(usesNativeMobileInput, 'Flatpickr delegates to the native date input on mobile browsers.');

  await startDate.click();
  const picker = page.locator('.flatpickr-calendar.open').first();
  await expect(picker).toBeVisible();

  return { endDate, picker, startDate };
}

async function pickerBackground(page) {
  return page.locator('.flatpickr-calendar.open').first().evaluate((element) => {
    const style = window.getComputedStyle(element);

    return {
      background: style.backgroundColor,
      color: style.color,
    };
  });
}

test.describe('date picker theme and month/year selection', () => {
  test('date picker stays readable in dark mode and supports month/year selection', async ({ page }) => {
    const { picker, startDate } = await openHolidayPicker(page, 'dark');
    const colors = await pickerBackground(page);

    expect(colors.background).not.toBe('rgb(255, 255, 255)');
    expect(colors.background).not.toBe(colors.color);

    const monthSelect = picker.locator('.flatpickr-monthDropdown-months').first();
    const yearInput = picker.locator('input.cur-year').first();

    await expect(monthSelect).toBeVisible();
    await expect(yearInput).toBeVisible();

    const controlColors = await monthSelect.evaluate((element) => {
      const style = window.getComputedStyle(element);

      return {
        background: style.backgroundColor,
        color: style.color,
        colorScheme: style.colorScheme,
      };
    });

    expect(controlColors.background).not.toBe('rgb(255, 255, 255)');
    expect(controlColors.background).not.toBe(controlColors.color);
    expect(controlColors.colorScheme).toContain('dark');

    await monthSelect.selectOption('5');
    await yearInput.fill('2027');
    await yearInput.press('Enter');

    await picker.locator('.flatpickr-day:not(.prevMonthDay):not(.nextMonthDay)', { hasText: /^15$/ }).first().click();
    await expect(startDate).toHaveValue('2027-06-15');
  });

  test('date picker stays readable in light mode', async ({ page }) => {
    await openHolidayPicker(page, 'light');
    const colors = await pickerBackground(page);

    expect(colors.background).not.toBe('rgb(0, 0, 0)');
    expect(colors.background).not.toBe(colors.color);
  });

  test('holiday end date cannot stay before the start date', async ({ page }) => {
    await openHolidayPicker(page, 'dark');

    const startDate = page.locator('input[name="holiday_date"]');
    const endDate = page.locator('input[name="holiday_end_date"]');

    await startDate.fill('2027-06-15');
    await startDate.dispatchEvent('change');

    await expect(endDate).toHaveAttribute('min', '2027-06-15');

    await endDate.fill('2027-06-10');
    await endDate.dispatchEvent('change');

    await expect(endDate).not.toHaveValue('2027-06-10');
    await expect(endDate).toHaveValue('2027-06-15');
  });

  test('holiday end date picker can select a valid date after the start date', async ({ page }) => {
    await openHolidayPicker(page, 'dark');

    const startDate = page.locator('input[name="holiday_date"]');
    const endDate = page.locator('input[name="holiday_end_date"]');

    await startDate.fill('2027-06-15');
    await startDate.dispatchEvent('change');
    await startDate.evaluate((input) => input._flatpickr.close());

    await endDate.click();
    const picker = page.locator('.flatpickr-calendar.open').first();
    await expect(picker).toBeVisible();
    const targetDay = picker.locator('.flatpickr-day[aria-label="June 16, 2027"]');
    await expect(targetDay).not.toHaveClass(/flatpickr-disabled/);
    await targetDay.focus();
    await targetDay.press('Enter');

    await expect(endDate).toHaveValue('2027-06-16');
  });
});

test.describe('native mobile date inputs', () => {
  test('mobile start and end dates remain synchronized and enforce the minimum range', async ({ page }) => {
    const { endDate, startDate } = await openHolidayForm(page, 'dark');
    const usesNativeMobileInput = await startDate.evaluate((input) => input._flatpickr.isMobile);
    test.skip(!usesNativeMobileInput, 'This behavior applies only when Flatpickr delegates to native mobile inputs.');

    const mobileInputs = page.locator('input.flatpickr-mobile');
    const mobileStartDate = mobileInputs.nth(0);
    const mobileEndDate = mobileInputs.nth(1);
    await expect(mobileInputs).toHaveCount(2);
    await expect(startDate).toHaveAttribute('type', 'hidden');
    await expect(endDate).toHaveAttribute('type', 'hidden');
    await expect(mobileStartDate).toBeVisible();
    await expect(mobileEndDate).toBeVisible();

    await mobileStartDate.fill('2027-06-15');
    await mobileStartDate.dispatchEvent('change');
    await expect(startDate).toHaveValue('2027-06-15');
    await expect(endDate).toHaveValue('2027-06-15');
    await expect(mobileEndDate).toHaveAttribute('min', '2027-06-15');
    await expect(mobileEndDate).toHaveValue('2027-06-15');

    await mobileEndDate.fill('2027-06-10');
    await mobileEndDate.dispatchEvent('change');
    await expect(endDate).toHaveValue('2027-06-15');
    await expect(mobileEndDate).toHaveValue('2027-06-15');

    await mobileEndDate.fill('2027-06-16');
    await mobileEndDate.dispatchEvent('change');
    await expect(endDate).toHaveValue('2027-06-16');
    await expect(mobileEndDate).toHaveValue('2027-06-16');
  });
});
