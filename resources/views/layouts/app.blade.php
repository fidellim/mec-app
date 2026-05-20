<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Timesheets') }}</title>
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
        .sidebar {
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
            min-width: 0;
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
        [data-sidebar="collapsed"] .sidebar-collapse-toggle {
            transform: rotate(180deg);
        }
        .sidebar a {
            color: var(--app-sidebar-link);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: .55rem;
            padding: .72rem .85rem;
            border-radius: .55rem;
            font-weight: 500;
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
            padding-left: .72rem;
            padding-right: .72rem;
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
        .project-select { width: 18rem; max-width: 100%; }
        .attendance-select { width: 13rem; max-width: 100%; }
        .timesheet-entry-table th, .timesheet-entry-table td { white-space: nowrap; }
        .timesheet-entry-table .remarks-cell { min-width: 14rem; }
        .timesheet-entry-table input,
        .timesheet-entry-table select { min-height: 2.45rem; }
        .timesheet-entry-table tbody tr { border-left: 3px solid transparent; }
        .timesheet-entry-table tbody tr:hover { border-left-color: var(--bs-primary); }
        .form-control,
        .form-select,
        .ts-control {
            border-color: var(--app-border);
            border-radius: .55rem;
        }
        .form-control:focus,
        .form-select:focus,
        .ts-wrapper.focus .ts-control {
            border-color: var(--bs-primary);
            box-shadow: 0 0 0 .22rem var(--app-focus-ring);
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
            .timesheet-entry-table { min-width: 1420px; }
        }
        @media (max-width: 767.98px) {
            .app-layout {
                display: block;
                min-height: 0;
            }
            [data-sidebar="collapsed"] .app-layout {
                grid-template-columns: none;
            }
            .sidebar {
                min-height: auto;
                position: static;
                border-bottom: 1px solid rgba(255, 255, 255, .08);
            }
            .sidebar-header {
                align-items: flex-start;
            }
            .sidebar-collapse-toggle {
                display: none;
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
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
            .topbar {
                position: static;
                padding: 1rem !important;
            }
            .page-content { padding: 1rem; }
            .content-card-body { padding: 1rem; }
            .brand-logo-wrap { margin-bottom: 1rem !important; }
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
        }
    </style>
</head>
<body>
<div class="container-fluid app-shell">
    <div class="@auth app-layout @else guest-layout @endauth">
        @auth
            <aside class="sidebar p-3">
                <div class="sidebar-header mb-4">
                    <div class="brand-logo-wrap mb-0">
                        <img class="brand-logo" data-theme-logo src="{{ asset('images/mec_logo_light.webp') }}" alt="MEC">
                    </div>
                    <button class="sidebar-collapse-toggle" type="button" data-sidebar-toggle aria-label="Collapse sidebar" title="Collapse sidebar">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="m15 18-6-6 6-6"/>
                        </svg>
                    </button>
                </div>
                <nav class="d-grid gap-1">
                    <a href="{{ route('dashboard') }}" @class(['active' => request()->routeIs('dashboard')]) title="Dashboard"><img class="sidebar-icon" src="{{ asset('images/sidebar/dashboard.svg') }}" alt=""><span class="sidebar-label">Dashboard</span></a>
                    <a href="{{ route('employee.timesheets.index') }}" @class(['active' => request()->routeIs('employee.timesheets.*')]) title="My Timesheets"><img class="sidebar-icon" src="{{ asset('images/sidebar/my-timesheets.svg') }}" alt=""><span class="sidebar-label">My Timesheets</span></a>
                    @if(auth()->user()->role === 'hod')
                        <a href="{{ route('hod.timesheets.index') }}" @class(['active' => request()->routeIs('hod.timesheets.*')]) title="Department Timesheets"><img class="sidebar-icon" src="{{ asset('images/sidebar/department-timesheets.svg') }}" alt=""><span class="sidebar-label">Department Timesheets</span></a>
                        <a href="{{ route('hod.tracker') }}" @class(['active' => request()->routeIs('hod.tracker')]) title="Submission Tracker"><img class="sidebar-icon" src="{{ asset('images/sidebar/submission-tracker.svg') }}" alt=""><span class="sidebar-label">Submission Tracker</span></a>
                    @endif
                    @if(in_array(auth()->user()->role, ['admin', 'super_admin'], true))
                        <a href="{{ route('admin.timesheets.index') }}" @class(['active' => request()->routeIs('admin.timesheets.*')]) title="All Timesheets"><img class="sidebar-icon" src="{{ asset('images/sidebar/all-timesheets.svg') }}" alt=""><span class="sidebar-label">All Timesheets</span></a>
                    @endif
                    @if(auth()->user()->role === 'super_admin')
                        <a href="{{ route('manage.users.index') }}" @class(['active' => request()->routeIs('manage.users.*')]) title="Users"><img class="sidebar-icon" src="{{ asset('images/sidebar/users.svg') }}" alt=""><span class="sidebar-label">Users</span></a>
                        <a href="{{ route('manage.departments.index') }}" @class(['active' => request()->routeIs('manage.departments.*')]) title="Departments"><img class="sidebar-icon" src="{{ asset('images/sidebar/departments.svg') }}" alt=""><span class="sidebar-label">Departments</span></a>
                        <a href="{{ route('manage.projects.index') }}" @class(['active' => request()->routeIs('manage.projects.*')]) title="Projects"><img class="sidebar-icon" src="{{ asset('images/sidebar/projects.svg') }}" alt=""><span class="sidebar-label">Projects</span></a>
                        <a href="{{ route('manage.periods.index') }}" @class(['active' => request()->routeIs('manage.periods.*')]) title="Weekly Periods"><img class="sidebar-icon" src="{{ asset('images/sidebar/weekly-periods.svg') }}" alt=""><span class="sidebar-label">Weekly Periods</span></a>
                        <a href="{{ route('manage.automations.index') }}" @class(['active' => request()->routeIs('manage.automations.*')]) title="Automations"><img class="sidebar-icon" src="{{ asset('images/sidebar/automations.svg') }}" alt=""><span class="sidebar-label">Automations</span></a>
                        <a href="{{ route('manage.audit-logs.index') }}" @class(['active' => request()->routeIs('manage.audit-logs.*')]) title="Audit Logs"><img class="sidebar-icon" src="{{ asset('images/sidebar/audit-logs.svg') }}" alt=""><span class="sidebar-label">Audit Logs</span></a>
                    @endif
                    <a href="{{ route('guide') }}" @class(['active' => request()->routeIs('guide')]) title="Help Guide"><img class="sidebar-icon" src="{{ asset('images/sidebar/guide.svg') }}" alt=""><span class="sidebar-label">Help Guide</span></a>
                </nav>
            </aside>
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
<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="confirmModalMessage">Are you sure?</div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmModalButton">Confirm</button>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script>
const initializeTooltips = (scope = document) => {
    scope.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((element) => {
        bootstrap.Tooltip.getOrCreateInstance(element);
    });
};

initializeTooltips();

const initializeSearchableSelects = (scope = document) => {
    if (!window.TomSelect) {
        return;
    }

    scope.querySelectorAll('select.form-select').forEach((select) => {
        if (select.tomselect || select.dataset.searchable === 'false') {
            return;
        }

        new TomSelect(select, {
            allowEmptyOption: true,
            create: false,
            dropdownParent: 'body',
            maxOptions: null,
            searchField: ['text'],
            sortField: [{ field: '$order' }],
        });
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
    });

    document.querySelectorAll('[data-sidebar-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const nextState = sidebarState() === 'collapsed' ? 'expanded' : 'collapsed';
            localStorage.setItem('sidebar', nextState);
            applySidebarState(nextState);
            applyLogos(effectiveTheme());
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
        document.getElementById('confirmModalMessage').textContent = message;
        const button = document.getElementById('confirmModalButton');
        button.onclick = () => {
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
        new bootstrap.Modal(document.getElementById('confirmModal')).show();
    });
});
</script>
</body>
</html>
