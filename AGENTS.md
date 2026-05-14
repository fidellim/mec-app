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

- Do not change existing Laravel routes.
- Do not change controller logic unless requested.
- Do not change database migrations unless requested.
- Do not rename form input names.
- Do not remove Blade directives such as @csrf, @method, @foreach, @if, @error, @auth, or @can.
- Preserve validation error messages.
- Preserve existing functionality.

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

## Development Priority

Improve one page at a time.
Focus first on:
1. Login page
2. Dashboard
3. Weekly timesheet form
4. Timesheet approval page
5. User management page