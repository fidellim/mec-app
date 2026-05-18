@extends('layouts.app')

@section('content')
<div class="section-header">
    <div>
        <h1 class="h3 page-heading mb-1">Departments</h1>
        <div class="text-muted">Archive departments with history and delete only unused records.</div>
    </div>
    <a class="btn btn-primary" href="{{ route('manage.departments.create') }}">New Department</a>
</div>

<div class="content-card overflow-hidden">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Code</th>
                    <th>Head of Department</th>
                    <th>Status</th>
                    <th>Usage</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($departments as $department)
                    @php($canDelete = $department->users_count === 0 && $department->timesheets_count === 0 && ! $department->hod_id)
                    <tr>
                        <td>{{ $department->name }}</td>
                        <td>{{ $department->code ?: '-' }}</td>
                        <td>{{ $department->hod?->name ?: '-' }}</td>
                        <td>
                            <span class="badge {{ $department->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                {{ $department->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="small text-muted">
                            {{ $department->users_count }} users / {{ $department->timesheets_count }} timesheets
                        </td>
                        <td class="text-end">
                            <div class="action-group">
                                <a class="btn btn-sm btn-primary" href="{{ route('manage.departments.edit', $department) }}">Edit</a>
                                <form method="post" action="{{ route('manage.departments.status', $department) }}" data-confirm="{{ $department->is_active ? 'Deactivate this department? Existing records will remain visible.' : 'Reactivate this department?' }}">
                                    @csrf
                                    @method('patch')
                                    <button class="btn btn-sm btn-outline-secondary">{{ $department->is_active ? 'Deactivate' : 'Reactivate' }}</button>
                                </form>
                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteDepartmentModal{{ $department->id }}">
                                    Delete
                                </button>
                            </div>
                        </td>
                    </tr>

                @endforeach
            </tbody>
        </table>
    </div>
</div>

@foreach($departments as $department)
    @php($canDelete = $department->users_count === 0 && $department->timesheets_count === 0 && ! $department->hod_id)
    <div class="modal fade" id="deleteDepartmentModal{{ $department->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="post" action="{{ route('manage.departments.destroy', $department) }}">
                    @csrf
                    @method('delete')
                    <div class="modal-header">
                        <h5 class="modal-title">Delete department</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2">Delete <strong>{{ $department->name }}</strong>?</p>
                        @if($canDelete)
                            <p class="text-muted mb-0">This department has no users, timesheets, or assigned Head of Department, so it can be permanently deleted.</p>
                        @else
                            <div class="alert alert-warning mb-0">
                                This department has users, timesheets, or an assigned Head of Department. Deactivate it instead to preserve historical records.
                            </div>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger" @disabled(! $canDelete)>Delete Department</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endforeach

<div class="mt-3">{{ $departments->links() }}</div>
@endsection
