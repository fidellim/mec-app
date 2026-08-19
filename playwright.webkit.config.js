import { defineConfig, devices } from '@playwright/test';
import baseConfig from './playwright.config.js';

export default defineConfig({
  ...baseConfig,
  projects: [
    {
      name: 'desktop-webkit',
      use: { ...devices['Desktop Safari'] },
    },
  ],
});
