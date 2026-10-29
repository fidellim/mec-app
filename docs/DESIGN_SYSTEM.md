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
| Typography | Existing Bootstrap system font; body `.95rem`, page title `<h1 class="h3 page-heading">`, section title `<h2 class="h5">` |
| Corners | Cards `.75rem`, buttons `.55rem`, small buttons `.45rem`, pill badges |
| Spacing | Bootstrap spacing scale; `gap-2` for actions, `g-3` for fields, `mb-3` between sections |

Use `color-mix` with existing tokens when a subtle surface is needed. Keep typography, sidebar, and page shell unchanged when adopting these rules. Heading elements express document structure; Bootstrap heading classes express visual size. Use one page `h1`, then `h2` for its sections, without skipping levels just to obtain smaller text.

### Financial chart colors

Shared `--app-chart-*` tokens in the layout provide explicit light/dark variants: `order` (slate), `invoice` (blue), `receipts` (copper), `spent` (muted amber), `labour` (teal), `subcontractor` (violet), and `overhead` (slate gray). Retain the same category color across financial charts and their keys. Remaining subcontractor commitment uses a pale violet mix with the card surface and a violet outline, distinguishing unpaid obligations from actual payments. Stacked segments have surface-colored separators. Unknown comparison amounts use a dashed empty track and an explicit Not provided or Awaiting labour cost label; they must not resemble confirmed zeros. Colors are categorical; copper receipts do not imply profit, amber cost bars do not imply a warning, and positive balances do not imply profit. Exceptions retain semantic warning/danger text with an explanation. A negative invoicing–collection gap may reflect advances and is not automatically an exception. Keep direct labels and amounts so color is never the only way to read a chart.

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
- Use text status badges according to the status vocabulary below. Submitted is primary, not warning; a workflow status and an attention message serve different purposes. Color alone never communicates status.
- Reuse `shared.pagination-footer`, existing validation feedback, status partials, and confirmation dialogs. Empty states should explain what is missing and an available next step without promising unavailable actions.

## Status vocabulary

For workflow badges, reuse `partials.status` rather than defining another map. The table records its current mappings, plus the existing active/inactive project badges. Labels below show wording; the partial currently applies CSS capitalization. Preserve stored status values and existing transition rules.

| Status value | Visible wording | Meaning in its workflow | Bootstrap badge role |
| --- | --- | --- | --- |
| `draft` | Draft | Saved without submission | `secondary` |
| `submitted` | Submitted | Submitted into the approval workflow; not yet approved | `primary` |
| `approved` | Approved | Approved through the applicable workflow | `success` |
| `rejected` | Rejected | Not approved; show the existing rejection reason where available | `danger` |
| `withdrawn` | Withdrawn | Submission or request withdrawn by its requester | `warning` |
| `recalled` | Recalled | Previously approved record returned for correction | `warning` |
| `voided` | Voided | Record invalidated; retained for history | `warning` |
| `cancellation_requested` | Cancellation requested | Cancellation awaiting a decision; not yet cancelled | `warning` |
| `cancelled` | Cancelled | Cancellation completed | `dark` |
| `not_submitted` | Not submitted | Tracker indicates the expected submission is missing | `warning` |
| `open` | Open | Period is open | `success` |
| `closed` | Closed | Period is closed; exceptions remain governed by existing rules | `dark` |
| `active` | Active | Project or account is active | `success` (render locally; not mapped in the partial) |
| `inactive` | Inactive | Project or account is inactive | `secondary` (render locally; not mapped in the partial) |

Use `badge text-bg-{role}` so Bootstrap pairs the foreground and background. Do not replace `dark` with a hardcoded foreground/background pair. The partial falls back to `secondary` for unknown values; this is a fallback, not an approved mapping for a new status. Review a new status's meaning and document it before adoption.

“Pending” may describe an approval queue, but must not replace the `submitted` badge or collapse distinct states. Where the workflow exposes an approval stage, show it as separate context. Status badges describe records; alerts describe what the user needs to know or do. A rejected record can therefore have a danger badge and a warning panel explaining how to correct it.

## Blade reference patterns

These are adaptation fragments, not replacement pages or new Blade components. Keep the destination page's variables, authorization checks, routes, input names, hidden fields, and scripts. Examples use existing project routes and fields; retain `ListContext` parameters and `shared.list-context` where the page already uses them. Use unique IDs when repeating fields or sections.

