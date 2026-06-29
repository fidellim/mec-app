# End-to-End Tests

These Playwright tests exercise the main browser workflows against a seeded local application.

## What They Cover

- Employee login and timesheet page access
- Timesheet form behavior, including dynamically added project rows, duplicate rows, Copy Day paste/overwrite, and full-plus-ISO date labels
- Head of Department approval/tracker pages
- Admin timesheet export download
- Super Admin management pages
- Flatpickr month/year selection and light/dark theme readability

## Setup

```bash
npm install
npm run test:e2e:prepare
npm run test:e2e
```

For safer local browser runs, use a dedicated E2E database instead of your normal `.env` database:

```bash
cp .env.e2e.example .env.e2e
npm run test:e2e:prepare
npm run test:e2e
```

On Windows PowerShell:

```powershell
Copy-Item .env.e2e.example .env.e2e
npm.cmd run test:e2e:prepare
npm run test:e2e
```

To reset the E2E database and run browser tests in one step:

```bash
npm run test:e2e:fresh
```

Playwright automatically loads `.env.e2e` when it exists. If npm can run but Playwright cannot find PHP, set `PHP_BINARY` in `.env.e2e`:

E2E defaults to one worker because these specs use shared seeded accounts and a shared local database. You can set `E2E_WORKERS` for parallel experiments, but login throttling may apply.

`.env.e2e` sets `E2E_DISABLE_LOGIN_THROTTLE=true` so repeated automated logins do not trip Laravel's login rate limiter. The bypass only applies when the app runs with `--env=e2e`.

```env
PHP_BINARY='C:\xampp\php\php.exe'
```

For a one-off Windows PowerShell override:

```powershell
$env:PHP_BINARY="C:\xampp\php\php.exe"
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
