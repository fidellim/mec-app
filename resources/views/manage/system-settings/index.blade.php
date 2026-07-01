@extends('layouts.app')

@section('content')
<div class="section-header">
    <div>
        <h1 class="h3 page-heading mb-1">System Settings</h1>
        <div class="text-muted">Control temporary access locks used during production setup and operational pauses.</div>
    </div>
</div>

<div class="content-card p-3">
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-2">
                <h2 class="h5 mb-0">Setup Mode</h2>
                <span class="badge {{ $setupMode->boolean_value ? 'text-bg-warning' : 'text-bg-success' }}">
                    {{ $setupMode->boolean_value ? 'Enabled' : 'Disabled' }}
                </span>
            </div>
            <div class="text-muted">
                When enabled, employees and HODs are sent to a setup notice after login. Admins and super admins can continue using admin workflows.
            </div>
        </div>

        <form method="post" action="{{ route('manage.system-settings.setup-mode') }}" data-confirm="{{ $setupMode->boolean_value ? 'Disable setup mode and allow employees and HODs back into the system?' : 'Enable setup mode and pause employee and HOD access?' }}">
            @csrf
            @method('patch')
            <input type="hidden" name="setup_mode_enabled" value="{{ $setupMode->boolean_value ? '0' : '1' }}">
            <button class="btn {{ $setupMode->boolean_value ? 'btn-outline-success' : 'btn-warning' }}">
                {{ $setupMode->boolean_value ? 'Disable Setup Mode' : 'Enable Setup Mode' }}
            </button>
        </form>
    </div>

    <div class="alert alert-info mt-3 mb-0">
        Use Laravel maintenance mode for deployment-time protection. Use setup mode after the application is online but employee and HOD access should remain paused.
    </div>
</div>
@endsection