### Page header and content section

On the projects list, `$listContext` comes from `\App\Support\ListContext::parameters('projects', true)`. Keep the page's existing permission boundary around the create action.

```blade
<div class="section-header">
    <div>
        <h1 class="h3 page-heading mb-1">Projects / Job Numbers</h1>
        <p class="text-body-secondary mb-0">Manage project details and availability.</p>
    </div>
    <a class="btn btn-primary" href="{{ route('manage.projects.create', $listContext) }}">New project</a>
</div>

<section class="content-card mb-3" aria-labelledby="project-details-heading">
    <div class="content-card-header">
        <h2 class="h5 mb-0" id="project-details-heading">Project details</h2>
    </div>
    <div class="content-card-body">
        {{-- Place the section's existing content here. --}}
    </div>
</section>
```

### Search and filter actions

This search-only fragment expects `$search` from the existing projects list. When adapting the full filter form, retain its status selector and compute the clear-filter condition from every active filter. Reuse `shared.pagination-footer` after the results and preserve the page's existing query-context handling.

```blade
<form class="filter-card mb-3" method="get" action="{{ route('manage.projects.index') }}">
    <div class="row g-3 align-items-end">
        <div class="col-12 col-md-8">
            <label class="form-label" for="project-search">Search projects</label>
            <input class="form-control @error('search') is-invalid @enderror"
                   id="project-search" name="search" type="search"
                   value="{{ $search }}" maxlength="100"
                   placeholder="Project code, name, or client"
                   @error('search') aria-invalid="true" aria-describedby="project-search-error" @enderror>
            @error('search')
                <div class="invalid-feedback" id="project-search-error">{{ $message }}</div>
            @enderror
        </div>
        <div class="col-12 col-md-4 d-flex flex-wrap gap-2">
            <button class="btn btn-outline-secondary" type="submit">Apply filters</button>
            @if(filled($search))
                <a class="btn btn-link" href="{{ route('manage.projects.index') }}">Clear filters</a>
            @endif
        </div>
    </div>
</form>
```

### Validated form field

Place this fragment inside the existing project form, retaining its action, `@csrf`, conditional `@method`, and other inputs. The example expects the form's `$project` model and preserves the existing `project_code` input name and required constraint. It demonstrates the adoption standard for label/error associations; it is not a claim that the current form already implements those associations.

```blade
<div class="col-12 col-md-4">
    <label class="form-label" for="project-code">Project code (required)</label>
    <input class="form-control @error('project_code') is-invalid @enderror"
           id="project-code" name="project_code" type="text" required
           value="{{ old('project_code', $project->project_code) }}"
           @error('project_code') aria-invalid="true" aria-describedby="project-code-error" @enderror>
    @error('project_code')
        <div class="invalid-feedback" id="project-code-error">{{ $message }}</div>
    @enderror
</div>
```

If a field also has help text, give it an ID and retain that ID in `aria-describedby` alongside the error ID. Do not replace server validation messages with generic text.

### Table actions and workflow badge

Inside a project row, use its existing `$project` and `$listContext`. Keep these within the page's existing authorization boundary. These are navigation fragments; state changes and deletion retain their own forms and confirmation flows after them. Never nest forms.

```blade
<td data-label="Actions" class="text-end">
    <div class="action-group">
        <a class="btn btn-sm btn-outline-primary ui-action ui-action-navigation"
           href="{{ route('projects.utilization', ['project' => $project, 'tab' => 'overview'] + $listContext) }}">Overview</a>
        <a class="btn btn-sm btn-outline-secondary"
           href="{{ route('manage.projects.edit', ['project' => $project] + $listContext) }}">Edit</a>
    </div>
</td>
```

For a timesheet workflow badge, pass that page's existing timesheet model:

```blade
@include('partials.status', ['status' => $timesheet->status])
```

### Empty results

Inside the projects table's existing `@forelse` / `@empty`, match `colspan` to its six columns. `$hasProjectFilters` must reflect all active filters. Offer creation only through the existing authorized action; do not add an unavailable action to the empty state.

```blade
<tr>
    <td colspan="6" class="empty-state text-center">
        @if($hasProjectFilters)
            <p class="mb-2">No projects match the selected filters.</p>
            <a class="btn btn-link" href="{{ route('manage.projects.index') }}">Clear filters</a>
        @else
            <p class="mb-0">No projects yet. New projects will appear here.</p>
        @endif
    </td>
</tr>
```

