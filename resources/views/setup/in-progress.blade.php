@extends('layouts.app')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8 col-xl-7">
        <div class="content-card p-4">
            <div class="d-flex flex-column gap-3">
                <div>
                    <span class="badge text-bg-warning mb-3">Setup in progress</span>
                    <h1 class="h3 page-heading mb-2">System setup is currently in progress</h1>
                    <div class="text-muted">
                        The timesheet system is temporarily paused while administrators finish configuration. Please check back shortly.
                    </div>
                </div>

                <div class="alert alert-info mb-0">
                    Your account remains active. Access will resume automatically once setup mode is disabled.
                </div>

                <form method="post" action="{{ route('logout') }}" class="mb-0">
                    @csrf
                    <button class="btn btn-outline-secondary">Sign Out</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
