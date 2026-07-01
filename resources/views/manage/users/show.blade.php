@extends('layouts.app')

@section('content')
@php($roleLabels = config('roles.labels'))
@php($genderLabels = ['male' => 'Male', 'female' => 'Female'])
@php($maritalStatusLabels = ['single' => 'Single', 'married' => 'Married', 'widowed' => 'Widowed', 'separated' => 'Separated'])
@php($assignedDepartments = $userModel->primaryDepartments->merge($userModel->managedDepartments)->unique('id')->values())
<div class="section-header">
    <div>
        <h1 class="h3 page-heading mb-1">User Profile</h1>
        <div class="text-muted">{{ $userModel->name }}</div>
    </div>
    <div class="action-group">
        <a class="btn btn-outline-secondary" href="{{ route('manage.users.index') }}">Back to Users</a>
        @if(auth()->user()->role === 'super_admin')
            <a class="btn btn-primary" href="{{ route('manage.users.edit', $userModel) }}">Edit</a>
        @endif
    </div>
</div>

<div class="content-card mb-4">
    <div class="content-card-header">
        <h2 class="h5 mb-1">Profile details</h2>
        <div class="small text-muted">Read-only account and employment information.</div>
    </div>
    <div class="content-card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="meta-label">Name</div>
                <div class="meta-value">{{ $userModel->name }}</div>
            </div>
            <div class="col-md-4">
                <div class="meta-label">Email</div>
                <div class="meta-value">{{ $userModel->email }}</div>
            </div>
            <div class="col-md-4">
                <div class="meta-label">Status</div>
                <div><span class="badge {{ $userModel->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $userModel->is_active ? 'Active' : 'Inactive' }}</span></div>
            </div>
            <div class="col-md-4">
                <div class="meta-label">Employee Number</div>
                <div class="meta-value">{{ $userModel->employee_code ?: '-' }}</div>
            </div>
            <div class="col-md-4">
                <div class="meta-label">Initials</div>
                <div class="meta-value">{{ $userModel->initials ?: '-' }}</div>
            </div>
            <div class="col-md-4">
                <div class="meta-label">Job Title</div>
                <div class="meta-value">{{ $userModel->job_title ?: '-' }}</div>
            </div>
            <div class="col-md-4">
                <div class="meta-label">Gender</div>
                <div class="meta-value">{{ $genderLabels[$userModel->gender] ?? '-' }}</div>
            </div>
            <div class="col-md-4">
                <div class="meta-label">Joining Date</div>
                <div class="meta-value">{{ $userModel->joining_date?->format('M d, Y') ?: '-' }}</div>
            </div>
            <div class="col-md-4">
                <div class="meta-label">Marital Status</div>
                <div class="meta-value">{{ $maritalStatusLabels[$userModel->marital_status] ?? '-' }}</div>
            </div>
            <div class="col-md-4">
                <div class="meta-label">Role</div>
                <div><span class="badge text-bg-light border text-dark">{{ $roleLabels[$userModel->role] ?? $userModel->role }}</span></div>
            </div>
            <div class="col-md-4">
                <div class="meta-label">Department</div>
                <div class="meta-value">{{ $userModel->department?->name ?: '-' }}</div>
            </div>
            <div class="col-md-4">
                <div class="meta-label">Current-Year Annual Leave Override</div>
                <div class="meta-value">{{ $userModel->annual_leave_allowance_days !== null ? rtrim(rtrim(number_format((float) $userModel->annual_leave_allowance_days, 2), '0'), '.').' days' : 'Regional default' }}</div>
            </div>
            @if($assignedDepartments->isNotEmpty())
                <div class="col-12">
                    <div class="meta-label">Head of Department For</div>
                    <div class="meta-value">{{ $assignedDepartments->pluck('name')->join(', ') }}</div>
                </div>
            @endif
        </div>
    </div>
</div>

@include('shared.leave_balance_cards', [
    'leaveBalances' => $leaveBalances,
    'title' => 'Eligible leave balances',
    'description' => 'Current calendar year eligible balances for this user.',
])
@endsection
