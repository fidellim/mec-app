@extends('layouts.app')

@section('content')
@php
    $roleLabels = config('roles.labels');
    $genderLabels = ['male' => 'Male', 'female' => 'Female'];
    $maritalStatusLabels = ['single' => 'Single', 'married' => 'Married', 'widowed' => 'Widowed', 'separated' => 'Separated'];
    $assignedDepartments = $userModel->primaryDepartments->merge($userModel->managedDepartments)->unique('id')->values();
    $workRegion = is_string($userModel->employee_code) && str_starts_with($userModel->employee_code, 'MEC-PHIL-HR-')
        ? 'ph'
        : (is_string($userModel->employee_code) && (str_starts_with($userModel->employee_code, 'MEC-HR-') || str_starts_with($userModel->employee_code, 'MCE-HR-'))
            ? 'uae'
            : null);
    $workRegionLabel = $workRegion === 'ph' ? 'Philippines' : ($workRegion === 'uae' ? 'UAE' : 'Not determined');
    $workRegionBadgeClass = $workRegion === 'ph' ? 'text-bg-info' : ($workRegion === 'uae' ? 'text-bg-primary' : 'text-bg-secondary');
    $annualLeaveOverride = $userModel->annual_leave_allowance_days !== null
        ? rtrim(rtrim(number_format((float) $userModel->annual_leave_allowance_days, 2), '0'), '.').' days'
        : 'Regional default';
    $employmentDetails = [
        'Job Title' => $userModel->job_title ?: '-',
        'Department' => $userModel->department?->name ?: '-',
        'Joining Date' => $userModel->joining_date?->format('M d, Y') ?: '-',
        'Gender' => $genderLabels[$userModel->gender] ?? '-',
        'Marital Status' => $maritalStatusLabels[$userModel->marital_status] ?? '-',
        'Initials' => $userModel->initials ?: '-',
    ];
    if ($workRegion !== 'ph') {
        $employmentDetails['Current-Year Annual Leave Override'] = $annualLeaveOverride;
    }
@endphp
<div class="section-header">
    <div>
        <h1 class="h3 page-heading mb-1">User Profile</h1>
        <div class="text-muted">{{ $userModel->name }}</div>
    </div>
    <div class="action-group">
        <a class="btn btn-outline-secondary" href="{{ route('manage.users.index') }}">Back to Users</a>
        @if(auth()->user()->role === 'super_admin' || (auth()->user()->role === 'admin' && in_array($userModel->role, ['hod', 'employee'], true)))
            <a class="btn btn-primary" href="{{ route('manage.users.edit', $userModel) }}">Edit</a>
        @endif
    </div>
</div>

<div class="content-card mb-4">
    <div class="content-card-header">
        <div class="d-flex flex-column flex-lg-row gap-3 justify-content-between align-items-start">
            <div>
                <h2 class="h5 mb-1">Profile details</h2>
                <div class="small text-muted">Read-only account identity and regional context.</div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <span class="badge {{ $userModel->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $userModel->is_active ? 'Active' : 'Inactive' }}</span>
                <span class="badge {{ $workRegionBadgeClass }}">{{ $workRegionLabel }}</span>
            </div>
        </div>
    </div>
    <div class="content-card-body">
        <div class="row g-3">
            <div class="col-12 col-lg-4">
                <div class="meta-label">Name</div>
                <div class="meta-value">{{ $userModel->name }}</div>
            </div>
            <div class="col-12 col-lg-4">
                <div class="meta-label">Email</div>
                <div class="meta-value">{{ $userModel->email }}</div>
            </div>
            <div class="col-12 col-lg-4">
                <div class="meta-label">Role</div>
                <div><span class="badge bg-body-secondary border text-body">{{ $roleLabels[$userModel->role] ?? $userModel->role }}</span></div>
            </div>
            <div class="col-12 col-lg-4">
                <div class="meta-label">Employee Number</div>
                <div class="meta-value">{{ $userModel->employee_code ?: '-' }}</div>
            </div>
            <div class="col-12 col-lg-4">
                <div class="meta-label">Work Region</div>
                <div class="meta-value">{{ $workRegionLabel }}</div>
            </div>
        </div>
    </div>
</div>

<div class="content-card mb-4">
    <div class="content-card-header">
        <h2 class="h5 mb-1">Employment details</h2>
        <div class="small text-muted">Role, department, and profile attributes used by leave eligibility.</div>
    </div>
    <div class="content-card-body">
        <div class="row g-3">
            @foreach($employmentDetails as $label => $value)
                <div class="col-12 col-md-6 col-xl-4">
                    <div class="meta-label">{{ $label }}</div>
                    <div class="meta-value">{{ $value }}</div>
                </div>
            @endforeach
            @if($assignedDepartments->isNotEmpty())
                <div class="col-12">
                    <div class="meta-label">Head of Department For</div>
                    <div class="meta-value">{{ $assignedDepartments->pluck('name')->join(', ') }}</div>
                </div>
            @endif
        </div>
    </div>
</div>

<div class="content-card mb-4">
    <div class="content-card-header">
        @if($workRegion === 'uae')
            <h2 class="h5 mb-1">UAE leave eligibility</h2>
            <div class="small text-muted">UAE profile controls that affect employee leave visibility.</div>
        @elseif($workRegion === 'ph')
            <h2 class="h5 mb-1">Philippines statutory leave eligibility</h2>
            <div class="small text-muted">HR-attested statutory leave controls for Philippines employees.</div>
        @else
            <h2 class="h5 mb-1">Leave eligibility region</h2>
            <div class="small text-muted">Employee number prefix determines which regional leave controls apply.</div>
        @endif
    </div>
    <div class="content-card-body">
        @if($workRegion === 'uae')
            <div class="d-flex flex-wrap gap-2">
                <span class="badge text-wrap text-start lh-sm {{ $userModel->eligible_for_parental_leave ? 'text-bg-success' : 'text-bg-secondary' }}">
                    Parental: {{ $userModel->eligible_for_parental_leave ? 'Eligible' : 'Not eligible' }}
                </span>
            </div>
        @elseif($workRegion === 'ph')
            <div class="d-flex flex-wrap gap-2">
                @foreach([
                    'Maternity' => $userModel->eligible_for_maternity_leave,
                    'Paternity' => $userModel->eligible_for_paternity_leave,
                    'Parental' => $userModel->eligible_for_parental_leave,
                    'VAWC' => $userModel->eligible_for_vawc_leave,
                    'Special Leave for Women' => $userModel->eligible_for_special_women_leave,
                    'Solo parent' => $userModel->is_solo_parent,
                ] as $label => $enabled)
                    <span class="badge text-wrap text-start lh-sm {{ $enabled ? 'text-bg-success' : 'text-bg-secondary' }}">
                        {{ $label }}: {{ $enabled ? ($label === 'Solo parent' ? 'Yes' : 'Eligible') : ($label === 'Solo parent' ? 'No' : 'Not eligible') }}
                    </span>
                @endforeach
            </div>
        @else
            <div class="text-muted">Enter a UAE or Philippines employee number to determine region-specific eligibility.</div>
        @endif
    </div>
</div>

@include('shared.leave_balance_cards', [
    'leaveBalances' => $leaveBalances,
    'title' => 'Eligible leave balances',
    'description' => 'Current calendar year eligible balances for this user.',
])
@endsection
