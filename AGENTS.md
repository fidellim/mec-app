# Project UI/UX Rules

This is a Laravel-based internal company platform for timesheets, leave forms, approvals, and admin management.

## Design Direction

Use a clean, professional internal dashboard style.

The UI should be:
- simple
- modern
- easy to understand
- responsive
- suitable for company employees and admins

## Frontend Rules

- Do not change existing Laravel routes unless the requested change requires a route update; when updating routes, keep route changes minimal and preserve existing names/URLs where feasible.
- Do not change controller logic unless requested.
- Do not change database migrations unless requested.
- Do not rename form input names.
- Do not remove Blade directives such as @csrf, @method, @foreach, @if, @error, @auth, or @can.
- Preserve validation error messages.
- Preserve existing functionality.
- Always make frontend UI changes compatible with both light and dark themes.
- Prefer existing theme variables, Bootstrap theme tokens, and color-mix patterns over hardcoded colors so contrast remains readable in both themes.
- For frontend UI/UX work, use the local `frontend-design` skill before planning or implementing visual changes, while still following this project's internal dashboard style and Laravel/Bootstrap constraints.

## UI Style

Use:
- consistent spacing
- readable typography
- rounded cards
- soft shadows
- clear buttons
- clean tables
- status badges
- responsive layouts

Avoid:
- overly colorful designs
- unnecessary animations
- complicated layouts
- changing backend logic during UI tasks

## Preferred Components

For dashboard pages:
- summary cards
- clean tables
- filter/search area
- clear action buttons
- status labels

For forms:
- grouped fields
- clear labels
- helpful placeholders
- visible validation errors
- primary and secondary buttons

## Testing Rules

- Use `composer test` for full Laravel regression runs.
- Use `php artisan test --parallel --processes=4 ...` for focused Laravel test runs.
- `php artisan test --parallel --processes=4` accepts only one test path argument in this project; when checking multiple files or directories, run separate parallel commands for each path.
- Avoid plain `php artisan test` for broad runs because it is serial and can exceed command timeouts.
- If a full regression still reaches the tool timeout, split it into parallel batches and report it as a runtime limit unless a test failure is shown.

## Development Priority

Improve one page at a time.
Focus first on:
1. Login page
2. Dashboard
3. Weekly timesheet form
4. Timesheet approval page
5. User management page
