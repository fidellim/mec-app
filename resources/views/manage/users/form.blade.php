@extends('layouts.app')

@section('content')
@php
    $roleLabels = config('roles.labels');
    $selectedNotificationExclusions = collect(old('hod_notification_exclusion_ids', $hodNotificationExclusionIds ?? []))->map(fn ($id) => (int) $id)->all();
    $selectedApprovalExclusions = collect(old('hod_approval_exclusion_ids', $hodApprovalExclusionIds ?? []))->map(fn ($id) => (int) $id)->all();
@endphp
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
        <div class="col-md-4">
            <label class="form-label" for="annual_leave_allowance_days">Annual leave allowance override</label>
            <input class="form-control @error('annual_leave_allowance_days') is-invalid @enderror" id="annual_leave_allowance_days" name="annual_leave_allowance_days" type="number" min="0" step="0.5" value="{{ old('annual_leave_allowance_days', $userModel->annual_leave_allowance_days) }}" placeholder="Use regional default">
            <div class="form-text">Optional L100 yearly allowance. Blank uses the regional default; unused days expire each December 31.</div>
            @error('annual_leave_allowance_days')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="col-md-4 d-flex align-items-end" data-admin-email-option>
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
    @if($userModel->exists && $userModel->role === 'hod')
        <div class="content-card mt-4">
            <div class="content-card-header">
                <h2 class="h5 mb-1">HOD notification and approval exceptions</h2>
                <div class="small text-muted">These settings apply only to users in departments this HOD currently manages.</div>
            </div>
            <div class="content-card-body">
                @if($hodExclusionCandidates->isEmpty())
                    <div class="alert alert-warning mb-0">No eligible users are available for this HOD. Assign managed departments before adding exceptions.</div>
                @else
                    <div class="row g-3">
                        <div class="col-lg-6">
                            <label class="form-label" for="hodNotificationExclusionIds">Do not email this HOD for submissions from</label>
                            <select class="form-select @error('hod_notification_exclusion_ids') is-invalid @enderror @error('hod_notification_exclusion_ids.*') is-invalid @enderror" id="hodNotificationExclusionIds" name="hod_notification_exclusion_ids[]" multiple>
                                @foreach($hodExclusionCandidates as $candidate)
                                    <option value="{{ $candidate->id }}" @selected(in_array((int) $candidate->id, $selectedNotificationExclusions, true))>{{ $candidate->name }} - {{ $roleLabels[$candidate->role] ?? $candidate->role }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">Email-only. This HOD can still approve or reject these users.</div>
                            @error('hod_notification_exclusion_ids')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            @error('hod_notification_exclusion_ids.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-lg-6">
                            <label class="form-label" for="hodApprovalExclusionIds">Do not allow this HOD to approve/reject submissions from</label>
                            <select class="form-select @error('hod_approval_exclusion_ids') is-invalid @enderror @error('hod_approval_exclusion_ids.*') is-invalid @enderror" id="hodApprovalExclusionIds" name="hod_approval_exclusion_ids[]" multiple>
                                @foreach($hodExclusionCandidates as $candidate)
                                    <option value="{{ $candidate->id }}" @selected(in_array((int) $candidate->id, $selectedApprovalExclusions, true))>{{ $candidate->name }} - {{ $roleLabels[$candidate->role] ?? $candidate->role }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">Approval restriction. At least one other eligible HOD approver must remain.</div>
                            @error('hod_approval_exclusion_ids')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            @error('hod_approval_exclusion_ids.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif
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
const adminEmailOption = document.querySelector('[data-admin-email-option]');

if (roleSelect && adminEmailOption) {
    const syncAdminEmailOption = () => {
        adminEmailOption.classList.toggle('d-none', ! ['admin', 'super_admin'].includes(roleSelect.value));
    };

    roleSelect.addEventListener('change', syncAdminEmailOption);
    syncAdminEmailOption();
}
</script>
@endpush
