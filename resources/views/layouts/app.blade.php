<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Timesheets') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f5f7fb; }
        .sidebar { min-height: 100vh; background: #182230; }
        .sidebar a { color: #d6dde8; text-decoration: none; display: block; padding: .65rem 1rem; border-radius: .35rem; }
        .sidebar a:hover, .sidebar a.active { background: #253449; color: #fff; }
        .content-card { background: #fff; border: 1px solid #e5e7eb; border-radius: .5rem; }
        .table > :not(caption) > * > * { vertical-align: middle; }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        @auth
            <aside class="col-md-3 col-xl-2 sidebar p-3">
                <div class="text-white fw-semibold fs-5 mb-4">Timesheets</div>
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
                    @endif
                </nav>
            </aside>
        @endauth
        <main class="@auth col-md-9 col-xl-10 @else col-12 @endauth p-0">
            @auth
                <header class="bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fw-semibold">{{ auth()->user()->name }}</div>
                        <div class="small text-muted text-capitalize">{{ str_replace('_', ' ', auth()->user()->role) }}</div>
                    </div>
                    <form method="post" action="{{ route('logout') }}">
                        @csrf
                        <button class="btn btn-outline-secondary btn-sm">Logout</button>
                    </form>
                </header>
            @endauth
            <section class="p-4">
                @if(session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
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
            form.submit();
        };
        new bootstrap.Modal(document.getElementById('confirmModal')).show();
    });
});
</script>
</body>
</html>
