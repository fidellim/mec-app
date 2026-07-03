@extends('layouts.app')

@section('content')
@php($isEdit = (bool) $leavePlan)
@php($leaveRegion = $leaveRegion ?? 'uae')
@php($leaveUsageDescription = $leaveRegion === 'ph'
    ? 'Philippines maternity leave uses calendar days. Service incentive and other approved statutory leaves use working leave days, and applicable holidays are excluded from leave usage.'
    : 'UAE sick and maternity leave use calendar days. Annual, parental, and bereavement leave use working leave days, and applicable holidays are excluded from leave usage.')
@php($leaveBalanceDescription = $leaveRegion === 'ph'
    ? 'Philippines leave balances show available statutory entitlements only. Service incentive leave requires one year of service; maternity, paternity, parental, VAWC, and special leave for women require HR eligibility approval.'
    : 'UAE leave balances reset every January 1. Sick and maternity balances show full-pay allowance first; parental and bereavement leave require HR eligibility approval, and bereavement is tracked by relationship.')
@php($supportingDocumentNotes = [
    'L110' => 'Please add a link to your medical certificate in the Reason field.',
    'L160' => 'Please add a link to your medical certificate, birth certificate, or hospital notification in the Reason field.',
    'L170' => 'Please add a link to the birth certificate or hospital birth notification in the Reason field.',
    'L180' => 'Please add a link to the death certificate in the Reason field.',
    'L210' => 'Please add a link to the birth certificate or hospital birth notification in the Reason field.',
    'L220' => 'Please add a link to the VAWC case certification in the Reason field.',
    'L230' => 'Please add a link to the medical certificate for the gynecological surgery in the Reason field.',
])
@php($bereavementRelationshipOptions = collect(\App\Models\LeavePlan::bereavementRelationshipOptions())->filter(fn ($label, $relationship) => app(\App\Services\LeaveEntitlementService::class)->userIsEligibleForBereavementRelationship(auth()->user(), $relationship))->all())
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
            <div class="small text-muted">{{ $leaveUsageDescription }}</div>
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
                    <div class="alert alert-info border mt-3 mb-0 d-none" data-supporting-document-note role="status">
                        <div class="fw-semibold">Supporting document needed</div>
                        <div data-supporting-document-message></div>
                    </div>
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
                <div class="col-md-6 d-none" data-bereavement-relationship-field>
                    <label class="form-label" for="bereavement_relationship">Relationship to deceased</label>
                    <select id="bereavement_relationship" class="form-select @error('bereavement_relationship') is-invalid @enderror" name="bereavement_relationship">
                        <option value="">Select relationship</option>
                        @foreach($bereavementRelationshipOptions as $value => $label)
                            <option value="{{ $value }}" @selected(old('bereavement_relationship', $leavePlan?->bereavement_relationship) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">UAE bereavement requires HR eligibility approval and is tracked by relationship for the calendar year.</div>
                    @error('bereavement_relationship')<div class="invalid-feedback">{{ $message }}</div>@enderror
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
@include('shared.leave_balance_cards', [
    'leaveBalances' => $leaveBalances,
    'class' => 'mt-3 mb-4',
    'description' => $leaveBalanceDescription,
])
@if(! empty($availabilityCalendar))
    <div class="mt-3" data-availability-calendar-shell>
        @include('shared.leave_plan_calendar', array_merge($availabilityCalendar, [
            'calendarTitle' => 'Department leave availability',
            'calendarDescription' => 'Shows submitted, approved, and cancellation-requested leave in your department. Your selected dates are highlighted for comparison.',
            'calendarReadonly' => true,
            'calendarInteractiveRange' => true,
            'calendarVariant' => 'availability',
        ]))
    </div>
@endif
<script>
(() => {
    const startDate = document.getElementById('start_date');
    const endDate = document.getElementById('end_date');
    const durationType = document.getElementById('duration_type');
    const halfDayPeriod = document.getElementById('half_day_period');
    const attendanceCode = document.getElementById('attendance_code');
    const supportingDocumentNote = document.querySelector('[data-supporting-document-note]');
    const supportingDocumentMessage = document.querySelector('[data-supporting-document-message]');
    const bereavementRelationshipField = document.querySelector('[data-bereavement-relationship-field]');
    const bereavementRelationship = document.getElementById('bereavement_relationship');
    const availabilityCalendarShell = document.querySelector('[data-availability-calendar-shell]');
    const supportingDocumentNotes = @json($supportingDocumentNotes);
    let availabilityCalendarChangeTimer = null;

    const getAvailabilityCalendar = () => availabilityCalendarShell?.querySelector('[data-leave-plan-availability-calendar]');

    const syncSupportingDocumentNote = () => {
        if (!supportingDocumentNote || !supportingDocumentMessage || !attendanceCode) {
            return;
        }

        const note = supportingDocumentNotes[attendanceCode.value] || '';
        supportingDocumentMessage.textContent = note;
        supportingDocumentNote.classList.toggle('d-none', !note);
    };

    const syncBereavementRelationshipField = () => {
        if (!bereavementRelationshipField || !bereavementRelationship || !attendanceCode) {
            return;
        }

        const isBereavement = attendanceCode.value === 'L180';
        bereavementRelationshipField.classList.toggle('d-none', !isBereavement);

        if (!isBereavement) {
            bereavementRelationship.value = '';
            bereavementRelationship.tomselect?.clear?.();
        }
    };

    const syncAvailabilityCalendar = () => {
        const availabilityCalendar = getAvailabilityCalendar();

        if (!availabilityCalendar || !startDate?.value || !endDate?.value) {
            availabilityCalendar?.querySelectorAll('[data-calendar-date]').forEach((day) => {
                day.classList.remove('leave-calendar-day-selected');
            });
            return;
        }

        const rangeStart = startDate.value <= endDate.value ? startDate.value : endDate.value;
        const rangeEnd = endDate.value >= startDate.value ? endDate.value : startDate.value;

        availabilityCalendar.querySelectorAll('[data-calendar-date]').forEach((day) => {
            const dayDate = day.dataset.calendarDate;
            day.classList.toggle('leave-calendar-day-selected', dayDate >= rangeStart && dayDate <= rangeEnd);
        });
    };

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

        syncAvailabilityCalendar();
    };

    const syncHalfDayControls = () => {
        const isHalfDay = durationType?.value === 'half_day';

        if (halfDayPeriod) {
            halfDayPeriod.required = isHalfDay;
            halfDayPeriod.disabled = !isHalfDay;

            if (halfDayPeriod.tomselect) {
                isHalfDay ? halfDayPeriod.tomselect.enable() : halfDayPeriod.tomselect.disable();
            }

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
    attendanceCode?.addEventListener('change', () => {
        syncSupportingDocumentNote();
        syncBereavementRelationshipField();
    });

    const withCalendarFragment = (url) => {
        const nextUrl = new URL(url, window.location.href);
        nextUrl.searchParams.set('calendar_fragment', 'availability');

        return nextUrl;
    };

    const loadAvailabilityCalendar = async (url) => {
        if (!availabilityCalendarShell || !window.fetch) {
            window.location.href = url;
            return;
        }

        availabilityCalendarShell.classList.add('opacity-75');

        try {
            const response = await fetch(withCalendarFragment(url), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'text/html',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error(`Calendar request failed with ${response.status}`);
            }

            availabilityCalendarShell.innerHTML = await response.text();
            syncAvailabilityCalendar();
            window.initializeSearchableSelects?.(availabilityCalendarShell);
        } catch (error) {
            window.location.href = url;
        } finally {
            availabilityCalendarShell.classList.remove('opacity-75');
        }
    };

    const syncCalendarMonthValue = (form) => {
        const monthInput = form.querySelector('[data-calendar-month-value]');
        const monthSelect = form.querySelector('[data-calendar-month]');
        const yearSelect = form.querySelector('[data-calendar-year]');

        if (monthInput && monthSelect && yearSelect) {
            monthInput.value = `${yearSelect.value}-${monthSelect.value}`;
        }
    };

    const calendarFormUrl = (form) => {
        syncCalendarMonthValue(form);

        const targetUrl = new URL(form.action || window.location.href, window.location.href);
        const targetParams = new URLSearchParams(new FormData(form));
        targetUrl.search = targetParams.toString();

        return targetUrl;
    };

    const queueCalendarFormLoad = (form) => {
        window.clearTimeout(availabilityCalendarChangeTimer);
        availabilityCalendarChangeTimer = window.setTimeout(() => {
            loadAvailabilityCalendar(calendarFormUrl(form));
        }, 180);
    };

    availabilityCalendarShell?.addEventListener('click', (event) => {
        const link = event.target.closest('.leave-calendar-nav a, [data-calendar-auto-submit] a');

        if (!link) {
            return;
        }

        event.preventDefault();
        loadAvailabilityCalendar(link.href);
    });

    availabilityCalendarShell?.addEventListener('change', (event) => {
        const form = event.target.closest('[data-calendar-auto-submit]');

        if (!form || !event.target.matches('[data-calendar-month], [data-calendar-year]')) {
            return;
        }

        queueCalendarFormLoad(form);
    });

    availabilityCalendarShell?.addEventListener('submit', (event) => {
        const form = event.target.closest('[data-calendar-month-selector]');

        if (!form) {
            return;
        }

        event.preventDefault();
        loadAvailabilityCalendar(calendarFormUrl(form));
    });

    syncHalfDayControls();
    syncAvailabilityCalendar();
    syncSupportingDocumentNote();
    syncBereavementRelationshipField();
})();
</script>
@endsection
