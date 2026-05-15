# End-to-End Tests

These Playwright tests exercise the main browser workflows against a seeded local application.

## Setup

```bash
npm install
php artisan migrate:fresh --seed
npm run test:e2e
```

Use `E2E_BASE_URL=https://your-domain.test npm run test:e2e` to test another environment.
Set `E2E_SKIP_WEBSERVER=1` when the Laravel server is already running.

The specs assume the demo accounts from the Laravel seeders are available.
