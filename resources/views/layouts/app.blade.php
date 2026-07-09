<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'MEC Group Portal') }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <script>
        (() => {
            const savedTheme = localStorage.getItem('theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.setAttribute('data-bs-theme', savedTheme || (prefersDark ? 'dark' : 'light'));
            document.documentElement.setAttribute('data-sidebar', localStorage.getItem('sidebar') === 'collapsed' ? 'collapsed' : 'expanded');
        })();
    </script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css" rel="stylesheet">
    <style>
        :root {
            --app-body-bg: #f4f6f9;
            --app-card-bg: #ffffff;
            --app-border: #e5e7eb;
            --app-soft-border: #eef2f7;
            --app-sidebar-bg: #182230;
            --app-sidebar-link: #d6dde8;
            --app-sidebar-hover: #253449;
            --app-sidebar-active: #31425a;
            --app-topbar-bg: #ffffff;
            --app-logo-plate-bg: #ffffff;
            --app-logo-plate-border: rgba(255, 255, 255, .2);
            --app-muted-bg: #f8fafc;
            --app-focus-ring: rgba(13, 110, 253, .18);
            --app-shadow-sm: 0 .25rem .75rem rgba(15, 23, 42, .05);
            --app-shadow-md: 0 .75rem 1.75rem rgba(15, 23, 42, .08);
        }
        [data-bs-theme="dark"] {
            --app-body-bg: #101418;
            --app-card-bg: #171c22;
            --app-border: #2b3440;
            --app-soft-border: #202a35;
            --app-sidebar-bg: #0c1117;
            --app-sidebar-link: #c7d0dc;
            --app-sidebar-hover: #1b2633;
            --app-sidebar-active: #243447;
            --app-topbar-bg: #171c22;
            --app-logo-plate-bg: transparent;
            --app-logo-plate-border: transparent;
            --app-muted-bg: #121820;
            --app-focus-ring: rgba(108, 161, 255, .22);
            --app-shadow-sm: 0 .25rem .75rem rgba(0, 0, 0, .2);
            --app-shadow-md: 0 .75rem 1.75rem rgba(0, 0, 0, .28);
        }
        body {
            background: var(--app-body-bg);
            color: var(--bs-body-color);
            font-size: .95rem;
            -webkit-font-smoothing: antialiased;
        }
        .d-none { display: none !important; }
        .visually-hidden {
            position: absolute !important;
            width: 1px !important;
            height: 1px !important;
            padding: 0 !important;
            margin: -1px !important;
            overflow: hidden !important;
            clip: rect(0, 0, 0, 0) !important;
            white-space: nowrap !important;
            border: 0 !important;
        }
        .app-shell {
            min-height: 100vh;
            padding: 0;
        }
        .app-layout {
            --sidebar-width: 17rem;
            --sidebar-collapsed-width: 5.5rem;
            min-height: 100vh;
            display: grid;
            grid-template-columns: var(--sidebar-width) minmax(0, 1fr);
            transition: grid-template-columns .26s ease;
        }
        [data-sidebar="collapsed"] .app-layout {
            grid-template-columns: var(--sidebar-collapsed-width) minmax(0, 1fr);
        }
        .app-layout.app-layout-no-sidebar,
        [data-sidebar="collapsed"] .app-layout.app-layout-no-sidebar {
            grid-template-columns: minmax(0, 1fr);
        }
        .sidebar {
            display: flex;
            flex-direction: column;
            height: 100vh;
            height: 100dvh;
            min-height: 100vh;
            background: var(--app-sidebar-bg);
            position: sticky;
            top: 0;
            align-self: flex-start;
            overflow: hidden;
            transition: padding .26s ease;
        }
        .sidebar-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
            flex: 0 0 auto;
            min-width: 0;
            position: relative;
            z-index: 1;
        }
        .sidebar-collapse-toggle {
            width: 2.35rem;
            height: 2.35rem;
            flex: 0 0 2.35rem;
            border: 1px solid rgba(255, 255, 255, .08);
            border-radius: .55rem;
            background: rgba(255, 255, 255, .04);
            color: var(--app-sidebar-link);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: background-color .15s ease, color .15s ease, transform .26s ease;
        }
        .sidebar-collapse-toggle:hover {
            background: var(--app-sidebar-hover);
            color: #fff;
        }
        .sidebar-collapse-toggle svg {
            width: 1.05rem;
            height: 1.05rem;
        }
        .mobile-sidebar-toggle {
            display: none;
            width: 2.5rem;
            height: 2.5rem;
            flex: 0 0 2.5rem;
            border: 1px solid rgba(255, 255, 255, .08);
            border-radius: .55rem;
            background: rgba(255, 255, 255, .04);
            color: var(--app-sidebar-link);
            align-items: center;
            justify-content: center;
        }
        .mobile-sidebar-toggle:hover,
        .mobile-sidebar-toggle:focus-visible {
            background: var(--app-sidebar-hover);
            color: #fff;
        }
        .mobile-sidebar-toggle svg {
            width: 1.15rem;
            height: 1.15rem;
        }
        [data-sidebar="collapsed"] .sidebar-collapse-toggle {
            transform: rotate(180deg);
        }
        .sidebar nav {
            align-content: start;
            flex: 1 1 auto;
            min-height: 0;
            overflow-x: hidden;
            overflow-y: auto;
            padding-right: .15rem;
            padding-bottom: .65rem;
            scrollbar-color: color-mix(in srgb, var(--app-sidebar-link) 36%, transparent) transparent;
            scrollbar-width: thin;
            box-shadow:
                inset 0 .75rem .75rem -.95rem rgba(0, 0, 0, .65),
                inset 0 -.75rem .75rem -.95rem rgba(0, 0, 0, .65);
            overscroll-behavior: contain;
        }
        .sidebar nav::-webkit-scrollbar {
            width: .45rem;
        }
        .sidebar nav::-webkit-scrollbar-track {
            background: transparent;
        }
        .sidebar nav::-webkit-scrollbar-thumb {
            background: color-mix(in srgb, var(--app-sidebar-link) 28%, transparent);
            border-radius: 999px;
        }
        .sidebar nav::-webkit-scrollbar-thumb:hover {
            background: color-mix(in srgb, var(--app-sidebar-link) 44%, transparent);
        }
        .sidebar-nav-group {
            display: grid;
            gap: .3rem;
        }
        .sidebar-nav-group + .sidebar-nav-group {
            margin-top: .35rem;
        }
        .sidebar-nav-toggle {
            width: 100%;
            border: 0;
            border-radius: .5rem;
            background: transparent;
            color: color-mix(in srgb, var(--app-sidebar-link) 72%, transparent);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .5rem;
            padding: .42rem .62rem;
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: .08em;
            line-height: 1.2;
            text-transform: uppercase;
            transition: background-color .15s ease, color .15s ease;
        }
        .sidebar-nav-toggle:hover,
        .sidebar-nav-toggle:focus-visible {
            background: rgba(255, 255, 255, .04);
            color: #fff;
        }
        .sidebar-nav-toggle::after {
            content: "";
            width: .45rem;
            height: .45rem;
            border-right: 1.5px solid currentColor;
            border-bottom: 1.5px solid currentColor;
            transform: rotate(45deg) translateY(-1px);
            transition: transform .18s ease;
        }
        .sidebar-nav-toggle[aria-expanded="false"]::after {
            transform: rotate(-45deg);
        }
        .sidebar-nav-items {
            display: grid;
            gap: .18rem;
        }
        .sidebar a {
            color: var(--app-sidebar-link);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: .55rem;
            padding: .62rem .78rem;
            border-radius: .55rem;
            font-weight: 500;
            font-size: .94rem;
            transition: background-color .15s ease, color .15s ease, transform .15s ease;
            white-space: nowrap;
        }
        .sidebar a:hover, .sidebar a.active {
            background: var(--app-sidebar-hover);
            color: #fff;
            transform: translateX(2px);
        }
        .sidebar a.active { background: var(--app-sidebar-active); }
        .sidebar-icon {
            width: 1.1rem;
            height: 1.1rem;
            flex: 0 0 1.1rem;
            opacity: .9;
        }
        .sidebar-label {
            overflow: hidden;
            text-overflow: ellipsis;
            transition: opacity .18s ease, max-width .26s ease;
            max-width: 13rem;
        }
        [data-sidebar="collapsed"] .sidebar {
            padding-left: .85rem !important;
            padding-right: .85rem !important;
        }
        [data-sidebar="collapsed"] .sidebar-header {
            justify-content: center;
        }
        [data-sidebar="collapsed"] .brand-logo-wrap {
            padding: .38rem;
        }
        [data-sidebar="collapsed"] .sidebar .brand-logo {
            width: 2.6rem;
            max-height: 2.6rem;
        }
        [data-sidebar="collapsed"] .sidebar-collapse-toggle {
            position: absolute;
            left: 3.8rem;
            top: 1.15rem;
            opacity: 0;
        }
        [data-sidebar="collapsed"] .sidebar:hover .sidebar-collapse-toggle,
        [data-sidebar="collapsed"] .sidebar-collapse-toggle:focus-visible {
            opacity: 1;
        }
        [data-sidebar="collapsed"] .sidebar a {
            justify-content: center;
            gap: 0;
            padding-left: .68rem;
            padding-right: .68rem;
        }
        [data-sidebar="collapsed"] .sidebar-nav-group {
            gap: .18rem;
            margin-top: 0;
        }
        [data-sidebar="collapsed"] .sidebar-nav-toggle {
            display: none;
        }
        [data-sidebar="collapsed"] .sidebar-nav-items.collapse:not(.show),
        [data-sidebar="collapsed"] .sidebar-nav-items.collapsing {
            display: grid;
            height: auto !important;
            visibility: visible;
        }
        [data-sidebar="collapsed"] .sidebar a:hover {
            transform: none;
        }
        [data-sidebar="collapsed"] .sidebar-label {
            max-width: 0;
            opacity: 0;
        }
        .topbar {
            background: color-mix(in srgb, var(--app-topbar-bg) 92%, transparent);
            backdrop-filter: blur(10px);
            position: sticky;
            top: 0;
            z-index: 20;
        }
        .app-main { min-width: 0; }
        .page-content { padding: 1.5rem; }
        .page-heading {
            letter-spacing: 0;
            font-weight: 700;
        }
        .content-card {
            background: var(--app-card-bg);
            border: 1px solid var(--app-border);
            border-radius: .75rem;
            box-shadow: var(--app-shadow-sm);
        }
        .content-card-header {
            border-bottom: 1px solid var(--app-soft-border);
            padding: 1rem 1.15rem;
        }
        .content-card-body { padding: 1.15rem; }
        .stat-card {
            min-height: 8.25rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .stat-label {
            color: var(--bs-secondary-color);
            font-size: .82rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .02em;
        }
        .stat-value {
            font-size: clamp(1.65rem, 3vw, 2.25rem);
            font-weight: 800;
            line-height: 1;
        }
        .leave-balance-card {
            display: flex;
            flex-direction: column;
            gap: .72rem;
            position: relative;
            overflow: hidden;
            min-height: 100%;
            padding: .9rem;
            border: 1px solid var(--app-soft-border);
            border-radius: .75rem;
            background:
                linear-gradient(135deg, color-mix(in srgb, var(--bs-primary-bg-subtle) 18%, transparent), transparent 40%),
                linear-gradient(180deg, color-mix(in srgb, var(--app-muted-bg) 48%, transparent), transparent 62%),
                var(--app-card-bg);
            box-shadow: var(--app-shadow-sm);
        }
        .leave-balance-card::before {
            content: "";
            position: absolute;
            inset: 0 auto 0 0;
            width: .28rem;
            background: linear-gradient(180deg, var(--bs-primary), color-mix(in srgb, var(--bs-primary) 42%, var(--bs-info)));
        }
        .leave-balance-card-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: .65rem;
            min-width: 0;
        }
        .leave-balance-heading {
            min-width: 0;
        }
        .leave-balance-title {
            margin: 0 0 .18rem;
            font-size: .95rem;
            font-weight: 800;
            line-height: 1.25;
            letter-spacing: 0;
        }
        .leave-balance-meta,
        .leave-balance-note {
            color: var(--bs-secondary-color);
            font-size: .78rem;
            line-height: 1.45;
        }
        .leave-balance-source {
            flex: 0 0 auto;
            max-width: 9.5rem;
            padding: .32rem .45rem;
            border-radius: .55rem;
            font-size: .68rem;
            line-height: 1.15;
            white-space: normal;
            text-align: center;
        }
        .leave-balance-summary {
            display: grid;
            grid-template-columns: minmax(7rem, .86fr) minmax(0, 1.14fr);
            gap: .62rem;
            align-items: stretch;
        }
        .leave-balance-remaining {
            min-width: 0;
            padding: .68rem .72rem;
            border: 1px solid color-mix(in srgb, var(--bs-success) 34%, var(--app-soft-border));
            border-radius: .7rem;
            background:
                linear-gradient(180deg, color-mix(in srgb, var(--bs-success-bg-subtle) 50%, transparent), transparent),
                color-mix(in srgb, var(--app-card-bg) 80%, var(--app-muted-bg));
        }
        .leave-balance-remaining.is-depleted {
            border-color: color-mix(in srgb, var(--bs-warning) 42%, var(--app-soft-border));
            background:
                linear-gradient(180deg, color-mix(in srgb, var(--bs-warning-bg-subtle) 44%, transparent), transparent),
                color-mix(in srgb, var(--app-card-bg) 80%, var(--app-muted-bg));
        }
        .leave-balance-remaining-value {
            margin-top: .1rem;
            color: color-mix(in srgb, var(--bs-success) 76%, var(--bs-body-color));
            font-size: clamp(1.65rem, 3vw, 2.12rem);
            font-weight: 800;
            letter-spacing: 0;
            line-height: .95;
        }
        .leave-balance-remaining.is-depleted .leave-balance-remaining-value {
            color: color-mix(in srgb, var(--bs-warning) 76%, var(--bs-body-color));
        }
        .leave-balance-remaining-unit {
            margin-top: .18rem;
            color: var(--bs-secondary-color);
            font-size: .72rem;
            font-weight: 700;
        }
        .leave-balance-metrics {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: .48rem;
        }
        .leave-balance-metric {
            min-width: 0;
            padding: .58rem .62rem;
            border: 1px solid var(--app-soft-border);
            border-radius: .7rem;
            background: color-mix(in srgb, var(--app-card-bg) 78%, var(--app-muted-bg));
        }
        .leave-balance-metric-label {
            color: var(--bs-secondary-color);
            font-size: .66rem;
            font-weight: 800;
            letter-spacing: .02em;
            line-height: 1.25;
            text-transform: uppercase;
        }
        .leave-balance-metric-value {
            margin-top: .2rem;
            color: var(--bs-body-color);
            font-size: .9rem;
            font-weight: 800;
            line-height: 1.15;
        }
        .leave-balance-track-group {
            display: grid;
            gap: .34rem;
        }
        .leave-balance-track-labels {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .5rem;
            color: var(--bs-secondary-color);
            font-size: .7rem;
            font-weight: 700;
        }
        .leave-balance-progress {
            height: .42rem;
            overflow: hidden;
            border-radius: 999px;
            background: color-mix(in srgb, var(--bs-secondary-bg) 82%, var(--app-card-bg));
            box-shadow: inset 0 0 0 1px var(--app-soft-border);
        }
        .leave-balance-progress span {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, var(--bs-primary), color-mix(in srgb, var(--bs-primary) 68%, var(--bs-info)));
        }
        .leave-balance-note {
            padding: .58rem .65rem;
            border: 1px solid var(--app-soft-border);
            border-radius: .65rem;
            background: color-mix(in srgb, var(--app-muted-bg) 54%, transparent);
        }
        .leave-balance-pay-bands {
            border-top: 1px solid var(--app-soft-border);
            padding-top: .65rem;
        }
        .section-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .empty-state {
            text-align: center;
            color: var(--bs-secondary-color);
            padding: 2.5rem 1rem !important;
        }
        .action-group {
            display: inline-flex;
            align-items: center;
            justify-content: flex-end;
            gap: .5rem;
            flex-wrap: wrap;
        }
        .filter-card {
            background: var(--app-card-bg);
            border: 1px solid var(--app-border);
            border-radius: .75rem;
            box-shadow: var(--app-shadow-sm);
            padding: 1rem;
        }
        .meta-label {
            color: var(--bs-secondary-color);
            font-size: .78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .02em;
        }
        .meta-value {
            font-weight: 700;
            font-size: 1rem;
        }
        .submission-chart-card {
            --chart-uae-submitted: #0f766e;
            --chart-uae-missing: #f59e0b;
            --chart-ph-submitted: #2563eb;
            --chart-ph-missing: #dc2626;
            --chart-unknown-submitted: #64748b;
            --chart-unknown-missing: #7c3aed;
        }
        .regional-chart-layout {
            display: grid;
            grid-template-columns: minmax(11rem, 15rem) minmax(0, 1fr);
            gap: 1.25rem;
            align-items: center;
        }
        .submission-donut-wrap {
            display: flex;
            justify-content: center;
        }
        .submission-donut {
            width: 13rem;
            aspect-ratio: 1;
            border-radius: 50%;
            display: grid;
            place-items: center;
            box-shadow: inset 0 0 0 1px var(--app-soft-border);
        }
        .submission-donut-center {
            width: 7.25rem;
            aspect-ratio: 1;
            border-radius: 50%;
            background: var(--app-card-bg);
            border: 1px solid var(--app-soft-border);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            box-shadow: var(--app-shadow-sm);
        }
        .regional-stat {
            border: 1px solid var(--app-soft-border);
            border-radius: .75rem;
            padding: .85rem;
            min-height: 7.25rem;
            background: color-mix(in srgb, var(--app-muted-bg) 62%, transparent);
        }
        .regional-stat-row {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr) auto;
            align-items: center;
            gap: .45rem;
            margin-top: .55rem;
            color: var(--bs-secondary-color);
            font-size: .9rem;
        }
        .regional-stat-row strong {
            color: var(--bs-body-color);
        }
        .regional-label {
            display: flex;
            align-items: center;
            gap: .45rem;
        }
        .country-flag {
            width: 1.45rem;
            height: .95rem;
            border-radius: .12rem;
            border: 1px solid color-mix(in srgb, var(--bs-body-color) 18%, transparent);
            box-shadow: 0 .08rem .18rem rgba(15, 23, 42, .12);
            flex: 0 0 auto;
            object-fit: cover;
        }
        .chart-key {
            width: .7rem;
            height: .7rem;
            border-radius: 50%;
            display: inline-block;
        }
        .chart-key-uae-submitted { background: var(--chart-uae-submitted); }
        .chart-key-uae-missing { background: var(--chart-uae-missing); }
        .chart-key-ph-submitted { background: var(--chart-ph-submitted); }
        .chart-key-ph-missing { background: var(--chart-ph-missing); }
        .chart-key-unknown-submitted { background: var(--chart-unknown-submitted); }
        .chart-key-unknown-missing { background: var(--chart-unknown-missing); }
        .brand-logo { display: block; height: 2.75rem; width: auto; object-fit: contain; }
        .brand-logo-wrap {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: .45rem;
            background: var(--app-logo-plate-bg);
            border: 1px solid var(--app-logo-plate-border);
            padding: .45rem .6rem;
        }
        .sidebar .brand-logo {
            width: 9.5rem;
            height: auto;
            max-height: 3.25rem;
            transition: width .26s ease, max-height .26s ease;
        }
        .login-logo { height: 4.5rem; max-width: 14rem; object-fit: contain; }
        .table {
            --bs-table-bg: transparent;
            --bs-table-border-color: var(--app-soft-border);
            margin-bottom: 0;
        }
        .table thead th {
            color: var(--bs-secondary-color);
            font-size: .75rem;
            letter-spacing: .02em;
            text-transform: uppercase;
            font-weight: 700;
            white-space: nowrap;
            border-bottom-width: 1px;
            background: var(--app-muted-bg);
        }
        .table tbody td { border-color: var(--app-soft-border); }
        .table > :not(caption) > * > * { vertical-align: middle; }
        .table-fixed { table-layout: fixed; }
        .text-truncate-cell { max-width: 1px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .project-name-cell { white-space: normal; overflow-wrap: anywhere; word-break: normal; line-height: 1.45; }
        .project-select { width: 18rem; max-width: 100%; }
        .attendance-select { width: 13rem; max-width: 100%; }
        .timesheet-entry-table th, .timesheet-entry-table td { white-space: nowrap; }
        .timesheet-entry-table .remarks-cell { min-width: 22rem; }
        .timesheet-entry-table input,
        .timesheet-entry-table select { min-height: 2.45rem; }
        .timesheet-entry-table [data-entry-row] { border-left: 3px solid transparent; }
        .timesheet-entry-table [data-entry-row]:hover { border-left-color: var(--bs-primary); }
        .timesheet-entry-table .timesheet-entry-row-invalid,
        .timesheet-entry-table .timesheet-entry-row-client-invalid {
            border-left-color: var(--bs-warning);
            background: color-mix(in srgb, var(--bs-warning-bg-subtle) 34%, transparent);
        }
        .timesheet-day-summary-row > td {
            background: color-mix(in srgb, var(--app-muted-bg) 72%, var(--app-card-bg));
            border-top: 1px solid var(--app-border);
            border-bottom: 1px solid var(--app-soft-border);
            padding: .9rem 1rem .75rem;
        }
        .timesheet-entry-table tbody tr + .timesheet-day-summary-row > td {
            border-top: .85rem solid var(--app-card-bg);
            box-shadow: inset 0 1px 0 var(--app-border);
        }
        .timesheet-day-summary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }
        .day-copy-button {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            min-height: 2rem;
            padding-inline: .75rem;
            color: var(--bs-btn-hover-color);
            background-color: var(--bs-btn-hover-bg);
            border-color: var(--bs-btn-hover-border-color);
            white-space: nowrap;
        }
        .day-copy-button:hover,
        .day-copy-button:focus-visible {
            color: var(--bs-btn-hover-color);
            background-color: var(--bs-btn-hover-bg);
            border-color: color-mix(in srgb, var(--bs-btn-hover-border-color) 72%, #000);
            box-shadow: 0 0 0 .18rem var(--app-focus-ring);
        }
        .timesheet-day-column-row > th {
            color: var(--bs-secondary-color);
            font-size: .72rem;
            letter-spacing: .02em;
            text-transform: uppercase;
            font-weight: 700;
            background: color-mix(in srgb, var(--app-muted-bg) 45%, transparent);
            border-bottom: 1px solid var(--app-soft-border);
            padding: .65rem 1rem;
        }
        .timesheet-entry-table [data-entry-row] > td {
            padding: .8rem 1rem;
        }
        .timesheet-row-actions {
            display: flex;
            justify-content: flex-end;
            gap: .35rem;
        }
        .action-icon-button {
            width: 2.25rem;
            height: 2.25rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            border-width: 1px;
            color: var(--bs-btn-hover-color);
            background-color: var(--bs-btn-hover-bg);
            border-color: var(--bs-btn-hover-border-color);
        }
        .action-icon-button:hover,
        .action-icon-button:focus-visible {
            color: var(--bs-btn-hover-color);
            background-color: var(--bs-btn-hover-bg);
            border-color: color-mix(in srgb, var(--bs-btn-hover-border-color) 72%, #000);
            box-shadow: 0 0 0 .18rem var(--app-focus-ring);
        }
        .action-icon {
            width: 1rem;
            height: 1rem;
            display: inline-block;
            background-color: currentColor;
            mask-position: center;
            mask-repeat: no-repeat;
            mask-size: contain;
            -webkit-mask-position: center;
            -webkit-mask-repeat: no-repeat;
            -webkit-mask-size: contain;
        }
        .action-icon-add {
            mask-image: url("{{ asset('images/actions/add-icon.svg') }}");
            -webkit-mask-image: url("{{ asset('images/actions/add-icon.svg') }}");
        }
        .action-icon-duplicate {
            mask-image: url("{{ asset('images/actions/duplicate-icon.svg') }}");
            -webkit-mask-image: url("{{ asset('images/actions/duplicate-icon.svg') }}");
        }
        .action-icon-trash {
            mask-image: url("{{ asset('images/actions/trash-icon.svg') }}");
            -webkit-mask-image: url("{{ asset('images/actions/trash-icon.svg') }}");
        }
        .copy-day-target-list {
            display: grid;
            gap: .5rem;
        }
        .copy-day-target {
            display: flex;
            align-items: center;
            gap: .7rem;
            padding: .75rem .85rem;
            border: 1px solid var(--app-border);
            border-radius: .65rem;
            background: color-mix(in srgb, var(--app-muted-bg) 50%, var(--app-card-bg));
            cursor: pointer;
        }
        .copy-day-target:hover {
            border-color: var(--bs-primary);
        }
        .timesheet-day-row > td {
            background: color-mix(in srgb, var(--app-muted-bg) 78%, var(--app-card-bg));
            border-top: 1px solid var(--app-border);
            border-bottom: 1px solid var(--app-border);
        }
        .timeline-list {
            list-style: none;
            margin: 0;
            padding: 0;
        }
        .timeline-item {
            position: relative;
            display: grid;
            grid-template-columns: 2.5rem minmax(0, 1fr);
            gap: .55rem;
            min-height: 5rem;
        }
        .timeline-item:not(:last-child)::before {
            content: "";
            position: absolute;
            left: 1.25rem;
            top: 2.3rem;
            bottom: .15rem;
            width: 1px;
            background: var(--app-border);
            transform: translateX(-50%);
        }
        .timeline-marker {
            width: 1.45rem;
            height: 1.45rem;
            margin-top: .1rem;
            border-radius: 50%;
            background: var(--timeline-marker-bg, #4f5df5);
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 0 .2rem color-mix(in srgb, var(--timeline-marker-bg, #4f5df5) 14%, transparent);
            z-index: 1;
            justify-self: center;
        }
        .timeline-marker svg {
            width: .78rem;
            height: .78rem;
            display: block;
            flex: 0 0 auto;
            overflow: visible;
            transform-box: fill-box;
            transform-origin: center;
        }
        .timeline-marker-icon-send { transform: translate(-.02rem, .02rem) scale(.9); }
        .timeline-marker-icon-check-circle { transform: scale(1.04); }
        .timeline-marker-icon-x-circle { transform: scale(1.04); }
        .timeline-marker-icon-undo { transform: translate(.01rem, 0) scale(.96); }
        .timeline-marker-icon-rotate-left { transform: translate(.01rem, .01rem) scale(.94); }
        .timeline-marker-icon-ban { transform: scale(1.02); }
        .timeline-marker-neutral { --timeline-marker-bg: #64748b; }
        .timeline-marker-info { --timeline-marker-bg: #2563eb; }
        .timeline-marker-success { --timeline-marker-bg: #15803d; }
        .timeline-marker-danger { --timeline-marker-bg: #dc2626; }
        .timeline-marker-warning { --timeline-marker-bg: #b45309; }
        .timeline-marker-recall { --timeline-marker-bg: #7c3aed; }
        .timeline-marker-void { --timeline-marker-bg: #475569; }
        .timeline-marker-muted { --timeline-marker-bg: #0f766e; }
        .timeline-content {
            padding-bottom: 1.1rem;
        }
        .timeline-date {
            color: var(--bs-secondary-color);
            font-size: .9rem;
            margin-bottom: .18rem;
        }
        .timeline-title {
            font-weight: 700;
            color: var(--bs-body-color);
        }
        .timeline-copy,
        .timeline-meta {
            color: var(--bs-secondary-color);
            font-size: .92rem;
        }
        .timeline-comment {
            margin-top: .5rem;
            padding: .65rem .75rem;
            border: 1px solid var(--app-border);
            border-radius: .6rem;
            background: color-mix(in srgb, var(--app-muted-bg) 70%, var(--app-card-bg));
            color: var(--bs-body-color);
            overflow-wrap: anywhere;
        }
        .timeline-meta {
            margin-top: .35rem;
        }
        .form-control,
        .form-select,
        .ts-control {
            background-color: var(--bs-body-bg);
            border-color: var(--app-border);
            border-radius: .55rem;
            color: var(--bs-body-color);
        }
        .form-control::placeholder {
            color: var(--bs-secondary-color);
            opacity: 1;
        }
        .form-control:focus,
        .form-select:focus,
        .ts-wrapper.focus .ts-control {
            border-color: var(--bs-primary);
            box-shadow: 0 0 0 .22rem var(--app-focus-ring);
        }
        .flatpickr-calendar {
            z-index: 1060;
            background: var(--app-card-bg);
            border: 1px solid var(--app-border);
            border-radius: .75rem;
            box-shadow: var(--app-shadow-md);
            color: var(--bs-body-color);
            overflow: hidden;
        }
        .flatpickr-calendar::before,
        .flatpickr-calendar::after {
            display: none;
        }
        .flatpickr-months {
            background: color-mix(in srgb, var(--app-muted-bg) 72%, var(--app-card-bg));
            border-bottom: 1px solid var(--app-soft-border);
        }
        .flatpickr-months .flatpickr-month,
        .flatpickr-current-month .flatpickr-monthDropdown-months,
        .flatpickr-current-month input.cur-year {
            background: transparent;
            color: var(--bs-body-color);
            fill: var(--bs-body-color);
            font-weight: 700;
        }
        .flatpickr-current-month .flatpickr-monthDropdown-months {
            border-radius: .4rem;
            color-scheme: light;
        }
        [data-bs-theme="dark"] .flatpickr-current-month .flatpickr-monthDropdown-months {
            color-scheme: dark;
        }
        .flatpickr-current-month .flatpickr-monthDropdown-months:hover,
        .flatpickr-current-month .flatpickr-monthDropdown-months:focus,
        .flatpickr-current-month input.cur-year:hover,
        .flatpickr-current-month input.cur-year:focus {
            background: color-mix(in srgb, var(--bs-primary-bg-subtle) 52%, var(--app-card-bg));
        }
        .flatpickr-current-month .flatpickr-monthDropdown-months option {
            background: var(--app-card-bg);
            color: var(--bs-body-color);
        }
        .flatpickr-current-month .numInputWrapper span.arrowUp::after {
            border-bottom-color: var(--bs-body-color);
        }
        .flatpickr-current-month .numInputWrapper span.arrowDown::after {
            border-top-color: var(--bs-body-color);
        }
        .flatpickr-months .flatpickr-prev-month,
        .flatpickr-months .flatpickr-next-month {
            color: var(--bs-body-color);
            fill: var(--bs-body-color);
        }
        .flatpickr-months .flatpickr-prev-month:hover,
        .flatpickr-months .flatpickr-next-month:hover {
            color: var(--bs-primary);
            fill: var(--bs-primary);
        }
        .flatpickr-weekday {
            color: var(--bs-secondary-color);
            font-size: .72rem;
            font-weight: 800;
            text-transform: uppercase;
        }
        .flatpickr-day {
            border-radius: .45rem;
            color: var(--bs-body-color);
            margin: .04rem;
        }
        .flatpickr-day:hover,
        .flatpickr-day:focus {
            background: color-mix(in srgb, var(--bs-primary-bg-subtle) 58%, var(--app-card-bg));
            border-color: transparent;
            color: var(--bs-body-color);
        }
        .flatpickr-day.today {
            border-color: var(--bs-primary);
            color: var(--bs-body-color);
        }
        .flatpickr-day.selected,
        .flatpickr-day.selected:hover,
        .flatpickr-day.startRange,
        .flatpickr-day.endRange {
            background: var(--bs-primary);
            border-color: var(--bs-primary);
            color: #fff;
        }
        .flatpickr-day.inRange {
            background: color-mix(in srgb, var(--bs-primary-bg-subtle) 66%, var(--app-card-bg));
            border-color: color-mix(in srgb, var(--bs-primary-bg-subtle) 66%, var(--app-card-bg));
            box-shadow: -5px 0 0 color-mix(in srgb, var(--bs-primary-bg-subtle) 66%, var(--app-card-bg)), 5px 0 0 color-mix(in srgb, var(--bs-primary-bg-subtle) 66%, var(--app-card-bg));
        }
        .flatpickr-day.flatpickr-disabled,
        .flatpickr-day.flatpickr-disabled:hover,
        .flatpickr-day.prevMonthDay,
        .flatpickr-day.nextMonthDay,
        .flatpickr-day.notAllowed {
            color: color-mix(in srgb, var(--bs-secondary-color) 58%, transparent);
        }
        .flatpickr-time {
            border-top-color: var(--app-soft-border);
        }
        .flatpickr-time input,
        .flatpickr-time .flatpickr-am-pm {
            color: var(--bs-body-color);
        }
        .ts-wrapper.single .ts-control,
        .ts-wrapper.single .ts-control input {
            cursor: text;
        }
        .ts-wrapper.form-select {
            padding: 0;
            background-image: none;
        }
        .ts-control {
            min-height: calc(2.25rem + 2px);
            background: var(--bs-body-bg);
            color: var(--bs-body-color);
        }
        .ts-control input {
            color: var(--bs-body-color) !important;
            -webkit-text-fill-color: var(--bs-body-color);
        }
        .ts-control input::placeholder {
            color: var(--bs-secondary-color);
            opacity: 1;
            -webkit-text-fill-color: var(--bs-secondary-color);
        }
        .ts-wrapper.multi .ts-control > div {
            align-items: center;
            background: color-mix(in srgb, var(--bs-primary-bg-subtle) 58%, var(--app-card-bg));
            border: 1px solid color-mix(in srgb, var(--bs-primary-border-subtle) 70%, var(--app-border));
            border-radius: .45rem;
            color: var(--bs-body-color);
            display: inline-flex;
            gap: .35rem;
            line-height: 1.35;
            padding: .15rem .35rem;
        }
        .ts-wrapper.multi .ts-control .remove {
            border-left-color: color-mix(in srgb, var(--bs-body-color) 20%, transparent);
            color: var(--bs-secondary-color);
            margin-left: .15rem;
            padding-left: .45rem;
            text-decoration: none;
        }
        .ts-wrapper.multi .ts-control .remove:hover,
        .ts-wrapper.multi .ts-control .remove:focus {
            background: transparent;
            color: var(--bs-danger);
        }
        .ts-dropdown {
            background: var(--app-card-bg);
            border-color: var(--app-border);
            border-radius: .65rem;
            box-shadow: var(--app-shadow-md);
            color: var(--bs-body-color);
            overflow: hidden;
        }
        .ts-dropdown .option,
        .ts-dropdown .no-results {
            padding: .55rem .75rem;
        }
        .ts-dropdown .active {
            background: var(--app-muted-bg);
            color: var(--bs-body-color);
        }
        .ts-wrapper.required .ts-control,
        .ts-wrapper.is-required .ts-control {
            border-color: var(--app-border);
        }
        .ts-wrapper.timesheet-client-invalid .ts-control,
        .ts-wrapper.is-invalid .ts-control {
            border-color: var(--bs-form-invalid-border-color);
        }
        .btn {
            border-radius: .55rem;
            font-weight: 600;
        }
        .btn-sm { border-radius: .45rem; }
        .badge {
            border-radius: 999px;
            font-weight: 700;
            letter-spacing: 0;
        }
        .filter-summary-badge {
            background: color-mix(in srgb, var(--app-muted-bg) 82%, var(--app-card-bg));
            border: 1px solid var(--app-border);
            color: var(--bs-body-color);
        }
        .app-toast {
            background: color-mix(in srgb, var(--app-card-bg) 96%, transparent);
            border: 1px solid var(--app-border);
            box-shadow: var(--app-shadow-md);
            color: var(--bs-body-color);
        }
        .app-toast-success {
            border-left: .35rem solid var(--bs-success);
        }
        .app-toast-warning {
            border-left: .35rem solid var(--bs-warning);
        }
        .app-toast-error {
            border-left: .35rem solid var(--bs-danger);
        }
        .app-toast-success .toast-header {
            color: var(--bs-success-text-emphasis);
            background: color-mix(in srgb, var(--bs-success-bg-subtle) 78%, var(--app-card-bg));
        }
        .app-toast-warning .toast-header {
            color: var(--bs-warning-text-emphasis);
            background: color-mix(in srgb, var(--bs-warning-bg-subtle) 78%, var(--app-card-bg));
        }
        .app-toast-error .toast-header {
            color: var(--bs-danger-text-emphasis);
            background: color-mix(in srgb, var(--bs-danger-bg-subtle) 78%, var(--app-card-bg));
        }
        .alert {
            border: 1px solid var(--app-border);
            border-radius: .75rem;
            box-shadow: var(--app-shadow-sm);
        }
        .toolbar-card {
            background: var(--app-card-bg);
            border: 1px solid var(--app-border);
            border-radius: .75rem;
            box-shadow: var(--app-shadow-sm);
        }
        .sticky-actions {
            position: sticky;
            bottom: 0;
            z-index: 10;
            background: color-mix(in srgb, var(--app-card-bg) 94%, transparent);
            backdrop-filter: blur(10px);
            border-top: 1px solid var(--app-soft-border);
            border-radius: 0 0 .75rem .75rem;
        }
        .pagination svg { width: 1rem; height: 1rem; }
        .pagination-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: .9rem 1rem;
            border: 1px solid var(--app-soft-border);
            border-radius: .75rem;
            background:
                linear-gradient(180deg, color-mix(in srgb, var(--app-card-bg) 94%, var(--app-muted-bg)), var(--app-card-bg)),
                var(--app-card-bg);
            box-shadow: var(--app-shadow-sm);
        }
        .pagination-footer-summary {
            color: var(--bs-secondary-color);
            font-size: .875rem;
        }
        .pagination-footer-controls {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: .5rem;
            justify-content: flex-end;
            min-width: 0;
        }
        .pagination-footer-actions,
        .pagination-footer-pages {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
        }
        .pagination-footer-button,
        .pagination-footer-page,
        .pagination-footer-current {
            min-height: 2.1rem;
            border: 1px solid var(--app-soft-border);
            border-radius: .55rem;
            background: color-mix(in srgb, var(--app-card-bg) 90%, var(--app-muted-bg));
            color: var(--bs-body-color);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: .82rem;
            font-weight: 700;
            line-height: 1;
            text-decoration: none;
            white-space: nowrap;
        }
        .pagination-footer-button {
            min-width: 5.25rem;
            padding: .55rem .75rem;
        }
        .pagination-footer-page {
            min-width: 2.1rem;
            padding: .55rem .65rem;
        }
        .pagination-footer-current {
            padding: .55rem .75rem;
            color: var(--bs-secondary-color);
            font-weight: 700;
        }
        .pagination-footer-button:hover,
        .pagination-footer-page:hover,
        .pagination-footer-button:focus-visible,
        .pagination-footer-page:focus-visible {
            border-color: color-mix(in srgb, var(--bs-primary) 42%, var(--app-soft-border));
            background: color-mix(in srgb, var(--bs-primary-bg-subtle) 42%, var(--app-card-bg));
            color: var(--bs-primary-text-emphasis);
        }
        .pagination-footer-button.is-primary,
        .pagination-footer-page.is-active {
            border-color: var(--bs-primary);
            background: var(--bs-primary);
            color: var(--bs-white);
        }
        .pagination-footer-button.is-disabled {
            background: color-mix(in srgb, var(--app-muted-bg) 72%, var(--app-card-bg));
            color: var(--bs-secondary-color);
            cursor: not-allowed;
            opacity: .68;
        }
        .theme-switch {
            --switch-width: 4.75rem;
            --switch-height: 2.35rem;
            --switch-padding: .22rem;
            width: var(--switch-width);
            height: var(--switch-height);
            border: 1px solid var(--app-border);
            border-radius: 999px;
            background: #ffffff;
            box-shadow: inset 0 0 0 1px rgba(15, 23, 42, .04), 0 .2rem .7rem rgba(15, 23, 42, .16);
            padding: var(--switch-padding);
            position: relative;
            display: inline-flex;
            align-items: center;
            cursor: pointer;
            transition: background-color .2s ease, border-color .2s ease;
        }
        .theme-switch::before {
            content: "";
            position: absolute;
            inset: var(--switch-padding);
            width: calc(var(--switch-height) - (var(--switch-padding) * 2));
            height: calc(var(--switch-height) - (var(--switch-padding) * 2));
            border-radius: 50%;
            background: #0f1114;
            transform: translateX(calc(var(--switch-width) - var(--switch-height)));
            transition: transform .2s ease, background-color .2s ease;
        }
        .theme-switch-icon {
            width: calc(var(--switch-height) - (var(--switch-padding) * 2));
            height: calc(var(--switch-height) - (var(--switch-padding) * 2));
            border-radius: 50%;
            padding: .42rem;
            position: relative;
            z-index: 1;
            transition: transform .2s ease, filter .2s ease;
        }
        [data-bs-theme="dark"] .theme-switch {
            background: #2d3033;
            border-color: #3a3f45;
        }
        [data-bs-theme="dark"] .theme-switch::before {
            background: #ffffff;
            transform: translateX(0);
        }
        [data-bs-theme="dark"] .theme-switch-icon {
            transform: translateX(0);
            filter: none;
        }
        [data-bs-theme="light"] .theme-switch-icon {
            transform: translateX(calc(var(--switch-width) - var(--switch-height)));
            filter: invert(1);
        }
        @media (min-width: 992px) {
            .timesheet-entry-table { min-width: 980px; }
        }
        @media (max-width: 575.98px) {
            .leave-balance-card-header {
                flex-direction: column;
            }
            .leave-balance-source {
                align-self: flex-start;
                max-width: 100%;
            }
            .leave-balance-summary {
                grid-template-columns: 1fr;
            }
            .leave-balance-metrics {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
            .leave-balance-metric-value {
                font-size: .9rem;
            }
            .leave-balance-track-labels {
                flex-wrap: wrap;
            }
        }
        @media (max-width: 767.98px) {
            .pagination-footer {
                align-items: stretch;
                flex-direction: column;
            }
            .pagination-footer-controls {
                justify-content: flex-start;
                overflow-x: auto;
            }
            .pagination-footer-actions,
            .pagination-footer-pages {
                flex-wrap: nowrap;
            }
            .app-layout {
                display: block;
                min-height: 0;
            }
            [data-sidebar="collapsed"] .app-layout {
                grid-template-columns: none;
            }
            .sidebar {
                display: block;
                height: auto;
                min-height: auto;
                position: static;
                border-bottom: 1px solid rgba(255, 255, 255, .08);
            }
            .sidebar-header {
                align-items: center;
                margin-bottom: 0 !important;
            }
            .sidebar-collapse-toggle {
                display: none;
            }
            .mobile-sidebar-toggle {
                display: inline-flex;
            }
            [data-sidebar="collapsed"] .sidebar {
                padding-left: 1rem !important;
                padding-right: 1rem !important;
            }
            [data-sidebar="collapsed"] .sidebar-header {
                justify-content: flex-start;
            }
            [data-sidebar="collapsed"] .brand-logo-wrap {
                padding: .45rem .6rem;
            }
            [data-sidebar="collapsed"] .sidebar .brand-logo {
                width: 9.5rem;
                max-height: 3.25rem;
            }
            [data-sidebar="collapsed"] .sidebar a {
                justify-content: flex-start;
                gap: .55rem;
                padding: .72rem .85rem;
            }
            [data-sidebar="collapsed"] .sidebar-label {
                max-width: 13rem;
                opacity: 1;
            }
            .sidebar nav {
                display: grid !important;
                grid-template-columns: 1fr;
                max-height: 0;
                opacity: 0;
                overflow: hidden;
                padding-bottom: 0;
                padding-top: 0;
                pointer-events: none;
                transition: max-height .24s ease, opacity .18s ease, padding-top .24s ease, padding-bottom .24s ease;
            }
            [data-mobile-sidebar="open"] .sidebar nav {
                max-height: calc(100vh - 6rem);
                max-height: calc(100dvh - 6rem);
                opacity: 1;
                overflow-y: auto;
                padding-bottom: .65rem;
                padding-top: 1rem;
                pointer-events: auto;
            }
            .sidebar-nav-toggle {
                padding-inline: .75rem;
            }
            [data-sidebar="collapsed"] .sidebar-nav-toggle {
                display: flex;
            }
            [data-sidebar="collapsed"] .sidebar-nav-items.collapse:not(.show) {
                display: none;
            }
            .sidebar a {
                min-width: 0;
                white-space: normal;
            }
            .sidebar-label {
                min-width: 0;
                overflow-wrap: anywhere;
            }
            .topbar {
                position: static;
                padding: 1rem !important;
            }
            .page-content { padding: 1rem; }
            .content-card-body { padding: 1rem; }
            .timesheet-day-summary {
                align-items: flex-start;
                flex-direction: column;
                gap: .75rem;
            }
            .timesheet-entry-table,
            .timesheet-entry-table tbody,
            .timesheet-entry-table tr,
            .timesheet-entry-table td {
                display: block;
                width: 100%;
            }
            .timesheet-entry-table {
                border-collapse: separate;
                border-spacing: 0;
            }
            .timesheet-day-column-row {
                display: none !important;
            }
            .timesheet-entry-table [data-entry-row] {
                border-left: 0;
                border-top: 1px solid var(--app-soft-border);
                padding: .85rem 1rem;
                background: var(--app-card-bg);
            }
            .timesheet-entry-table [data-entry-row]:hover {
                border-left-color: transparent;
            }
            .timesheet-entry-table [data-entry-row] > td {
                border: 0;
                padding: .45rem 0;
                white-space: normal;
            }
            .timesheet-entry-table [data-entry-row] > td::before {
                display: block;
                margin-bottom: .3rem;
                color: var(--bs-secondary-color);
                font-size: .72rem;
                font-weight: 700;
                letter-spacing: .02em;
                text-transform: uppercase;
            }
            .timesheet-entry-table [data-entry-row] > td:nth-child(1)::before { content: "Attendance Code"; }
            .timesheet-entry-table [data-entry-row] > td:nth-child(2)::before { content: "Project/Job"; }
            .timesheet-entry-table [data-entry-row] > td:nth-child(3)::before { content: "Regular"; }
            .timesheet-entry-table [data-entry-row] > td:nth-child(4)::before { content: "Overtime"; }
            .timesheet-entry-table [data-entry-row] > td:nth-child(5)::before { content: "Remarks"; }
            .timesheet-entry-table [data-entry-row] > td:nth-child(6)::before { content: "Actions"; }
            .timesheet-entry-table .remarks-cell {
                min-width: 0;
            }
            .attendance-select,
            .project-select {
                width: 100%;
            }
            .timesheet-row-actions {
                justify-content: flex-start;
            }
            .timesheet-day-summary-row > td {
                padding: .9rem 1rem;
            }
            .timesheet-entry-table tbody tr + .timesheet-day-summary-row > td {
                border-top-width: .85rem;
            }
            .brand-logo-wrap { margin-bottom: 1rem !important; }
            .sidebar-header .brand-logo-wrap { margin-bottom: 0 !important; }
            .section-header {
                flex-direction: column;
                align-items: stretch;
            }
            .section-header .btn,
            .section-header form,
            .action-group,
            .action-group .btn,
            .action-group form { width: 100%; }
            .action-group { display: flex; }
            .regional-chart-layout {
                grid-template-columns: 1fr;
            }
            .submission-donut {
                width: min(13rem, 72vw);
            }
        }
    </style>
</head>
<body>
@php
    $hideAuthenticatedNavigation = auth()->check()
        && \App\Models\SystemSetting::setupModeEnabled()
        && in_array(auth()->user()->role, ['employee', 'hod'], true);
@endphp
<div class="container-fluid app-shell">
    <div @class([
        'app-layout' => auth()->check(),
        'app-layout-no-sidebar' => $hideAuthenticatedNavigation,
        'guest-layout' => auth()->guest(),
    ])>
        @auth
            @unless($hideAuthenticatedNavigation)
            <aside class="sidebar p-3">
                <div class="sidebar-header mb-4">
                    <div class="brand-logo-wrap mb-0">
                        <img class="brand-logo" data-theme-logo src="{{ asset('images/mec_logo_light.webp') }}" alt="MEC">
                    </div>
                    <button class="mobile-sidebar-toggle" type="button" data-mobile-sidebar-toggle aria-label="Open navigation menu" aria-expanded="false" title="Open navigation menu">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M4 6h16"/>
                            <path d="M4 12h16"/>
                            <path d="M4 18h16"/>
                        </svg>
                    </button>
                    <button class="sidebar-collapse-toggle" type="button" data-sidebar-toggle aria-label="Collapse sidebar" title="Collapse sidebar">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="m15 18-6-6 6-6"/>
                        </svg>
                    </button>
                </div>
                @php
                    $leavePlanApprovals = app(\App\Services\LeavePlanApprovalService::class);
                    $isLeavePlanStageApprover = collect([$leavePlanApprovals->director()?->id, $leavePlanApprovals->approver(\App\Models\LeavePlanApproverSetting::HR_UAE)?->id, $leavePlanApprovals->approver(\App\Models\LeavePlanApproverSetting::HR_PH)?->id])
                        ->filter()
                        ->contains((int) auth()->id());
                    $hasApprovalNav = $isLeavePlanStageApprover || auth()->user()->role === 'hod';
                    $hasAdminNav = in_array(auth()->user()->role, ['admin', 'super_admin'], true) || auth()->user()->isAdminLike();
                    $workspaceOpen = request()->routeIs('dashboard', 'employee.timesheets.*', 'employee.leave-plans.*');
                    $approvalsOpen = request()->routeIs('assigned.leave-plans.*', 'hod.timesheets.*', 'hod.leave-plans.*', 'hod.tracker');
                    $adminOpen = request()->routeIs('admin.timesheets.*', 'admin.leave-plans.*', 'admin.leave-entitlements.*', 'admin.annual-leave-carry-overs.*', 'admin.hod-timesheets.*', 'admin.hod-tracker', 'manage.*');
                    $supportOpen = request()->routeIs('guide');
                @endphp
                <nav class="d-grid gap-1" aria-label="Primary navigation">
                    <div class="sidebar-nav-group">
                        <button class="sidebar-nav-toggle @unless($workspaceOpen) collapsed @endunless" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarWorkspaceNav" aria-expanded="{{ $workspaceOpen ? 'true' : 'false' }}" aria-controls="sidebarWorkspaceNav">
                            <span>Workspace</span>
                        </button>
                        <div id="sidebarWorkspaceNav" @class(['sidebar-nav-items', 'collapse', 'show' => $workspaceOpen])>
                            <a href="{{ route('dashboard') }}" @class(['active' => request()->routeIs('dashboard')]) title="Dashboard"><img class="sidebar-icon" src="{{ asset('images/sidebar/dashboard.svg') }}" alt=""><span class="sidebar-label">Dashboard</span></a>
                            <a href="{{ route('employee.timesheets.index') }}" @class(['active' => request()->routeIs('employee.timesheets.*')]) title="My Timesheets"><img class="sidebar-icon" src="{{ asset('images/sidebar/my-timesheets.svg') }}" alt=""><span class="sidebar-label">My Timesheets</span></a>
                            <a href="{{ route('employee.leave-plans.index') }}" @class(['active' => request()->routeIs('employee.leave-plans.*')]) title="My Leave Plans"><img class="sidebar-icon" src="{{ asset('images/sidebar/my-timesheets.svg') }}" alt=""><span class="sidebar-label">My Leave Plans</span></a>
                        </div>
                    </div>
                    @if($hasApprovalNav)
                        <div class="sidebar-nav-group">
                            <button class="sidebar-nav-toggle @unless($approvalsOpen) collapsed @endunless" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarApprovalsNav" aria-expanded="{{ $approvalsOpen ? 'true' : 'false' }}" aria-controls="sidebarApprovalsNav">
                                <span>Approvals</span>
                            </button>
                            <div id="sidebarApprovalsNav" @class(['sidebar-nav-items', 'collapse', 'show' => $approvalsOpen])>
                                @if($isLeavePlanStageApprover)
                                    <a href="{{ route('assigned.leave-plans.index') }}" @class(['active' => request()->routeIs('assigned.leave-plans.*')]) title="Assigned Leave Plans"><img class="sidebar-icon" src="{{ asset('images/sidebar/department-timesheets.svg') }}" alt=""><span class="sidebar-label">Assigned Leave Plans</span></a>
                                @endif
                                @if(auth()->user()->role === 'hod')
                                    <a href="{{ route('hod.timesheets.index') }}" @class(['active' => request()->routeIs('hod.timesheets.*')]) title="Department Timesheets"><img class="sidebar-icon" src="{{ asset('images/sidebar/department-timesheets.svg') }}" alt=""><span class="sidebar-label">Department Timesheets</span></a>
                                    <a href="{{ route('hod.leave-plans.index') }}" @class(['active' => request()->routeIs('hod.leave-plans.*')]) title="Department Leave Plans"><img class="sidebar-icon" src="{{ asset('images/sidebar/department-timesheets.svg') }}" alt=""><span class="sidebar-label">Department Leave Plans</span></a>
                                    <a href="{{ route('hod.tracker') }}" @class(['active' => request()->routeIs('hod.tracker')]) title="Submission Tracker"><img class="sidebar-icon" src="{{ asset('images/sidebar/submission-tracker.svg') }}" alt=""><span class="sidebar-label">Submission Tracker</span></a>
                                @endif
                            </div>
                        </div>
                    @endif
                    @if($hasAdminNav)
                        <div class="sidebar-nav-group">
                            <button class="sidebar-nav-toggle @unless($adminOpen) collapsed @endunless" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarAdminNav" aria-expanded="{{ $adminOpen ? 'true' : 'false' }}" aria-controls="sidebarAdminNav">
                                <span>Administration</span>
                            </button>
                            <div id="sidebarAdminNav" @class(['sidebar-nav-items', 'collapse', 'show' => $adminOpen])>
                                @if(in_array(auth()->user()->role, ['admin', 'super_admin'], true))
                                    <a href="{{ route('admin.timesheets.index') }}" @class(['active' => request()->routeIs('admin.timesheets.*')]) title="All Timesheets"><img class="sidebar-icon" src="{{ asset('images/sidebar/all-timesheets.svg') }}" alt=""><span class="sidebar-label">All Timesheets</span></a>
                                    <a href="{{ route('admin.leave-plans.index') }}" @class(['active' => request()->routeIs('admin.leave-plans.*')]) title="All Leave Plans"><img class="sidebar-icon" src="{{ asset('images/sidebar/all-timesheets.svg') }}" alt=""><span class="sidebar-label">All Leave Plans</span></a>
                                    <a href="{{ route('admin.leave-entitlements.index') }}" @class(['active' => request()->routeIs('admin.leave-entitlements.*')]) title="Leave Entitlements"><img class="sidebar-icon" src="{{ asset('images/sidebar/weekly-periods.svg') }}" alt=""><span class="sidebar-label">Leave Entitlements</span></a>
                                    <a href="{{ route('admin.annual-leave-carry-overs.index') }}" @class(['active' => request()->routeIs('admin.annual-leave-carry-overs.*')]) title="Annual Carry-Overs"><img class="sidebar-icon" src="{{ asset('images/sidebar/weekly-periods.svg') }}" alt=""><span class="sidebar-label">Annual Carry-Overs</span></a>
                                    <a href="{{ route('admin.hod-timesheets.index') }}" @class(['active' => request()->routeIs('admin.hod-timesheets.*')]) title="HOD Timesheets"><img class="sidebar-icon" src="{{ asset('images/sidebar/department-timesheets.svg') }}" alt=""><span class="sidebar-label">HOD Timesheets</span></a>
                                    <a href="{{ route('admin.hod-tracker') }}" @class(['active' => request()->routeIs('admin.hod-tracker')]) title="HOD Submission Tracker"><img class="sidebar-icon" src="{{ asset('images/sidebar/submission-tracker.svg') }}" alt=""><span class="sidebar-label">HOD Tracker</span></a>
                                @endif
                                @if(in_array(auth()->user()->role, ['admin', 'super_admin'], true))
                                    <a href="{{ route('manage.users.index') }}" @class(['active' => request()->routeIs('manage.users.*')]) title="Users"><img class="sidebar-icon" src="{{ asset('images/sidebar/users.svg') }}" alt=""><span class="sidebar-label">Users</span></a>
                                    <a href="{{ route('manage.leave-settings.index') }}" @class(['active' => request()->routeIs('manage.leave-settings.*')]) title="Leave Settings"><img class="sidebar-icon" src="{{ asset('images/sidebar/weekly-periods.svg') }}" alt=""><span class="sidebar-label">Leave Settings</span></a>
                                @endif
                                @if(auth()->user()->role === 'super_admin')
                                    <a href="{{ route('manage.departments.index') }}" @class(['active' => request()->routeIs('manage.departments.*')]) title="Departments"><img class="sidebar-icon" src="{{ asset('images/sidebar/departments.svg') }}" alt=""><span class="sidebar-label">Departments</span></a>
                                    <a href="{{ route('manage.projects.index') }}" @class(['active' => request()->routeIs('manage.projects.*')]) title="Projects"><img class="sidebar-icon" src="{{ asset('images/sidebar/projects.svg') }}" alt=""><span class="sidebar-label">Projects</span></a>
                                    <a href="{{ route('manage.periods.index') }}" @class(['active' => request()->routeIs('manage.periods.*')]) title="Weekly Periods"><img class="sidebar-icon" src="{{ asset('images/sidebar/weekly-periods.svg') }}" alt=""><span class="sidebar-label">Weekly Periods</span></a>
                                    <a href="{{ route('manage.leave-plan-approvers.index') }}" @class(['active' => request()->routeIs('manage.leave-plan-approvers.*')]) title="Leave Plan Approvers"><img class="sidebar-icon" src="{{ asset('images/sidebar/users.svg') }}" alt=""><span class="sidebar-label">Leave Approvers</span></a>
                                @endif
                                @if(auth()->user()->isAdminLike())
                                    <a href="{{ route('manage.holidays.index') }}" @class(['active' => request()->routeIs('manage.holidays.*')]) title="Holidays"><img class="sidebar-icon" src="{{ asset('images/sidebar/weekly-periods.svg') }}" alt=""><span class="sidebar-label">Holidays</span></a>
                                @endif
                                @if(auth()->user()->role === 'super_admin')
                                    <a href="{{ route('manage.automations.index') }}" @class(['active' => request()->routeIs('manage.automations.*')]) title="Automations"><img class="sidebar-icon" src="{{ asset('images/sidebar/automations.svg') }}" alt=""><span class="sidebar-label">Automations</span></a>
                                    <a href="{{ route('manage.system-settings.index') }}" @class(['active' => request()->routeIs('manage.system-settings.*')]) title="System Settings"><img class="sidebar-icon" src="{{ asset('images/sidebar/automations.svg') }}" alt=""><span class="sidebar-label">System Settings</span></a>
                                    <a href="{{ route('manage.audit-logs.index') }}" @class(['active' => request()->routeIs('manage.audit-logs.*')]) title="Audit Logs"><img class="sidebar-icon" src="{{ asset('images/sidebar/audit-logs.svg') }}" alt=""><span class="sidebar-label">Audit Logs</span></a>
                                @endif
                            </div>
                        </div>
                    @endif
                    <div class="sidebar-nav-group">
                        <button class="sidebar-nav-toggle @unless($supportOpen) collapsed @endunless" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarSupportNav" aria-expanded="{{ $supportOpen ? 'true' : 'false' }}" aria-controls="sidebarSupportNav">
                            <span>Support</span>
                        </button>
                        <div id="sidebarSupportNav" @class(['sidebar-nav-items', 'collapse', 'show' => $supportOpen])>
                            <a href="{{ route('guide') }}" @class(['active' => request()->routeIs('guide')]) title="Help Guide"><img class="sidebar-icon" src="{{ asset('images/sidebar/guide.svg') }}" alt=""><span class="sidebar-label">Help Guide</span></a>
                        </div>
                    </div>
                </nav>
            </aside>
            @endunless
        @endauth
        <main class="@auth app-main @else col-12 @endauth p-0">
            @auth
                <header class="topbar border-bottom px-4 py-3 d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fw-semibold">{{ auth()->user()->name }}</div>
                        <div class="small text-muted">{{ config('roles.labels.'.auth()->user()->role, auth()->user()->role) }}</div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <button class="theme-switch" type="button" data-theme-toggle aria-label="Toggle color theme" title="Toggle color theme">
                            <img class="theme-switch-icon" data-theme-icon src="{{ asset('images/sun-icon.svg') }}" alt="">
                        </button>
                        <form method="post" action="{{ route('logout') }}">
                            @csrf
                            <button class="btn btn-outline-secondary btn-sm">Logout</button>
                        </form>
                    </div>
                </header>
            @endauth
            <section class="page-content">
                @guest
                    <div class="d-flex justify-content-end mb-3">
                        <button class="theme-switch" type="button" data-theme-toggle aria-label="Toggle color theme" title="Toggle color theme">
                            <img class="theme-switch-icon" data-theme-icon src="{{ asset('images/sun-icon.svg') }}" alt="">
                        </button>
                    </div>
                @endguest
                @if(session('success'))
                    <div class="alert alert-success d-flex align-items-start gap-2"><span class="fw-bold">Success</span><span>{{ session('success') }}</span></div>
                @endif
                @if(session('warning'))
                    <div class="alert alert-warning d-flex align-items-start gap-2"><span class="fw-bold">Notice</span><span>{{ session('warning') }}</span></div>
                @endif
                @if($errors->any())
                    <div class="alert alert-danger">
                        <strong>Please check the form.</strong>
                        <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif
                @yield('content')
            </section>
        </main>
    </div>
</div>
<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1080;" id="appToastContainer">
    @if(session('success'))
        <div class="toast app-toast app-toast-success" role="status" aria-live="polite" aria-atomic="true" data-app-toast>
            <div class="toast-header">
                <strong class="me-auto">Success</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body">{{ session('success') }}</div>
        </div>
    @endif
    @if(session('warning'))
        <div class="toast app-toast app-toast-warning" role="alert" aria-live="assertive" aria-atomic="true" data-app-toast>
            <div class="toast-header">
                <strong class="me-auto">Notice</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body">{{ session('warning') }}</div>
        </div>
    @endif
    @if($errors->any())
        <div class="toast app-toast app-toast-error" role="alert" aria-live="assertive" aria-atomic="true" data-app-toast>
            <div class="toast-header">
                <strong class="me-auto">Something went wrong</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body">Please check the form and try again.</div>
        </div>
    @endif
</div>
<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="confirmModalMessage">Are you sure?</div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" id="confirmModalCancelButton">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmModalButton">Confirm</button>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
<script>
const initializeTooltips = (scope = document) => {
    if (!window.bootstrap?.Tooltip) {
        return;
    }

    scope.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((element) => {
        bootstrap.Tooltip.getOrCreateInstance(element);
    });
};

initializeTooltips();

const appToastContainer = document.getElementById('appToastContainer');

window.showAppToast = (message, type = 'info', title = 'Notice') => {
    if (!appToastContainer) {
        return;
    }

    const toast = document.createElement('div');
    const typeClass = {
        success: 'app-toast-success',
        warning: 'app-toast-warning',
        error: 'app-toast-error',
    }[type] || 'app-toast-warning';
    toast.className = `toast app-toast ${typeClass}`;
    toast.setAttribute('role', type === 'success' ? 'status' : 'alert');
    toast.setAttribute('aria-live', type === 'success' ? 'polite' : 'assertive');
    toast.setAttribute('aria-atomic', 'true');

    const header = document.createElement('div');
    header.className = 'toast-header';

    const titleElement = document.createElement('strong');
    titleElement.className = 'me-auto';
    titleElement.textContent = title;

    const closeButton = document.createElement('button');
    closeButton.type = 'button';
    closeButton.className = 'btn-close';
    closeButton.setAttribute('data-bs-dismiss', 'toast');
    closeButton.setAttribute('aria-label', 'Close');

    const body = document.createElement('div');
    body.className = 'toast-body';
    body.textContent = message;

    header.append(titleElement, closeButton);
    toast.append(header, body);
    appToastContainer.appendChild(toast);

    toast.addEventListener('hidden.bs.toast', () => toast.remove(), { once: true });
    window.bootstrap?.Toast?.getOrCreateInstance(toast, { delay: 6000 }).show();
};

document.querySelectorAll('[data-app-toast]').forEach((toast) => {
    window.bootstrap?.Toast?.getOrCreateInstance(toast, { delay: 7000 }).show();
});

const initializeSearchableSelects = (scope = document) => {
    if (!window.TomSelect) {
        return;
    }

    scope.querySelectorAll('select.form-select').forEach((select) => {
        if (select.tomselect || select.dataset.searchable === 'false') {
            return;
        }

        const options = {
            allowEmptyOption: true,
            create: false,
            dropdownParent: 'body',
            maxOptions: null,
            searchField: ['text'],
            sortField: [{ field: '$order' }],
        };

        if (select.multiple) {
            options.plugins = {
                remove_button: {
                    title: 'Remove selected item',
                },
            };
        }

        new TomSelect(select, options);
    });
};

const destroySearchableSelects = (scope) => {
    scope.querySelectorAll('select.form-select').forEach((select) => {
        if (select.tomselect) {
            select.tomselect.destroy();
        }
    });
};

const setSearchableSelectValue = (select, value) => {
    if (select?.tomselect) {
        select.tomselect.setValue(value, true);
        return;
    }

    if (select) {
        select.value = value;
    }
};

initializeSearchableSelects();

const dispatchDateInputEvents = (input) => {
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
};

const parseDateInputValue = (value) => {
    if (!value) {
        return null;
    }

    const date = new Date(`${value}T00:00:00`);

    return Number.isNaN(date.getTime()) ? null : date;
};

const formatDateInputValue = (date) => {
    if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
        return '';
    }

    const year = date.getFullYear();
    const month = `${date.getMonth() + 1}`.padStart(2, '0');
    const day = `${date.getDate()}`.padStart(2, '0');

    return `${year}-${month}-${day}`;
};

const setDatePickerViewDate = (picker, date) => {
    if (!picker || !date) {
        return;
    }

    picker.jumpToDate(date);
};

const closeOtherDatePickers = (currentInput) => {
    document.querySelectorAll('input').forEach((input) => {
        if (input !== currentInput && input._flatpickr?.isOpen) {
            input._flatpickr.close();
        }
    });
};

const initializeDatePickers = (scope = document) => {
    if (!window.flatpickr) {
        return;
    }

    scope.querySelectorAll('input[type="date"]').forEach((input) => {
        if (input._flatpickr || input.dataset.datepicker === 'false') {
            return;
        }

        input.autocomplete = input.autocomplete || 'off';
        input.inputMode = 'numeric';
        input.placeholder = input.placeholder || 'YYYY-MM-DD';

        input.addEventListener('mousedown', () => closeOtherDatePickers(input), { capture: true });
        input.addEventListener('focus', () => closeOtherDatePickers(input), { capture: true });

        flatpickr(input, {
            allowInput: true,
            dateFormat: 'Y-m-d',
            monthSelectorType: 'dropdown',
            minDate: input.min || null,
            onOpen: (selectedDates, dateStr, picker) => {
                if (input.readOnly) {
                    picker.close();
                    return;
                }

                const selectedDate = parseDateInputValue(input.value);
                const minDate = parseDateInputValue(input.min);

                setDatePickerViewDate(picker, selectedDate || minDate);
            },
            onChange: (selectedDates, dateStr) => {
                input.value = dateStr;
                dispatchDateInputEvents(input);
            },
        });
    });
};

window.syncDatePicker = (input) => {
    if (!input?._flatpickr) {
        return;
    }

    const date = parseDateInputValue(input.value);

    if (date) {
        input._flatpickr.setDate(formatDateInputValue(date), false, 'Y-m-d');
        setDatePickerViewDate(input._flatpickr, date);
        return;
    }

    input._flatpickr.clear(false);
};

window.setDatePickerMin = (input, minDate) => {
    if (input?._flatpickr) {
        const parsedMinDate = minDate ? parseDateInputValue(minDate) : null;
        const currentValue = input.value;
        const currentDate = parseDateInputValue(currentValue);

        input._flatpickr.set('minDate', minDate || null);

        if (currentDate) {
            input._flatpickr.setDate(currentValue, false, 'Y-m-d');
            input.value = currentValue;
        }

        setDatePickerViewDate(input._flatpickr, currentDate || parsedMinDate);
    }
};

window.setDatePickerReadonly = (input, isReadonly) => {
    if (!input?._flatpickr) {
        return;
    }

    if (isReadonly) {
        input._flatpickr.close();
    }
};

initializeDatePickers();

document.querySelectorAll('[data-timesheet-history], [data-leave-plan-history]').forEach((card) => {
    const toggle = card.querySelector('[data-history-toggle]');
    const panel = card.querySelector('[data-history-panel]');
    const content = card.querySelector('[data-history-content]');
    const url = card.dataset.historyUrl;
    const historyLabel = card.dataset.historyLabel || 'Timesheet';

    if (!toggle || !panel || !content || !url) {
        return;
    }

    const setVisible = (visible) => {
        panel.classList.toggle('d-none', !visible);
        toggle.textContent = visible ? 'Hide history' : 'Show history';
        toggle.setAttribute('aria-expanded', visible ? 'true' : 'false');
    };

    toggle.setAttribute('aria-expanded', 'false');

    toggle.addEventListener('click', async () => {
        if (card.dataset.loaded === 'true') {
            setVisible(panel.classList.contains('d-none'));
            return;
        }

        panel.classList.remove('d-none');
        toggle.disabled = true;
        toggle.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Loading...';

        try {
            const response = await fetch(url, {
                headers: {
                    'Accept': 'text/html',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error(`Unable to load ${historyLabel.toLowerCase()} history.`);
            }

            content.innerHTML = await response.text();
            card.dataset.loaded = 'true';
            initializeTooltips(content);
            setVisible(true);
        } catch (error) {
            content.innerHTML = `<div class="alert alert-warning mb-0">${historyLabel} history could not be loaded. Please try again.</div>`;
            toggle.textContent = 'Retry';
        } finally {
            toggle.disabled = false;
        }
    });
});

(() => {
    const buttons = document.querySelectorAll('[data-theme-toggle]');
    const icons = document.querySelectorAll('[data-theme-icon]');
    const media = window.matchMedia('(prefers-color-scheme: dark)');
    const desktopSidebar = window.matchMedia('(min-width: 768px)');
    const iconPaths = {
        light: "{{ asset('images/moon-icon.svg') }}",
        dark: "{{ asset('images/sun-icon.svg') }}",
    };
    const logoPaths = {
        expanded: {
            light: "{{ asset('images/mec_logo_light.webp') }}",
            dark: "{{ asset('images/mec_logo_dark.webp') }}",
        },
        collapsed: {
            light: "{{ asset('images/mec_icon_light.webp') }}",
            dark: "{{ asset('images/mec_icon_dark.webp') }}",
        },
    };

    const effectiveTheme = () => localStorage.getItem('theme') || (media.matches ? 'dark' : 'light');
    const sidebarState = () => document.documentElement.getAttribute('data-sidebar') === 'collapsed' ? 'collapsed' : 'expanded';
    const applyMobileSidebarState = (open) => {
        document.documentElement.setAttribute('data-mobile-sidebar', open ? 'open' : 'closed');
        document.querySelectorAll('[data-mobile-sidebar-toggle]').forEach((button) => {
            button.setAttribute('aria-expanded', open ? 'true' : 'false');
            button.setAttribute('aria-label', open ? 'Close navigation menu' : 'Open navigation menu');
            button.setAttribute('title', open ? 'Close navigation menu' : 'Open navigation menu');
        });
    };
    const applySidebarState = (state) => {
        document.documentElement.setAttribute('data-sidebar', state);
        document.querySelectorAll('[data-sidebar-toggle]').forEach((button) => {
            const isCollapsed = state === 'collapsed';
            button.setAttribute('aria-label', isCollapsed ? 'Expand sidebar' : 'Collapse sidebar');
            button.setAttribute('title', isCollapsed ? 'Expand sidebar' : 'Collapse sidebar');
        });
    };
    const applyLogos = (theme) => {
        const state = desktopSidebar.matches ? sidebarState() : 'expanded';
        document.querySelectorAll('[data-theme-logo]').forEach((logo) => {
            logo.src = logoPaths[state][theme];
        });
    };
    const applyTheme = (theme) => {
        document.documentElement.setAttribute('data-bs-theme', theme);
        icons.forEach((icon) => {
            icon.src = iconPaths[theme];
        });
        applyLogos(theme);
        buttons.forEach((button) => {
            button.setAttribute('aria-label', theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
            button.setAttribute('title', theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
        });
    };

    applySidebarState(sidebarState());
    applyMobileSidebarState(false);
    applyTheme(effectiveTheme());

    buttons.forEach((button) => {
        button.addEventListener('click', () => {
            const nextTheme = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
            localStorage.setItem('theme', nextTheme);
            applyTheme(nextTheme);
        });
    });

    media.addEventListener('change', () => {
        if (!localStorage.getItem('theme')) {
            applyTheme(effectiveTheme());
        }
    });
    desktopSidebar.addEventListener('change', () => {
        applyLogos(effectiveTheme());
        applyMobileSidebarState(false);
    });

    document.querySelectorAll('[data-sidebar-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const nextState = sidebarState() === 'collapsed' ? 'expanded' : 'collapsed';
            localStorage.setItem('sidebar', nextState);
            applySidebarState(nextState);
            applyLogos(effectiveTheme());
        });
    });

    document.querySelectorAll('[data-mobile-sidebar-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            applyMobileSidebarState(document.documentElement.getAttribute('data-mobile-sidebar') !== 'open');
        });
    });
})();

document.querySelectorAll('form').forEach((form) => {
    form.addEventListener('submit', (event) => {
        const submitter = event.submitter;
        const message = submitter?.dataset.confirm || form.dataset.confirm;
        if (!message) {
            return;
        }
        if (form.dataset.confirmed === 'true') {
            return;
        }
        event.preventDefault();
        const modalElement = document.getElementById('confirmModal');
        const cancelButton = document.getElementById('confirmModalCancelButton');
        const closeButton = modalElement.querySelector('.btn-close');
        document.getElementById('confirmModalMessage').textContent = message;
        const button = document.getElementById('confirmModalButton');
        button.disabled = false;
        button.textContent = 'Confirm';
        cancelButton.disabled = false;
        closeButton.disabled = false;
        button.onclick = () => {
            button.disabled = true;
            cancelButton.disabled = true;
            closeButton.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Processing...';
            form.querySelectorAll('button, input[type="submit"]').forEach((control) => {
                control.disabled = true;
            });
            form.dataset.confirmed = 'true';
            if (submitter?.name) {
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = submitter.name;
                hidden.value = submitter.value;
                form.appendChild(hidden);
            }
            if (form.requestSubmit) {
                form.requestSubmit();
                return;
            }

            HTMLFormElement.prototype.submit.call(form);
        };
        if (!window.bootstrap?.Modal) {
            if (window.confirm(message)) {
                form.dataset.confirmed = 'true';
                form.requestSubmit ? form.requestSubmit(submitter) : HTMLFormElement.prototype.submit.call(form);
            }
            return;
        }

        new bootstrap.Modal(modalElement).show();
    });
});
</script>
@stack('scripts')
</body>
</html>
