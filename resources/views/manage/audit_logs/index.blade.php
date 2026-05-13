@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Audit Logs</h1>
</div>

<form class="content-card p-3 mb-3 row g-2">
    <div class="col-md-3">
        <select class="form-select" name="action">
            <option value="">All actions</option>
            @foreach($actions as $action)
                <option value="{{ $action }}" @selected(request('action') === $action)>{{ str_replace('_', ' ', ucfirst($action)) }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        <select class="form-select" name="user_id">
            <option value="">All users</option>
            @foreach($users as $user)
                <option value="{{ $user->id }}" @selected(request('user_id') == $user->id)>{{ $user->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2">
        <input class="form-control" type="date" name="date_from" value="{{ request('date_from') }}">
    </div>
    <div class="col-md-2">
        <input class="form-control" type="date" name="date_to" value="{{ request('date_to') }}">
    </div>
    <div class="col-md-2 d-flex gap-2">
        <button class="btn btn-primary flex-fill">Filter</button>
        <a class="btn btn-outline-secondary" href="{{ route('manage.audit-logs.index') }}">Reset</a>
    </div>
</form>

<div class="content-card p-3">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th style="width: 11rem;">Date</th>
                    <th style="width: 10rem;">User</th>
                    <th style="width: 13rem;">Action</th>
                    <th style="width: 13rem;">Record</th>
                    <th>Changes</th>
                    <th style="width: 9rem;">IP</th>
                </tr>
            </thead>
            <tbody>
            @forelse($logs as $log)
                <tr>
                    <td>{{ $log->created_at->format('Y-m-d H:i') }}</td>
                    <td>{{ $log->user?->name ?? 'System' }}</td>
                    <td><span class="badge text-bg-secondary">{{ $log->action }}</span></td>
                    <td>
                        <div class="small">{{ class_basename($log->auditable_type) ?: '-' }}</div>
                        <div class="text-muted small">ID: {{ $log->auditable_id ?? '-' }}</div>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#audit-{{ $log->id }}">
                            View Details
                        </button>
                        <div class="collapse mt-2" id="audit-{{ $log->id }}">
                            <div class="row g-2">
                                <div class="col-lg-6">
                                    <div class="small fw-semibold mb-1">Old values</div>
                                    <pre class="small p-2 rounded border mb-0 text-wrap">{{ json_encode($log->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: 'null' }}</pre>
                                </div>
                                <div class="col-lg-6">
                                    <div class="small fw-semibold mb-1">New values</div>
                                    <pre class="small p-2 rounded border mb-0 text-wrap">{{ json_encode($log->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: 'null' }}</pre>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td>{{ $log->ip_address }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center text-muted py-4">No audit logs found.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $logs->links() }}</div>
@endsection
