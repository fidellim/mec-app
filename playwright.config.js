import { defineConfig, devices } from '@playwright/test';

const baseURL = process.env.E2E_BASE_URL || 'http://127.0.0.1:8000';
const slowMo = Number(process.env.E2E_SLOW_MO || 0);

export default defineConfig({
  testDir: './tests/E2E',
  timeout: 30_000,
  expect: {
    timeout: 5_000,
  },
  use: {
    baseURL,
    launchOptions: {
      slowMo,
    },
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
    {
      name: 'tablet',
      use: { ...devices['iPad Pro 11 landscape'] },
    },
  ],
  webServer: process.env.E2E_SKIP_WEBSERVER
    ? undefined
    : {
        command: 'php artisan serve --host=127.0.0.1 --port=8000',
        url: baseURL,
        reuseExistingServer: true,
        timeout: 120_000,
      },
});
