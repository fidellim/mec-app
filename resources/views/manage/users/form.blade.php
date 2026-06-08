@extends('layouts.app')

@section('content')
@php($roleLabels = config('roles.labels'))
<div class="section-header"><div><h1 class="h3 page-heading mb-1">{{ $userModel->exists ? 'Edit User' : 'New User' }}</h1><div class="text-muted">Set employee identity, role, department, and account status.</div></div></div>
<form class="content-card p-3" method="post" action="{{ $userModel->exists ? route('manage.users.update', $userModel) : route('manage.users.store') }}">
    @csrf @if($userModel->exists) @method('put') @endif
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Name</label><input class="form-control" name="name" value="{{ old('name', $userModel->name) }}" required></div>
        <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" type="email" name="email" value="{{ old('email', $userModel->email) }}" required></div>
        <div class="col-md-4">
            <label class="form-label">Employee Number</label>
            <input class="form-control" name="employee_code" value="{{ old('employee_code', $userModel->employee_code) }}" placeholder="MEC-PHIL-HR-2026-095">
            <div class="form-text">Required for employees and Heads of Department. Use MEC-HR, MCE-HR, or MEC-PHIL-HR followed by YYYY-NNN.</div>
        </div>
        <div class="col-md-4">
            <label class="form-label">Initials</label>
            <input class="form-control" name="initials" value="{{ old('initials', $userModel->initials) }}" maxlength="20" placeholder="ABC">
            <div class="form-text">Optional. Used in timesheet exports.</div>
        </div>
        <div class="col-md-4">
            <label class="form-label">Job Title</label>
            <input class="form-control" name="job_title" value="{{ old('job_title', $userModel->job_title) }}" maxlength="100" placeholder="Project Engineer">
            <div class="form-text">Optional. Shown in timesheet exports.</div>
        </div>
        <div class="col-md-4"><label class="form-label">Role</label><select class="form-select" name="role" id="roleSelect">@foreach(['super_admin','admin','hod','employee'] as $role)<option value="{{ $role }}" @selected(old('role', $userModel->role ?: 'employee') === $role)>{{ $roleLabels[$role] ?? $role }}</option>@endforeach</select></div>
        <div class="col-md-4"><label class="form-label">Department</label><select class="form-select" name="department_id"><option value="">None</option>@foreach($departments as $department)<option value="{{ $department->id }}" @selected(old('department_id', $userModel->department_id) == $department->id)>{{ $department->name }}{{ $department->is_active ? '' : ' (inactive)' }}</option>@endforeach</select></div>
        <div class="col-md-4 d-flex align-items-end" data-super-admin-option>
            <div class="form-check">
                <input type="hidden" name="receives_hod_timesheet_submission_emails" value="0">
                <input class="form-check-input" type="checkbox" name="receives_hod_timesheet_submission_emails" value="1" id="receivesHodSubmissionEmails" @checked(old('receives_hod_timesheet_submission_emails', $userModel->receives_hod_timesheet_submission_emails ?? true))>
                <label class="form-check-label" for="receivesHodSubmissionEmails">Receive HOD timesheet submission emails</label>
            </div>
        </div>
        <div class="col-md-6">
            <label class="form-label">Password {{ $userModel->exists ? '(leave blank to keep current)' : '' }}</label>
            <div class="input-group">
                <input class="form-control @error('password') is-invalid @enderror" id="password" type="password" name="password" minlength="10" maxlength="64" {{ $userModel->exists ? '' : 'required' }}>
                <button class="btn btn-outline-secondary" type="button" data-password-toggle="password" aria-label="Show password">Show</button>
            </div>
            @error('password')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>
        <div class="col-md-6 d-flex align-items-end"><div class="form-check"><input type="hidden" name="is_active" value="0"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="active" @checked(old('is_active', $userModel->is_active ?? true))><label class="form-check-label" for="active">Active</label></div></div>
    </div>
    <div class="text-end mt-3"><button class="btn btn-primary">Save User</button></div>
</form>
@endsection

@push('scripts')
<script>
document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        const input = document.getElementById(button.dataset.passwordToggle);
        const isHidden = input.type === 'password';
        input.type = isHidden ? 'text' : 'password';
        button.textContent = isHidden ? 'Hide' : 'Show';
        button.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
    });
});

const roleSelect = document.getElementById('roleSelect');
const superAdminOption = document.querySelector('[data-super-admin-option]');

if (roleSelect && superAdminOption) {
    const syncSuperAdminOption = () => {
        superAdminOption.classList.toggle('d-none', roleSelect.value !== 'super_admin');
    };

    roleSelect.addEventListener('change', syncSuperAdminOption);
    syncSuperAdminOption();
}
</script>
@endpush
