# Login UI audit and adoption

Reviewed on 2026-10-28. Target: `resources/views/auth/login.blade.php`, rendered at `/login` using the existing Laravel layout. Scope: login only; routes, controllers, authentication rules, and the shared shell remain unchanged.

## Implementation integrity

Pass after fixes: the existing MEC logo, Bootstrap foundation, shared card, theme selection, CSRF token, input names, and password-reset destination are retained. Page-specific CSS is scoped under `.login-page`. No replacement palette, dependencies, or decorative motion were introduced.

The Impeccable detector returned `[]` before and after the changes. Its clean result did not identify the missing field associations, session feedback, or rendered contrast issues; source and browser checks supplied that evidence.

## Audit health after fixes

Scores summarize the scoped review, not a certification of WCAG conformance or a performance benchmark.

| Dimension | Score | Evidence and limits |
| --- | --- | --- |
| Accessibility | 3/4 | Connected labels, autocomplete, field-associated errors, error-summary focus, visible keyboard focus, measured default text and field-boundary contrast. Manual screen-reader and saved-password-manager checks remain untested. |
| Performance | 3/4 | Small page-local CSS/script; no new dependencies or animations. Shared layout still loads Tom Select and Flatpickr assets on login. No performance profiling was performed. |
| Theming | 4/4 | Both themes visually inspected; scoped styles use existing tokens. Password visibility action uses readable theme-aware text in both themes. |
| Responsive design | 3/4 | Four emulated widths in both themes, wrapping, 44px-high page controls, and a desktop CSS-zoom check. Physical-device keyboard/gesture behavior remains untested; the shared theme switch retains its existing smaller height. |
| Implementation integrity | 4/4 | Existing identity and request contract retained; detector clean; existing authentication regression tests pass. |
| Total | 17/20 | Good; scoped adoption verified with the limitations above. |

## Findings addressed

| Priority | Finding before adoption | Impact | Resolution |
| --- | --- | --- | --- |
| P1 | Email and password labels had no `for` association; fields lacked autocomplete purposes | Labels did not programmatically name the fields; password-manager support was less explicit (WCAG 1.3.1, 3.3.2, 1.3.5) | Added unique IDs, associated labels, required wording, `username` and `current-password` autocomplete. |
| P1 | Login did not render the backend's session-expiry `status` message | Users returning after an expired session had no explanation | Rendered the original session message inside the card; extended the existing expired-guest-session regression test to check the rendered message. |
| P1 | Password Show/Hide text used Bootstrap's fixed secondary outline color in dark mode | Action text was insufficiently distinct against the dark card (WCAG 1.4.3) | Scoped neutral action colors to existing theme tokens; measured final text contrast at 6.76:1 light and 7.88:1 dark. |
| P2 | Errors appeared only in the layout summary/toast | Users had to connect messages to fields; initial autofocus did not guide them to the failure | Added a focused persistent summary and field-level original messages with `aria-invalid`/`aria-describedby`; used the existing custom-error-section convention to suppress duplicate layout validation feedback. |
| P2 | Standard inputs/buttons were below the project's preferred 44px touch height; options had no wrapping rule | Smaller targets and cramped options made narrow-screen use harder | Added 44px minimum heights, a full-height remember label, wrapping options, and a capped fluid card. |
| P2 | Very subtle field borders and no login-specific focus outline | Field discovery and keyboard location were less clear | Used token-derived boundaries and an explicit token-based focus outline (WCAG 1.4.11, 2.4.7). Final normal field-boundary contrast: 4.16:1 light, 4.95:1 dark. |
| P2 | No pending-submission feedback | Repeated clicks were possible while waiting for authentication | Added visible/polite “Signing in…” feedback, a disabled submit action, and busy state; `pageshow` resets controls on return. Data inputs remain enabled. |

## Verification performed

- Headless Chromium against the local Laravel application at viewport sizes 1440×900, 768×1024, 375×812, and 320×568 in both light and dark themes. No horizontal overflow at any checked size.
- Visually inspected desktop light/dark, mobile light/dark, and mobile server-error captures. The error summary and long messages wrap inside the card.
- Measured computed default text contrast for the heading, email label/input, recovery link, Show/Hide button, and primary button. Minimum sampled text contrast was approximately 4.50:1 on the inherited primary button. Measurements are samples, not an exhaustive check of every shared hover/disabled state.
- Keyboard sequence from initial email focus through password, Show/Hide, Remember me, recovery link, and Login. Explicit focus outlines were present. Server-error rendering moved focus to the summary.
- Password visibility toggled in both directions without changing the input value. Recovery navigation reached the existing password-reset page.
- Native required-field validation left Login enabled. A direct invalid-field submission exercised real Laravel validation without authenticating an account: original messages rendered, email was preserved, password remained blank, field descriptions matched their errors, and no duplicate validation toast appeared.
- A held, intercepted POST verified the pending submit label, disabled button, busy state, and polite progress text without sending credentials to the authentication backend. A subsequent page load restored the enabled Login action.
- Desktop layout checked at 200% CSS zoom for horizontal overflow. This is not a physical-device or native browser-zoom test.
- `php artisan test --parallel --processes=4 tests/Feature/AuthAndAccessTest.php`: 18 tests, 114 assertions passed, including the rendered session-expiry check. Existing tests cover active/inactive login, reset flows, throttling, role access, and expired sessions.
- `git diff --check` passed. No full Laravel regression or cross-browser run was needed for this page-scoped markup/script change.

## Remaining shared-shell observations

These are follow-ups for a shared-layout pass, not completed login fixes:

- P2, performance: consider loading Tom Select and Flatpickr only on pages that use them. Measure before changing shared asset loading; no new dependency is needed for login.
- P3, responsive: the shared theme switch is about 38px high, below the preferred 44px touch height. It exceeds WCAG 2.5.8's 24px size minimum; this observation is not an AA failure.

Maintain the logo, simple single-card structure, theme tokens, native form submission, and visible password-recovery link. Continue page adoption with Dashboard; use a scoped audit before polishing it. Manual assistive-technology, physical-device, and saved-password autofill acceptance remain useful follow-ups.
