@extends('layouts.app')

@section('content')
<div class="section-header">
    <div>
        <h1 class="h3 page-heading mb-1">Automation Controls</h1>
        <div class="text-muted">Enable or pause scheduled background tasks during operations or emergencies.</div>
    </div>
</div>

<div class="content-card overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Automation</th>
                    <th style="width: 9rem;">Status</th>
                    <th style="width: 13rem;">Last run</th>
                    <th style="width: 11rem;"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($automations as $automation)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $automation->name }}</div>
                            <div class="text-muted small">{{ $automation->description ?: $automation->key }}</div>
                        </td>
                        <td>
                            <span class="badge {{ $automation->is_enabled ? 'text-bg-success' : 'text-bg-secondary' }}">
                                {{ $automation->is_enabled ? 'Enabled' : 'Disabled' }}
                            </span>
                        </td>
                        <td>
                            {{ $automation->last_run_at ? $automation->last_run_at->format('M d, Y H:i') : '-' }}
                        </td>
                        <td class="text-end">
                            <form method="post" action="{{ route('manage.automations.toggle', $automation) }}" data-confirm="{{ $automation->is_enabled ? 'Disable this automation? Scheduled runs will be skipped until it is enabled again.' : 'Enable this automation? Scheduled runs will resume.' }}">
                                @csrf
                                @method('patch')
                                <button class="btn btn-sm {{ $automation->is_enabled ? 'btn-outline-danger' : 'btn-outline-success' }}">
                                    {{ $automation->is_enabled ? 'Disable' : 'Enable' }}
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="empty-state">No automations have been configured yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
