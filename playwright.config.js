import { defineConfig, devices } from '@playwright/test';
import { existsSync, readFileSync } from 'node:fs';

const loadEnvFile = (file) => {
  if (!existsSync(file)) {
    return;
  }

  readFileSync(file, 'utf8')
    .split(/\r?\n/)
    .forEach((line) => {
      const trimmed = line.trim();

      if (!trimmed || trimmed.startsWith('#')) {
        return;
      }

      const separator = trimmed.indexOf('=');

      if (separator === -1) {
        return;
      }

      const key = trimmed.slice(0, separator).trim();
      let value = trimmed.slice(separator + 1).trim();

      if (
        (value.startsWith('"') && value.endsWith('"')) ||
        (value.startsWith("'") && value.endsWith("'"))
      ) {
        value = value.slice(1, -1);
      }

      if (key && process.env[key] === undefined) {
        process.env[key] = value;
      }
    });
};

loadEnvFile('.env.e2e');

const baseURL = process.env.E2E_BASE_URL || 'http://127.0.0.1:8765';
const serverURL = new URL(baseURL);
const serverHost = serverURL.hostname;
const serverPort = serverURL.port || (serverURL.protocol === 'https:' ? '443' : '80');
const slowMo = Number(process.env.E2E_SLOW_MO || 0);
const workers = Number(process.env.E2E_WORKERS || 1);
const phpBinary = process.env.PHP_BINARY || 'php';
const quoteCommandPath = (value) => `"${value.replace(/"/g, '\\"')}"`;
const e2eAppEnv = process.env.E2E_APP_ENV || '';
const e2eEnvArg = e2eAppEnv ? ` --env=${e2eAppEnv}` : '';

export default defineConfig({
  testDir: './tests/E2E',
  timeout: 30_000,
  workers,
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
        command: `${quoteCommandPath(phpBinary)} artisan serve${e2eEnvArg} --host=${serverHost} --port=${serverPort}`,
        url: baseURL,
        reuseExistingServer: true,
        timeout: 120_000,
      },
});
