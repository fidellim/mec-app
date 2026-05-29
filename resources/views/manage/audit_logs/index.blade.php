@extends('layouts.app')

@section('content')
@php
    $auditLogFilters = request()->only(['action', 'user_id', 'date_from', 'date_to']);
@endphp

<div class="section-header">
    <div>
        <h1 class="h3 page-heading mb-1">Audit Logs</h1>
        <div class="text-muted">Review important user, project, department, and timesheet actions.</div>
    </div>
    <a class="btn btn-outline-success" href="{{ route('manage.audit-logs.export', $auditLogFilters) }}">Export Excel</a>
</div>

<form class="filter-card mb-3 row g-2">
    <div class="col-md-3">
        <select class="form-select" name="action">
            <option value="">All actions</option>
            @foreach($actions as $action)
                <option value="{{ $action }}" @selected(request('action') === $action)>{{ str_replace('_', ' ', ucfirst($action)) }}</option>
            @endforeach
        </select>
        @error('action')
            <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-md-3">
        <select class="form-select" name="user_id">
            <option value="">All users</option>
            @foreach($users as $user)
                <option value="{{ $user->id }}" @selected(request('user_id') == $user->id)>{{ $user->name }}</option>
            @endforeach
        </select>
        @error('user_id')
            <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-md-2">
        <input class="form-control" type="date" name="date_from" value="{{ request('date_from') }}">
        @error('date_from')
            <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-md-2">
        <input class="form-control" type="date" name="date_to" value="{{ request('date_to') }}">
        @error('date_to')
            <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-md-2 d-flex gap-2">
        <button class="btn btn-primary flex-fill">Filter</button>
        <a class="btn btn-outline-secondary" href="{{ route('manage.audit-logs.index') }}">Reset</a>
    </div>
</form>

<div class="content-card overflow-hidden mb-3">
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-2 border-bottom p-3">
        <form method="POST" action="{{ route('manage.audit-logs.destroy-selected') }}" id="auditBulkDeleteForm" data-confirm="Delete the selected audit logs? This action cannot be undone.">
            @csrf
            @method('DELETE')
            @foreach($auditLogFilters as $key => $value)
                @if($value !== null && $value !== '')
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endif
            @endforeach
            @if(request('page'))
                <input type="hidden" name="page" value="{{ request('page') }}">
            @endif
            <button class="btn btn-outline-danger" type="submit" id="auditBulkDeleteButton" disabled>Delete Selected</button>
            @error('audit_log_ids')
                <div class="text-danger small mt-1">{{ $message }}</div>
            @enderror
            @error('audit_log_ids.*')
                <div class="text-danger small mt-1">{{ $message }}</div>
            @enderror
        </form>

        <form method="POST" action="{{ route('manage.audit-logs.destroy-matching') }}" id="auditDeleteMatchingForm" class="d-flex flex-column flex-lg-row align-items-lg-center gap-2" data-confirm="Delete all {{ $logs->total() }} audit logs matching the current filters? This action cannot be undone.">
            @csrf
            @method('DELETE')
            @foreach($auditLogFilters as $key => $value)
                @if($value !== null && $value !== '')
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endif
            @endforeach
            @if(request('page'))
                <input type="hidden" name="page" value="{{ request('page') }}">
            @endif
            <div class="form-check mb-0">
                <input class="form-check-input" type="checkbox" value="1" id="confirmDeleteMatching" name="confirm_delete_matching">
                <label class="form-check-label small" for="confirmDeleteMatching">I understand this permanently deletes all matching logs.</label>
            </div>
            <button class="btn btn-danger" type="submit" id="auditDeleteMatchingButton" disabled>Delete All Matching Filters</button>
            @error('confirm_delete_matching')
                <div class="text-danger small">{{ $message }}</div>
            @enderror
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th style="width: 3rem;">
                        <input class="form-check-input" type="checkbox" id="selectAuditLogsOnPage" aria-label="Select all audit logs on this page" @disabled($logs->isEmpty())>
                    </th>
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
                    <td>
                        <input class="form-check-input audit-log-checkbox" type="checkbox" name="audit_log_ids[]" value="{{ $log->id }}" form="auditBulkDeleteForm" aria-label="Select audit log {{ $log->id }}">
                    </td>
                    <td>{{ $log->created_at->format('Y-m-d H:i') }}</td>
                    <td class="fw-semibold">{{ $log->user?->name ?? 'System' }}</td>
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
                    <td colspan="7" class="empty-state">No audit logs found.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $logs->links() }}</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const selectAll = document.getElementById('selectAuditLogsOnPage');
    const checkboxes = Array.from(document.querySelectorAll('.audit-log-checkbox'));
    const bulkButton = document.getElementById('auditBulkDeleteButton');
    const confirmDeleteMatching = document.getElementById('confirmDeleteMatching');
    const deleteMatchingButton = document.getElementById('auditDeleteMatchingButton');
    const matchingLogCount = {{ $logs->total() }};

    const updateBulkState = () => {
        const selectedCount = checkboxes.filter((checkbox) => checkbox.checked).length;
        bulkButton.disabled = selectedCount === 0;

        if (selectAll) {
            selectAll.checked = selectedCount > 0 && selectedCount === checkboxes.length;
            selectAll.indeterminate = selectedCount > 0 && selectedCount < checkboxes.length;
        }
    };

    if (selectAll) {
        selectAll.addEventListener('change', () => {
            checkboxes.forEach((checkbox) => {
                checkbox.checked = selectAll.checked;
            });
            updateBulkState();
        });
    }

    checkboxes.forEach((checkbox) => checkbox.addEventListener('change', updateBulkState));

    const updateDeleteMatchingState = () => {
        deleteMatchingButton.disabled = matchingLogCount === 0 || ! confirmDeleteMatching.checked;
    };

    confirmDeleteMatching?.addEventListener('change', updateDeleteMatchingState);

    updateBulkState();
    updateDeleteMatchingState();
});
</script>
@endsection
