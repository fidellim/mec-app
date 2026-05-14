@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Users</h1>
    <a class="btn btn-primary" href="{{ route('manage.users.create') }}">New User</a>
</div>

<div class="content-card p-3">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Employee Number</th>
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
                                <div class="small text-muted">HOD of {{ $headedDepartment->name }}</div>
                            @endif
                        </td>
                        <td>{{ $user->employee_code ?: '-' }}</td>
                        <td>{{ $user->email }}</td>
                        <td>{{ str_replace('_', ' ', $user->role) }}</td>
                        <td>{{ $user->department?->name }}</td>
                        <td>{{ $user->is_active ? 'Active' : 'Inactive' }}</td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-2">
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
                                    This user is the HOD of {{ $headedDepartment->name }}. Select a replacement HOD before deleting.
                                </div>
                                <label class="form-label">Replacement HOD</label>
                                <select class="form-select" name="replacement_hod_id" required @disabled($departmentReplacementHods->isEmpty())>
                                    <option value="">Select replacement</option>
                                    @foreach($departmentReplacementHods as $replacementHod)
                                        <option value="{{ $replacementHod->id }}">{{ $replacementHod->name }} - {{ $replacementHod->employee_code }}</option>
                                    @endforeach
                                </select>
                                @if($departmentReplacementHods->isEmpty())
                                    <div class="form-text text-danger">Create or update another active HOD in {{ $headedDepartment->name }} before deleting this user.</div>
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
