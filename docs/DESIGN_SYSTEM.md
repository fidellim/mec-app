# MEC UI design system

Preserve the current professional dashboard identity. This document defines the shared standard for every page, built on Bootstrap 5.3 and `resources/views/layouts/app.blade.php`. Adoption is incremental; documentation does not mean every existing page has been migrated.

## Foundation

Use Bootstrap `--bs-*` tokens for semantic colors and the shared `--app-*` tokens for surfaces. Do not add page-specific palettes or duplicate light/dark values. Theme selection continues through `data-bs-theme`.

| Purpose | Existing source |
| --- | --- |
| Page, card, subdued surfaces | `--app-body-bg`, `--app-card-bg`, `--app-muted-bg` |
| Borders | `--app-border`, `--app-soft-border` |
| Text | `--bs-body-color`, `--bs-secondary-color` |
| Semantic emphasis | Bootstrap primary, success, warning, danger tokens |
| Elevation | `--app-shadow-sm`, `--app-shadow-md` |
| Typography | Existing Bootstrap system font; body `.95rem`, page title `h3 page-heading`, section title `h5` |
| Corners | Cards `.75rem`, buttons `.55rem`, small buttons `.45rem`, pill badges |
| Spacing | Bootstrap spacing scale; `gap-2` for actions, `g-3` for fields, `mb-3` between sections |

Use `color-mix` with existing tokens when a subtle surface is needed. Keep typography, sidebar, and page shell unchanged when adopting these rules.

## Action hierarchy

Choose styles by meaning, not to give every button a different color. Always retain a visible verb or clear destination label. Similar actions should look similar.

| Role | Classes | Examples |
| --- | --- | --- |
| Main action | `btn btn-primary` | New project, Save changes, Submit timesheet |
| Supporting action | `btn btn-outline-secondary` | Edit, Apply filters, Cancel, Export |
| Forward navigation | `btn btn-outline-primary ui-action ui-action-navigation` | Overview, Utilization |
| Back navigation | `btn btn-outline-secondary` | Back to projects |
| Filter reset | `btn btn-link` | Clear filters |
| Reversible caution | `btn btn-outline-warning ui-action ui-action-warning` | Deactivate, Recall |
| Positive transition | `btn btn-outline-success ui-action ui-action-success` | Reactivate, Approve |
| Destructive entry point | `btn btn-outline-danger ui-action ui-action-danger` | Delete, Reject |
| Final destructive confirmation | `btn btn-danger` | Delete project in its confirmation dialog |

Prefer one primary action in each page or independent form/dialog. Repeated table rows should not each contain a solid primary button. On a project detail page, Edit project is the primary header action and Back to projects is neutral outlined. Overview and Utilization remain visibly outlined buttons in the projects list; within the detail page they remain section tabs. A caution or destructive action keeps its semantic styling even when it is the main action in a dialog.

The shared `ui-action` modifier uses theme-aware emphasis tokens for outlined actions and navigation, including hover, active, disabled, and keyboard focus states. Pair it only with the matching role class above. Bootstrap supplies sizing and state behavior. Use `btn-sm` in tables and normal buttons in forms and dialogs. Do not reuse the specialized timesheet icon-button styles for ordinary labeled actions.

Use anchors for navigation and buttons for actions; set button types explicitly. Preserve confirmation flows and permission checks. Use sentence case for new or updated action labels. Icons may supplement labels but must not be the only way to distinguish actions.

## Page and component patterns

- Start with `section-header`: one `h1`, short contextual description, and the main page action.
- Use `content-card`, `content-card-header`, and `content-card-body` for related content. Use summary cards only for useful totals.
- Put search and filters in `filter-card`, with visible labels, Apply filters, and a conditional Clear filters action. Preserve query parameters during pagination.
- Group related form fields with Bootstrap grids. Keep visible labels connected through `for`/`id`, explain required fields, and use placeholders only as examples. Preserve `@error`, invalid styling, submitted values, CSRF, and method directives.
- Keep form submission and cancellation together, in a consistent order: primary action then secondary action. Confirmation dialogs retain Cancel then the confirming action.
- Use readable table headings, an explicit Actions heading, `align-middle`, and `action-group`. Keep navigation, editing, state changes, and deletion in that order. Reuse existing responsive table patterns and `data-label` values.
- Use status badges with text: success for active/approved, warning for pending/attention, danger for rejected/failed, secondary for inactive/draft. Color alone never communicates status.
- Reuse `shared.pagination-footer`, existing validation feedback, status partials, and confirmation dialogs. Empty states should explain what is missing and an available next step without promising unavailable actions.

## Accessibility and responsive behavior

- Check normal text at 4.5:1 contrast and large text at 3:1 in both themes; interactive boundaries and focus indicators must remain discernible.
- Preserve visible keyboard focus, logical tab order, native disabled behavior, and accessible labels. Do not introduce hover-only controls.
- Allow action groups and field layouts to wrap. Verify narrow mobile widths, long labels, and long project names; retain the existing mobile table action target height of at least 44px.
- Avoid unnecessary animation; respect reduced motion for any motion introduced later.

## Adoption and verification

Update one page at a time, preserving routes, controller behavior, permissions, input names, and validation. Do not bulk-replace button classes without reviewing each action's meaning.

| Area | Adoption status |
| --- | --- |
| Shared foundation | Existing theme tokens and components retained; semantic action modifiers added |
| Projects list | Button hierarchy reference implementation |
| Project detail | Header hierarchy adopted: neutral Back, primary Edit; remaining controls pending audit |
| Login | Pending page audit |
| Dashboards | Pending page audit |
| Weekly timesheet form | Pending page audit |
| Timesheet approvals | Pending page audit |
| User management | Pending page audit |
| Remaining forms, leave, reports, and administration | Pending page audits |

For each adopted page, review hierarchy, labels, spacing, forms, tables, badges, empty/error/disabled states, light/dark themes, keyboard navigation, and mobile wrapping. Run the relevant existing workflow tests when markup affects an interactive flow. Record actual verification; do not mark visual checks complete based on compilation alone.
