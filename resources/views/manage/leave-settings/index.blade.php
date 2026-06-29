@extends('layouts.app')

@section('content')
<div class="section-header">
    <div>
        <h1 class="h3 page-heading mb-1">Leave Settings</h1>
        <div class="text-muted">Manage yearly L100 annual leave entitlement rules.</div>
    </div>
</div>

<form class="content-card p-3" method="post" action="{{ route('manage.leave-settings.update') }}">
    @csrf
    @method('patch')
    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label" for="annual_leave_default_days">Default annual leave days</label>
            <input class="form-control @error('annual_leave_default_days') is-invalid @enderror" id="annual_leave_default_days" name="annual_leave_default_days" type="number" min="0" step="0.5" value="{{ old('annual_leave_default_days', $annualLeaveDefault->decimal_value) }}" required>
            <div class="form-text">Applies to L100 Annual Leave unless a user has an override.</div>
            @error('annual_leave_default_days')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="col-md-8">
            <div class="alert alert-info mb-0">
                Annual leave resets every January 1. Unused days expire at year end and do not carry over into the next calendar year.
            </div>
        </div>
    </div>
    <div class="text-end mt-3">
        <button class="btn btn-primary">Save Settings</button>
    </div>
</form>
@endsection
