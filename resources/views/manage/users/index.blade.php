@extends('layouts.app')

@section('content')
@php($roleLabels = config('roles.labels'))
<div class="section-header">
    <div>
        <h1 class="h3 page-heading mb-1">Users</h1>
        <div class="text-muted">Manage accounts, roles, employee numbers, and access status.</div>
    </div>
    <a class="btn btn-primary" href="{{ route('manage.users.create') }}">New User</a>
</div>

<form class="filter-card mb-3 row g-2 align-items-end" method="get" action="{{ route('manage.users.index') }}">
    <div class="col-12 col-md-5 col-lg-4">
        <label class="form-label" for="department_id">Department</label>
        <select class="form-select @error('department_id') is-invalid @enderror" id="department_id" name="department_id">
            <option value="">All departments</option>
            <option value="unassigned" @selected($selectedDepartmentId === 'unassigned')>Unassigned</option>
            @foreach($departments as $department)
                <option value="{{ $department->id }}" @selected((string) $selectedDepartmentId === (string) $department->id)>
                    {{ $department->name }}
                </option>
            @endforeach
        </select>
        @error('department_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-12 col-md-auto">
        <button class="btn btn-primary" type="submit">Apply Filter</button>
    </div>
    @if(filled($selectedDepartmentId))
        <div class="col-12 col-md-auto">
            <a class="btn btn-outline-secondary" href="{{ route('manage.users.index') }}">Clear</a>
        </div>
    @endif
</form>

<div class="content-card overflow-hidden">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Employee Number</th>
                    <th>Initials</th>
                    <th>Job Title</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Department</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($users as $user)
                    @php($headedDepartment = $user->headedDepartment)
                    @php($departmentReplacementHods = $headedDepartment ? $replacementHods->where('department_id', $headedDepartment->id)->where('id', '!=', $user->id) : collect())
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $user->name }}</div>
                            @if($headedDepartment)
                                <div class="small text-muted">Head of Department for {{ $headedDepartment->name }}</div>
                            @endif
                        </td>
                        <td>{{ $user->employee_code ?: '-' }}</td>
                        <td>{{ $user->initials ?: '-' }}</td>
                        <td>{{ $user->job_title ?: '-' }}</td>
                        <td>{{ $user->email }}</td>
                        <td><span class="badge text-bg-light border text-dark">{{ $roleLabels[$user->role] ?? $user->role }}</span></td>
                        <td>{{ $user->department?->name ?: '-' }}</td>
                        <td><span class="badge {{ $user->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $user->is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td class="text-end">
                            <div class="action-group">
                                <a class="btn btn-sm btn-primary" href="{{ route('manage.users.edit', $user) }}">Edit</a>
                                @if((int) $user->id !== (int) auth()->id())
                                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteUserModal{{ $user->id }}">
                                        Delete
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>

                @endforeach
                @if($users->isEmpty())
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">No users match the selected department.</td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>
</div>

@foreach($users as $user)
    @php($headedDepartment = $user->headedDepartment)
    @php($departmentReplacementHods = $headedDepartment ? $replacementHods->where('department_id', $headedDepartment->id)->where('id', '!=', $user->id) : collect())
    @if((int) $user->id !== (int) auth()->id())
        <div class="modal fade" id="deleteUserModal{{ $user->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="post" action="{{ route('manage.users.destroy', $user) }}">
                        @csrf
                        @method('delete')
                        <div class="modal-header">
                            <h5 class="modal-title">Delete user</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-2">Delete <strong>{{ $user->name }}</strong>?</p>
                            <p class="text-muted mb-3">This will permanently remove the user and all timesheets and entries owned by this user.</p>

                            @if($headedDepartment)
                                <div class="alert alert-warning">
                                    This user is the Head of Department for {{ $headedDepartment->name }}. Select a replacement Head of Department before deleting.
                                </div>
                                <label class="form-label">Replacement Head of Department</label>
                                <select class="form-select" name="replacement_hod_id" required @disabled($departmentReplacementHods->isEmpty())>
                                    <option value="">Select replacement</option>
                                    @foreach($departmentReplacementHods as $replacementHod)
                                        <option value="{{ $replacementHod->id }}">{{ $replacementHod->name }} - {{ $replacementHod->employee_code }}</option>
                                    @endforeach
                                </select>
                                @if($departmentReplacementHods->isEmpty())
                                    <div class="form-text text-danger">Create or update another active Head of Department in {{ $headedDepartment->name }} before deleting this user.</div>
                                @endif
                            @endif
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger" @disabled($headedDepartment && $departmentReplacementHods->isEmpty())>Delete User</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endforeach

<div class="mt-3">{{ $users->links() }}</div>
@endsection