## Interaction states and feedback

The following rules are adoption requirements. Existing pages may only implement some of them; verify behavior when adopting a page rather than assuming shared CSS supplies it.

| Situation | Placement and behavior |
| --- | --- |
| No records yet | Explain what will appear here. Offer an existing, permitted next step where available. |
| No filter matches | State that the current filters returned no matches and offer Clear filters. Keep filter values visible. |
| Field validation failure | Preserve input with the existing `old()` handling, show the original message beside the field, apply `is-invalid`, and associate the message using `aria-describedby` and `aria-invalid`. |
| Multiple validation failures | Keep a persistent error summary above the form or affected section. For complex/repeated fields, identify affected entries and provide links where practical. |
| Submission or async work in progress | Keep a visible label such as “Saving…” or “Submitting…”; a spinner supplements it. Announce async progress with a polite status region and mark the affected region busy where appropriate. Prevent duplicate submissions while the request is pending. |
| Successful action | Confirm the actual result, using the same action vocabulary. Reuse the existing session feedback or `window.showAppToast` for transient async feedback. |
| Request failure | Keep an actionable message near the affected task and preserve unsaved values. A toast may supplement it; it must not be the only place containing recovery instructions. Restore retry controls after a recoverable failure. |
| Unavailable action | Preserve permission-based visibility. For a visible action blocked by record state, use native `disabled` on buttons and give a nearby visible reason. Do not depend on a disabled control's tooltip. |
| Destructive confirmation | Identify the record and consequence. Retain the existing confirmation flow, Cancel then confirm order, and any eligibility explanation. |

### Feedback placement and focus

- Inline feedback belongs near the field or task it concerns. Persistent warnings, rejection reasons, and recovery instructions remain readable after a toast disappears. Do not announce static empty states as urgent alerts.
- The shared layout already renders session success/warning messages inline and as toasts, plus a generic validation summary/toast. Do not add another copy on an adopting page. Pages with a custom validation summary may use the existing `@section('handles-validation-errors', '1')` convention, but must then render all relevant errors themselves; this flag suppresses the layout's validation summary and error toast.
- `window.showAppToast(message, type)` supports `success`, `warning`, and `error`. The shared implementation uses polite status announcements for success and assertive alerts for warning/error. Reuse it for matching outcomes; routine progress should use a separate polite status region. Avoid repeated announcements for every keystroke or changing total.
- For a failed submission, move focus to the error summary (`tabindex="-1"`) or first invalid field once the errors are rendered. Preserve normal focus during typing; do not move focus for background status updates. The weekly timesheet's `timesheet-error-summary` is an existing complex-form reference, not a universal shared component.
- Retain Bootstrap modal focus management. Verify initial focus, keyboard dismissal where allowed, and focus returning to the invoking control. A blocked deletion must explain the restriction in the dialog body, as the projects list does for projects with timesheet entries.
- Preserve the clicked submit button's name/value when preventing duplicate submissions; disabling it must not change “save draft” into “submit,” or lose another action selector. Do not disable data fields just to indicate loading because disabled fields are excluded from submission. Use the existing confirmation handler for `data-confirm` forms; generic loading/retry behavior is not supplied to every form by that handler.

### Shared versus specialized patterns

The page shell, `section-header`, `content-card*`, `filter-card`, `action-group`, semantic `ui-action` modifiers, status partial, pagination footer, and feedback/confirmation helpers are shared foundations. The timesheet's day-copy controls, row icon buttons, repeated-entry error navigation, and dashboard-specific metric layouts are specialized patterns. Reuse specialized patterns only when their interaction and data structure also fit; do not copy their visual styling alone into unrelated forms.

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
| Login | Scoped adoption verified 2026-10-28: labels, validation/focus, session feedback, pending state, light/dark and responsive checks; see [login audit](audits/login-ui.md) for evidence and limits |
| Dashboards | Pending page audit |
| Weekly timesheet form | Pending page audit |
| Timesheet approvals | Pending page audit |
| User management | Pending page audit |
| Remaining forms, leave, reports, and administration | Pending page audits |

For each adopted page, review hierarchy, labels, spacing, forms, tables, badges, empty/error/disabled states, light/dark themes, keyboard navigation, and mobile wrapping. Run the relevant existing workflow tests when markup affects an interactive flow. Record actual verification; do not mark visual checks complete based on compilation alone.
