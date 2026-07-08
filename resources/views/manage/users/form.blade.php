@extends('layouts.app')

@section('content')
@php
    $roleLabels = config('roles.labels');
    $genderLabels = ['male' => 'Male', 'female' => 'Female'];
    $maritalStatusLabels = ['single' => 'Single', 'married' => 'Married', 'widowed' => 'Widowed', 'separated' => 'Separated'];
    $selectedNotificationExclusions = collect(old('hod_notification_exclusion_ids', $hodNotificationExclusionIds ?? []))->map(fn ($id) => (int) $id)->all();
    $selectedApprovalExclusions = collect(old('hod_approval_exclusion_ids', $hodApprovalExclusionIds ?? []))->map(fn ($id) => (int) $id)->all();
    $selectedVisibilityExclusions = collect(old('hod_visibility_exclusion_ids', $hodVisibilityExclusionIds ?? []))->map(fn ($id) => (int) $id)->all();
    $visibilityExcludableIds = collect($hodVisibilityExcludableIds ?? [])->map(fn ($id) => (int) $id)->all();
    $hasVisibilityBlockedCandidates = $hodExclusionCandidates->contains(fn ($candidate) => ! in_array((int) $candidate->id, $visibilityExcludableIds, true));
    $isSuperAdmin = auth()->user()->role === 'super_admin';
    $canEditAnnualLeaveOverride = in_array(auth()->user()->role, ['admin', 'super_admin'], true);
    $employeeCodeForRegion = old('employee_code', $userModel->employee_code);
    $workRegion = is_string($employeeCodeForRegion) && str_starts_with($employeeCodeForRegion, 'MEC-PHIL-HR-')
        ? 'ph'
        : (is_string($employeeCodeForRegion) && (str_starts_with($employeeCodeForRegion, 'MEC-HR-') || str_starts_with($employeeCodeForRegion, 'MCE-HR-'))
            ? 'uae'
            : null);
