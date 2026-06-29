@extends('layouts.app')

@section('content')
@php($isEdit = (bool) $leavePlan)
<div class="section-header">
    <div>
        <h1 class="h3 page-heading mb-1">{{ $isEdit ? 'Edit Leave Plan' : 'Create Leave Plan' }}</h1>
        <div class="text-muted">Save a draft or submit it to your HOD for approval.</div>
    </div>
</div>
<form method="post" action="{{ $isEdit ? route('employee.leave-plans.update', $leavePlan) : route('employee.leave-plans.store') }}">
    @csrf
    @if($isEdit) @method('put') @endif
    <div class="content-card">
        <div class="content-card-header">
            <h2 class="h5 mb-1">Leave details</h2>
            <div class="small text-muted">Leave duration counts working leave days only. Half-day leave is available for a single date only.</div>
        </div>
        <div class="content-card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="attendance_code">Leave type</label>
                    <select id="attendance_code" class="form-select @error('attendance_code') is-invalid @enderror" name="attendance_code" required>
                        <option value="">Select leave type</option>
                        @foreach($attendanceCodes as $code => $label)
                            <option value="{{ $code }}" @selected(old('attendance_code', $leavePlan?->attendance_code) === $code)>{{ $code }} - {{ $label }}</option>
                        @endforeach
                    </select>
                    @error('attendance_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="start_date">Start date</label>
                    <input id="start_date" class="form-control @error('start_date') is-invalid @enderror" type="date" name="start_date" value="{{ old('start_date', $leavePlan?->start_date?->toDateString()) }}" required>
                    @error('start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="end_date">End date</label>
                    <input id="end_date" class="form-control @error('end_date') is-invalid @enderror" type="date" name="end_date" value="{{ old('end_date', $leavePlan?->end_date?->toDateString()) }}" required>
                    @error('end_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="duration_type">Duration</label>
                    <select id="duration_type" class="form-select @error('duration_type') is-invalid @enderror" name="duration_type" required>
                        <option value="full_day" @selected(old('duration_type', $leavePlan?->duration_type ?? 'full_day') === 'full_day')>Full day</option>
                        <option value="half_day" @selected(old('duration_type', $leavePlan?->duration_type) === 'half_day')>Half day</option>
                    </select>
                    @error('duration_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="half_day_period">Half-day period</label>
                    <select id="half_day_period" class="form-select @error('half_day_period') is-invalid @enderror" name="half_day_period">
                        <option value="">Not applicable</option>
                        <option value="morning" @selected(old('half_day_period', $leavePlan?->half_day_period) === 'morning')>Morning</option>
                        <option value="afternoon" @selected(old('half_day_period', $leavePlan?->half_day_period) === 'afternoon')>Afternoon</option>
                    </select>
                    @error('half_day_period')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label class="form-label" for="reason">Reason <span class="text-muted fw-normal">(optional)</span></label>
                    <textarea id="reason" class="form-control @error('reason') is-invalid @enderror" name="reason" rows="4" placeholder="Add context for your HOD.">{{ old('reason', $leavePlan?->reason) }}</textarea>
                    @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
        <div class="sticky-actions d-flex flex-column flex-sm-row gap-2 justify-content-end p-3">
            <a class="btn btn-outline-secondary" href="{{ $isEdit ? route('employee.leave-plans.show', $leavePlan) : route('employee.leave-plans.index') }}">Cancel</a>
            <button type="submit" class="btn btn-outline-secondary" name="submit" value="0">Save Draft</button>
            <button type="submit" class="btn btn-primary" name="submit" value="1" data-confirm="Submit this leave plan for approval?">Submit for Approval</button>
        </div>
    </div>
</form>
<script>
(() => {
    const startDate = document.getElementById('start_date');
    const endDate = document.getElementById('end_date');
    const durationType = document.getElementById('duration_type');
    const halfDayPeriod = document.getElementById('half_day_period');

    const syncDateRules = () => {
        if (!startDate || !endDate) {
            return;
        }

        endDate.min = startDate.value || '';
        window.setDatePickerMin?.(endDate, startDate.value || null);

        if (startDate.value && (!endDate.value || endDate.value < startDate.value)) {
            endDate.value = startDate.value;
            window.syncDatePicker?.(endDate);
        }

        if (durationType?.value === 'half_day' && startDate.value && endDate.value !== startDate.value) {
            endDate.value = startDate.value;
            window.syncDatePicker?.(endDate);
        }
    };

    const syncHalfDayControls = () => {
        const isHalfDay = durationType?.value === 'half_day';

        if (halfDayPeriod) {
            halfDayPeriod.required = isHalfDay;
            halfDayPeriod.disabled = !isHalfDay;

            if (!isHalfDay) {
                halfDayPeriod.value = '';
                halfDayPeriod.tomselect?.clear?.();
            }
        }

        if (endDate) {
            endDate.readOnly = isHalfDay;
            window.setDatePickerReadonly?.(endDate, isHalfDay);
        }

        syncDateRules();
    };

    startDate?.addEventListener('change', syncHalfDayControls);
    endDate?.addEventListener('change', syncDateRules);
    durationType?.addEventListener('change', syncHalfDayControls);
    syncHalfDayControls();
})();
</script>
@endsection
