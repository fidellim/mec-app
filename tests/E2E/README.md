# End-to-End Tests

These Playwright tests exercise the main browser workflows against a seeded local application.

## What They Cover

- Employee login and timesheet page access
- Timesheet form behavior, including dynamically added project rows
- Head of Department approval/tracker pages
- Admin timesheet export download
- Super Admin management pages

## Setup

```bash
npm install
php artisan migrate:fresh --seed
npm run test:e2e
```

Run in headed mode to watch the browser:

```bash
npm run test:e2e:headed
```

Run headed mode more slowly so each browser action is easier to follow:

```bash
npm run test:e2e:headed:slow
```

The slow headed script uses `E2E_SLOW_MO=500` by default. To use a different delay, set `E2E_SLOW_MO` before running Playwright.

Open the Playwright UI runner:

```bash
npm run test:e2e:ui
```

Use another environment:

```bash
E2E_BASE_URL=https://your-domain.test npm run test:e2e
```

Run against an already-running Laravel server:

```bash
E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://127.0.0.1:8000 npm run test:e2e
```

Run headed mode against an already-running Laravel server:

```bash
E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://127.0.0.1:8000 npm run test:e2e:headed
```

On Windows PowerShell:

```powershell
$env:E2E_SKIP_WEBSERVER="1"
$env:E2E_BASE_URL="http://127.0.0.1:8000"
npm run test:e2e
```

Windows PowerShell headed mode:

```powershell
$env:E2E_SKIP_WEBSERVER="1"
$env:E2E_BASE_URL="http://127.0.0.1:8000"
npm run test:e2e:headed
```

Windows PowerShell slow headed mode:

```powershell
$env:E2E_SKIP_WEBSERVER="1"
$env:E2E_BASE_URL="http://127.0.0.1:8000"
npm run test:e2e:headed:slow
```

Run one browser project only:

```bash
npx playwright test --project=chromium --headed
```

Run one browser project slowly:

```powershell
$env:E2E_SLOW_MO="500"
npx playwright test --project=chromium --headed
```

## Assumptions

The specs assume the demo accounts from the Laravel seeders are available.

Default password:

```text
password123
```

Useful accounts:

```text
superadmin@example.com
admin@example.com
aisha@example.com
```

## Troubleshooting

- If browsers are missing, run `npx playwright install`.
- If seeded accounts are missing, run `php artisan migrate:fresh --seed`.
- If port 8000 is already in use, start Laravel manually on another port and set `E2E_BASE_URL`.
- If export tests leave temporary files, clear Laravel cache/storage temp folders before rerunning.