@endphp
<div class="section-header"><div><h1 class="h3 page-heading mb-1">{{ $userModel->exists ? 'Edit User' : 'New User' }}</h1><div class="text-muted">{{ $isSuperAdmin ? 'Set employee identity, role, department, and account status.' : 'Update employee profile details and account status.' }}</div></div></div>
<form class="content-card p-3" method="post" action="{{ $userModel->exists ? route('manage.users.update', $userModel) : route('manage.users.store') }}">
    @csrf @if($userModel->exists) @method('put') @endif
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Name</label><input class="form-control" name="name" value="{{ old('name', $userModel->name) }}" required></div>
        @if($isSuperAdmin)
            <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" type="email" name="email" value="{{ old('email', $userModel->email) }}" required></div>
        @else
            <div class="col-md-6">
                <div class="meta-label">Email</div>
                <div class="meta-value">{{ $userModel->email }}</div>
            </div>
        @endif
        <div class="col-md-4">
            <label class="form-label">Employee Number</label>
            <input class="form-control" id="employee_code" name="employee_code" value="{{ old('employee_code', $userModel->employee_code) }}" placeholder="MEC-PHIL-HR-2026-095" data-region-source>
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
        <div class="col-md-4">
            <label class="form-label" for="gender">Gender</label>
            <select class="form-select @error('gender') is-invalid @enderror" id="gender" name="gender">
                <option value="">Not specified</option>
                @foreach($genderLabels as $value => $label)
                    <option value="{{ $value }}" @selected(old('gender', $userModel->gender) === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('gender')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="col-md-4">
            <label class="form-label" for="joining_date">Joining Date</label>
            <input class="form-control @error('joining_date') is-invalid @enderror" id="joining_date" name="joining_date" type="date" value="{{ old('joining_date', optional($userModel->joining_date)->format('Y-m-d')) }}">
            @error('joining_date')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="col-md-4">
            <label class="form-label" for="marital_status">Marital Status</label>
            <select class="form-select @error('marital_status') is-invalid @enderror" id="marital_status" name="marital_status">
                <option value="">Not specified</option>
                @foreach($maritalStatusLabels as $value => $label)
                    <option value="{{ $value }}" @selected(old('marital_status', $userModel->marital_status) === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('marital_status')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        @if($isSuperAdmin)
            <div class="col-md-4"><label class="form-label">Role</label><select class="form-select" name="role" id="roleSelect">@foreach(['super_admin','admin','hod','employee'] as $role)<option value="{{ $role }}" @selected(old('role', $userModel->role ?: 'employee') === $role)>{{ $roleLabels[$role] ?? $role }}</option>@endforeach</select></div>
        @else
            <div class="col-md-4">
                <div class="meta-label">Role</div>
                <div><span class="badge text-bg-light border text-dark">{{ $roleLabels[$userModel->role] ?? $userModel->role }}</span></div>
            </div>
        @endif
        <div class="col-md-4"><label class="form-label">Department</label><select class="form-select" name="department_id"><option value="">None</option>@foreach($departments as $department)<option value="{{ $department->id }}" @selected(old('department_id', $userModel->department_id) == $department->id)>{{ $department->name }}{{ $department->is_active ? '' : ' (inactive)' }}</option>@endforeach</select></div>
        <input type="hidden" name="eligible_for_parental_leave" value="0">
        <input type="hidden" name="eligible_for_bereavement_spouse_leave" value="0">
        <input type="hidden" name="eligible_for_bereavement_immediate_family_leave" value="0">
        <input type="hidden" name="eligible_for_maternity_leave" value="0">
        <input type="hidden" name="eligible_for_paternity_leave" value="0">
        <input type="hidden" name="eligible_for_vawc_leave" value="0">
        <input type="hidden" name="eligible_for_special_women_leave" value="0">
        <input type="hidden" name="is_solo_parent" value="0">
        <div class="col-12 {{ $workRegion ? 'd-none' : '' }}" data-region-panel="unknown">
            <div class="p-3 bg-body-tertiary border rounded-3">
                <div class="fw-semibold mb-1">Leave eligibility region</div>
                <div class="form-text">Enter an employee number to show UAE or Philippines leave eligibility controls.</div>
            </div>
        </div>
        <div class="col-12 {{ $workRegion === 'uae' ? '' : 'd-none' }}" data-region-panel="uae">
            <div class="p-3 bg-body-tertiary border rounded-3">
                <div class="fw-semibold mb-1">UAE leave eligibility</div>
                <div class="form-text mb-3">HR-attested UAE eligibility controls. Event-based eligibility is automatically unticked after the related leave plan is fully approved.</div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="eligible_for_parental_leave" value="1" id="eligibleForUaeParentalLeave" @checked(old('eligible_for_parental_leave', $userModel->eligible_for_parental_leave ?? false)) data-region-control="uae" @disabled($workRegion !== 'uae')>
                            <label class="form-check-label" for="eligibleForUaeParentalLeave">Eligible for UAE parental leave</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="eligible_for_bereavement_spouse_leave" value="1" id="eligibleForBereavementSpouseLeave" @checked(old('eligible_for_bereavement_spouse_leave', $userModel->eligible_for_bereavement_spouse_leave ?? false)) data-region-control="uae" @disabled($workRegion !== 'uae')>
                            <label class="form-check-label" for="eligibleForBereavementSpouseLeave">Eligible for spouse bereavement leave</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="eligible_for_bereavement_immediate_family_leave" value="1" id="eligibleForBereavementImmediateFamilyLeave" @checked(old('eligible_for_bereavement_immediate_family_leave', $userModel->eligible_for_bereavement_immediate_family_leave ?? false)) data-region-control="uae" @disabled($workRegion !== 'uae')>
                            <label class="form-check-label" for="eligibleForBereavementImmediateFamilyLeave">Eligible for immediate-family bereavement leave</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 {{ $workRegion === 'ph' ? '' : 'd-none' }}" data-region-panel="ph">
            <div class="p-3 bg-body-tertiary border rounded-3">
                <div class="fw-semibold mb-1">Philippines statutory leave eligibility</div>
                <div class="form-text mb-3">HR-attested eligibility controls for event-based statutory leaves. One-shot eligibility is automatically unticked after the related leave plan is fully approved.</div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="eligible_for_maternity_leave" value="1" id="eligibleForMaternityLeave" @checked(old('eligible_for_maternity_leave', $userModel->eligible_for_maternity_leave ?? false)) data-region-control="ph" @disabled($workRegion !== 'ph')>
                            <label class="form-check-label" for="eligibleForMaternityLeave">Eligible for maternity leave</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="eligible_for_paternity_leave" value="1" id="eligibleForPaternityLeave" @checked(old('eligible_for_paternity_leave', $userModel->eligible_for_paternity_leave ?? false)) data-region-control="ph" @disabled($workRegion !== 'ph')>
                            <label class="form-check-label" for="eligibleForPaternityLeave">Eligible for paternity leave</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="eligible_for_parental_leave" value="1" id="eligibleForPhParentalLeave" @checked(old('eligible_for_parental_leave', $userModel->eligible_for_parental_leave ?? false)) data-region-control="ph" @disabled($workRegion !== 'ph')>
                            <label class="form-check-label" for="eligibleForPhParentalLeave">Eligible for parental leave</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="eligible_for_vawc_leave" value="1" id="eligibleForVawcLeave" @checked(old('eligible_for_vawc_leave', $userModel->eligible_for_vawc_leave ?? false)) data-region-control="ph" @disabled($workRegion !== 'ph')>
                            <label class="form-check-label" for="eligibleForVawcLeave">Eligible for VAWC leave</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="eligible_for_special_women_leave" value="1" id="eligibleForSpecialWomenLeave" @checked(old('eligible_for_special_women_leave', $userModel->eligible_for_special_women_leave ?? false)) data-region-control="ph" @disabled($workRegion !== 'ph')>
                            <label class="form-check-label" for="eligibleForSpecialWomenLeave">Eligible for special leave for women</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_solo_parent" value="1" id="isSoloParent" @checked(old('is_solo_parent', $userModel->is_solo_parent ?? false)) data-region-control="ph" @disabled($workRegion !== 'ph')>
                            <label class="form-check-label" for="isSoloParent">Qualified solo parent</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @if($canEditAnnualLeaveOverride)
            <div class="col-md-4">
                <label class="form-label" for="annual_leave_allowance_days">Current-year base annual leave override</label>
                <input class="form-control @error('annual_leave_allowance_days') is-invalid @enderror" id="annual_leave_allowance_days" name="annual_leave_allowance_days" type="number" min="0" step="0.5" value="{{ old('annual_leave_allowance_days', $userModel->annual_leave_allowance_days) }}" placeholder="Use regional default">
                <div class="form-text">Optional base L100 allowance for a special current-year entitlement. Use Annual Carry-Overs for unused prior-year days; blank uses the regional default.</div>
                @error('annual_leave_allowance_days')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        @endif
        @if($isSuperAdmin)
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
        @endif
        <div class="col-md-6 d-flex align-items-end"><div class="form-check"><input type="hidden" name="is_active" value="0"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="active" @checked(old('is_active', $userModel->is_active ?? true))><label class="form-check-label" for="active">Active</label></div></div>
    </div>
    @if($isSuperAdmin && $userModel->exists && $userModel->role === 'hod')
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
                        <div class="col-lg-6">
                            <label class="form-label" for="hodVisibilityExclusionIds">Do not show this employee to this HOD</label>
                            @if($hasVisibilityBlockedCandidates)
                                <div class="alert alert-info py-2 small">
                                    Some employees cannot be hidden from this HOD yet because no other active HOD can still see and approve them. Assign another HOD to the department first.
                                </div>
                            @endif
                            <select class="form-select @error('hod_visibility_exclusion_ids') is-invalid @enderror @error('hod_visibility_exclusion_ids.*') is-invalid @enderror" id="hodVisibilityExclusionIds" name="hod_visibility_exclusion_ids[]" multiple>
                                @foreach($hodExclusionCandidates as $candidate)
                                    @php
                                        $candidateCanBeHidden = in_array((int) $candidate->id, $visibilityExcludableIds, true);
                                    @endphp
                                    <option value="{{ $candidate->id }}" @selected(in_array((int) $candidate->id, $selectedVisibilityExclusions, true)) @disabled(! $candidateCanBeHidden)>
                                        {{ $candidate->name }} - {{ $roleLabels[$candidate->role] ?? $candidate->role }}{{ $candidateCanBeHidden ? '' : ' (assign another HOD first)' }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">Visibility restriction. This also prevents approve, reject, recall, tracker, reminder, and direct detail access. At least one other eligible HOD must remain.</div>
                            @error('hod_visibility_exclusion_ids')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            @error('hod_visibility_exclusion_ids.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif
    <div class="text-end mt-3"><button class="btn btn-primary">Save User</button></div>
</form>
<script>
(() => {
    const source = document.querySelector('[data-region-source]');
    const panels = document.querySelectorAll('[data-region-panel]');
    const controls = document.querySelectorAll('[data-region-control]');

    const regionFor = (value) => {
        const code = (value || '').trim().toUpperCase();

        if (code.startsWith('MEC-PHIL-HR-')) {
            return 'ph';
        }

        if (code.startsWith('MEC-HR-') || code.startsWith('MCE-HR-')) {
            return 'uae';
        }

        return 'unknown';
    };

    const syncRegionPanels = () => {
        const region = regionFor(source?.value);

        panels.forEach((panel) => {
            panel.classList.toggle('d-none', panel.dataset.regionPanel !== region);
        });

        controls.forEach((control) => {
            control.disabled = control.dataset.regionControl !== region;
        });
    };

    source?.addEventListener('input', syncRegionPanels);
    syncRegionPanels();
})();
</script>
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
