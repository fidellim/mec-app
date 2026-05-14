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
        })();
    </script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --app-body-bg: #f5f7fb;
            --app-card-bg: #ffffff;
            --app-border: #e5e7eb;
            --app-sidebar-bg: #182230;
            --app-sidebar-link: #d6dde8;
            --app-sidebar-hover: #253449;
            --app-topbar-bg: #ffffff;
            --app-logo-plate-bg: #ffffff;
            --app-logo-plate-border: rgba(255, 255, 255, .2);
        }
        [data-bs-theme="dark"] {
            --app-body-bg: #101418;
            --app-card-bg: #171c22;
            --app-border: #2b3440;
            --app-sidebar-bg: #0c1117;
            --app-sidebar-link: #c7d0dc;
            --app-sidebar-hover: #1b2633;
            --app-topbar-bg: #171c22;
            --app-logo-plate-bg: transparent;
            --app-logo-plate-border: transparent;
        }
        body { background: var(--app-body-bg); }
        .sidebar { min-height: 100vh; background: var(--app-sidebar-bg); }
        .sidebar a { color: var(--app-sidebar-link); text-decoration: none; display: block; padding: .65rem 1rem; border-radius: .35rem; }
        .sidebar a:hover, .sidebar a.active { background: var(--app-sidebar-hover); color: #fff; }
        .topbar { background: var(--app-topbar-bg); }
        .content-card { background: var(--app-card-bg); border: 1px solid var(--app-border); border-radius: .5rem; }
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
        .sidebar .brand-logo { width: 9.5rem; height: auto; max-height: 3.25rem; }
        .login-logo { height: 4.5rem; max-width: 14rem; object-fit: contain; }
        .table > :not(caption) > * > * { vertical-align: middle; }
        .table-fixed { table-layout: fixed; }
        .text-truncate-cell { max-width: 1px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .project-select { width: 18rem; max-width: 100%; }
        .attendance-select { width: 13rem; max-width: 100%; }
        .timesheet-entry-table th, .timesheet-entry-table td { white-space: nowrap; }
        .timesheet-entry-table .remarks-cell { min-width: 14rem; }
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
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        @auth
            <aside class="col-md-3 col-xl-2 sidebar p-3">
                <div class="brand-logo-wrap mb-4">
                    <img class="brand-logo" data-theme-logo src="{{ asset('images/mec_logo_light.webp') }}" alt="MEC">
                </div>
                <nav class="d-grid gap-1">
                    <a href="{{ route('dashboard') }}">Dashboard</a>
                    <a href="{{ route('employee.timesheets.index') }}">My Timesheets</a>
                    @if(auth()->user()->role === 'hod')
                        <a href="{{ route('hod.timesheets.index') }}">Department Timesheets</a>
                        <a href="{{ route('hod.tracker') }}">Submission Tracker</a>
                    @endif
                    @if(in_array(auth()->user()->role, ['admin', 'super_admin'], true))
                        <a href="{{ route('admin.timesheets.index') }}">All Timesheets</a>
                    @endif
                    @if(auth()->user()->role === 'super_admin')
                        <a href="{{ route('manage.users.index') }}">Users</a>
                        <a href="{{ route('manage.departments.index') }}">Departments</a>
                        <a href="{{ route('manage.projects.index') }}">Projects</a>
                        <a href="{{ route('manage.periods.index') }}">Weekly Periods</a>
                        <a href="{{ route('manage.audit-logs.index') }}">Audit Logs</a>
                    @endif
                </nav>
            </aside>
        @endauth
        <main class="@auth col-md-9 col-xl-10 @else col-12 @endauth p-0">
            @auth
                <header class="topbar border-bottom px-4 py-3 d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fw-semibold">{{ auth()->user()->name }}</div>
                        <div class="small text-muted text-capitalize">{{ str_replace('_', ' ', auth()->user()->role) }}</div>
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
            <section class="p-4">
                @guest
                    <div class="d-flex justify-content-end mb-3">
                        <button class="theme-switch" type="button" data-theme-toggle aria-label="Toggle color theme" title="Toggle color theme">
                            <img class="theme-switch-icon" data-theme-icon src="{{ asset('images/sun-icon.svg') }}" alt="">
                        </button>
                    </div>
                @endguest
                @if(session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif
                @if(session('warning'))
                    <div class="alert alert-warning">{{ session('warning') }}</div>
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
<script>
const initializeTooltips = (scope = document) => {
    scope.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((element) => {
        bootstrap.Tooltip.getOrCreateInstance(element);
    });
};

initializeTooltips();

(() => {
    const buttons = document.querySelectorAll('[data-theme-toggle]');
    const icons = document.querySelectorAll('[data-theme-icon]');
    const media = window.matchMedia('(prefers-color-scheme: dark)');
    const iconPaths = {
        light: "{{ asset('images/moon-icon.svg') }}",
        dark: "{{ asset('images/sun-icon.svg') }}",
    };
    const logoPaths = {
        light: "{{ asset('images/mec_logo_light.webp') }}",
        dark: "{{ asset('images/mec_logo_dark.webp') }}",
    };

    const effectiveTheme = () => localStorage.getItem('theme') || (media.matches ? 'dark' : 'light');
    const applyTheme = (theme) => {
        document.documentElement.setAttribute('data-bs-theme', theme);
        icons.forEach((icon) => {
            icon.src = iconPaths[theme];
        });
        document.querySelectorAll('[data-theme-logo]').forEach((logo) => {
            logo.src = logoPaths[theme];
        });
        buttons.forEach((button) => {
            button.setAttribute('aria-label', theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
            button.setAttribute('title', theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
        });
    };

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
