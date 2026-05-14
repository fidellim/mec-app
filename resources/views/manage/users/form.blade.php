@extends('layouts.app')

@section('content')
<h1 class="h3 mb-3">{{ $userModel->exists ? 'Edit User' : 'New User' }}</h1>
<form class="content-card p-3" method="post" action="{{ $userModel->exists ? route('manage.users.update', $userModel) : route('manage.users.store') }}">
    @csrf @if($userModel->exists) @method('put') @endif
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Name</label><input class="form-control" name="name" value="{{ old('name', $userModel->name) }}" required></div>
        <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" type="email" name="email" value="{{ old('email', $userModel->email) }}" required></div>
        <div class="col-md-4">
            <label class="form-label">Employee Number</label>
            <input class="form-control" name="employee_code" value="{{ old('employee_code', $userModel->employee_code) }}" placeholder="MEC-HR-2026-095">
            <div class="form-text">Required for employees and HODs. Use MEC/MCE-HR-YYYY-NNN.</div>
        </div>
        <div class="col-md-4"><label class="form-label">Role</label><select class="form-select" name="role">@foreach(['super_admin','admin','hod','employee'] as $role)<option value="{{ $role }}" @selected(old('role', $userModel->role ?: 'employee') === $role)>{{ str_replace('_', ' ', ucfirst($role)) }}</option>@endforeach</select></div>
        <div class="col-md-4"><label class="form-label">Department</label><select class="form-select" name="department_id"><option value="">None</option>@foreach($departments as $department)<option value="{{ $department->id }}" @selected(old('department_id', $userModel->department_id) == $department->id)>{{ $department->name }}{{ $department->is_active ? '' : ' (inactive)' }}</option>@endforeach</select></div>
        <div class="col-md-6"><label class="form-label">Password {{ $userModel->exists ? '(leave blank to keep current)' : '' }}</label><input class="form-control" type="password" name="password" {{ $userModel->exists ? '' : 'required' }}></div>
        <div class="col-md-6 d-flex align-items-end"><div class="form-check"><input type="hidden" name="is_active" value="0"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="active" @checked(old('is_active', $userModel->is_active ?? true))><label class="form-check-label" for="active">Active</label></div></div>
    </div>
    <div class="text-end mt-3"><button class="btn btn-primary">Save User</button></div>
</form>
@endsection
