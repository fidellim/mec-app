@extends('layouts.app')

@section('content')
@php
    $directorId = old('director_user_id', $settings[\App\Models\LeavePlanApproverSetting::DIRECTOR]->user_id ?? null);
    $hrUaeId = old('hr_uae_user_id', $settings[\App\Models\LeavePlanApproverSetting::HR_UAE]->user_id ?? null);
    $hrPhId = old('hr_ph_user_id', $settings[\App\Models\LeavePlanApproverSetting::HR_PH]->user_id ?? null);
@endphp
<div class="section-header">
    <div>
        <h1 class="h3 page-heading mb-1">Leave Plan Approvers</h1>
        <div class="text-muted">Assign Director and regional HR reviewers for staged leave-plan approvals.</div>
    </div>
</div>

<form class="content-card p-3" method="post" action="{{ route('manage.leave-plan-approvers.update') }}">
    @csrf
    @method('patch')
    <div class="row g-3">
        <div class="col-lg-4">
            <label class="form-label" for="director_user_id">Director approver</label>
            <select class="form-select @error('director_user_id') is-invalid @enderror" id="director_user_id" name="director_user_id">
                <option value="">Not configured</option>
                @foreach($users as $user)
                    <option value="{{ $user->id }}" @selected((int) $directorId === (int) $user->id)>{{ $user->name }} - {{ config('roles.labels.'.$user->role, $user->role) }}</option>
                @endforeach
            </select>
            <div class="form-text">Reviews after Head of Department approval.</div>
            @error('director_user_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-lg-4">
            <label class="form-label" for="hr_uae_user_id">UAE HR approver</label>
            <select class="form-select @error('hr_uae_user_id') is-invalid @enderror" id="hr_uae_user_id" name="hr_uae_user_id">
                <option value="">Not configured</option>
                @foreach($users as $user)
                    <option value="{{ $user->id }}" @selected((int) $hrUaeId === (int) $user->id)>{{ $user->name }} - {{ config('roles.labels.'.$user->role, $user->role) }}</option>
                @endforeach
            </select>
            <div class="form-text">Final reviewer for MEC-HR and MCE-HR employee numbers.</div>
            @error('hr_uae_user_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-lg-4">
            <label class="form-label" for="hr_ph_user_id">Philippines HR approver</label>
            <select class="form-select @error('hr_ph_user_id') is-invalid @enderror" id="hr_ph_user_id" name="hr_ph_user_id">
                <option value="">Not configured</option>
                @foreach($users as $user)
                    <option value="{{ $user->id }}" @selected((int) $hrPhId === (int) $user->id)>{{ $user->name }} - {{ config('roles.labels.'.$user->role, $user->role) }}</option>
                @endforeach
            </select>
            <div class="form-text">Final reviewer for MEC-PHIL-HR employee numbers.</div>
            @error('hr_ph_user_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
    <div class="text-end mt-3">
        <button class="btn btn-primary">Save Approvers</button>
    </div>
</form>
@endsection
