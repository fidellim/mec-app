@extends('layouts.app')

@section('content')
@php($supportingDocumentNotes = [
    'L110' => 'Medical certificate link can be added in Reason when available.',
    'L160' => 'Medical certificate, birth certificate, or hospital notification link can be added in Reason.',
    'L170' => 'Birth certificate or hospital notification link can be added in Reason.',
    'L180' => 'Death certificate reference can be added in Reason.',
    'L210' => 'Birth certificate or hospital notification link can be added in Reason.',
    'L220' => 'VAWC case certification reference can be added in Reason.',
    'L230' => 'Medical certificate reference can be added in Reason.',
])
<div class="section-header">
    <div>
        <h1 class="h3 page-heading mb-1">Add Approved Leave</h1>
        <div class="text-muted">Record leave that was already approved outside the portal.</div>
    </div>
    <div class="action-group">
        <a class="btn btn-outline-secondary" href="{{ route('admin.leave-plans.index') }}">All Leave Plans</a>
        <a class="btn btn-outline-primary" href="{{ route('admin.leave-plans.import') }}">Import CSV</a>
    </div>
</div>

@error('admin_approved_leave')
    <div class="alert alert-danger">{{ $message }}</div>
@enderror

<form method="post" action="{{ route('admin.leave-plans.store') }}">
    @csrf
    <div class="content-card">
        <div class="content-card-header">
            <h2 class="h5 mb-1">Approved leave details</h2>
            <div class="small text-muted">The selected employee's current department, eligibility, balance, and overlapping leave are checked before saving.</div>
        </div>
        <div class="content-card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="employee_id">Employee or HOD</label>
                    <select id="employee_id" class="form-select @error('employee_id') is-invalid @enderror" name="employee_id" required>
                        <option value="">Select employee or HOD</option>
                        @foreach($employees as $employee)
                            <option value="{{ $employee->id }}" @selected((int) old('employee_id') === (int) $employee->id)>
                                {{ $employee->name }} - {{ $employee->employee_code }}{{ $employee->department ? ' - '.$employee->department->name : '' }}
                            </option>
                        @endforeach
                    </select>
                    @error('employee_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="approved_at">Original approval date</label>
                    <input id="approved_at" class="form-control @error('approved_at') is-invalid @enderror" type="date" name="approved_at" value="{{ old('approved_at') }}" required>
                    @error('approved_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="attendance_code">Leave type</label>
                    <select id="attendance_code" class="form-select @error('attendance_code') is-invalid @enderror" name="attendance_code" required>
                        <option value="">Select leave type</option>
                        @foreach($attendanceCodes as $code => $label)
                            <option value="{{ $code }}" @selected(old('attendance_code') === $code)>{{ $code }} - {{ $label }}</option>
                        @endforeach
                    </select>
                    @error('attendance_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="alert alert-info border mt-3 mb-0 d-none" data-supporting-document-note role="status">
                        <div class="fw-semibold">Reference note</div>
                        <div data-supporting-document-message></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="start_date">Start date</label>
                    <input id="start_date" class="form-control @error('start_date') is-invalid @enderror" type="date" name="start_date" value="{{ old('start_date') }}" required>
                    @error('start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="end_date">End date</label>
                    <input id="end_date" class="form-control @error('end_date') is-invalid @enderror" type="date" name="end_date" value="{{ old('end_date') }}" required>
                    @error('end_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="duration_type">Duration</label>
                    <select id="duration_type" class="form-select @error('duration_type') is-invalid @enderror" name="duration_type" required>
                        <option value="full_day" @selected(old('duration_type', 'full_day') === 'full_day')>Full day</option>
                        <option value="half_day" @selected(old('duration_type') === 'half_day')>Half day</option>
                    </select>
                    @error('duration_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="half_day_period">Half-day period</label>
                    <select id="half_day_period" class="form-select @error('half_day_period') is-invalid @enderror" name="half_day_period">
                        <option value="">Not applicable</option>
                        <option value="morning" @selected(old('half_day_period') === 'morning')>Morning</option>
                        <option value="afternoon" @selected(old('half_day_period') === 'afternoon')>Afternoon</option>
                    </select>
                    @error('half_day_period')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4 d-none" data-bereavement-relationship-field>
                    <label class="form-label" for="bereavement_relationship">Bereavement relationship</label>
                    <select id="bereavement_relationship" class="form-select @error('bereavement_relationship') is-invalid @enderror" name="bereavement_relationship">
                        <option value="">Select relationship</option>
                        @foreach($bereavementRelationships as $value => $label)
                            <option value="{{ $value }}" @selected(old('bereavement_relationship') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('bereavement_relationship')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label class="form-label" for="reason">Reason or source reference <span class="text-muted fw-normal">(optional)</span></label>
                    <textarea id="reason" class="form-control @error('reason') is-invalid @enderror" name="reason" rows="4" placeholder="Example: Historical approved leave from HR records.">{{ old('reason') }}</textarea>
                    @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
        <div class="sticky-actions d-flex flex-column flex-sm-row gap-2 justify-content-end p-3">
            <a class="btn btn-outline-secondary" href="{{ route('admin.leave-plans.index') }}">Cancel</a>
            <button class="btn btn-primary" data-confirm="Add this already-approved leave record?">Add Approved Leave</button>
        </div>
    </div>
</form>

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
    const supportingDocumentNotes = @json($supportingDocumentNotes);

    const syncDateRules = () => {
        if (!startDate || !endDate) return;

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
            isHalfDay ? halfDayPeriod.tomselect?.enable?.() : halfDayPeriod.tomselect?.disable?.();

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

    const syncSupportingDocumentNote = () => {
        const note = supportingDocumentNotes[attendanceCode?.value] || '';
        if (supportingDocumentMessage) supportingDocumentMessage.textContent = note;
        supportingDocumentNote?.classList.toggle('d-none', !note);
    };

    const syncBereavementRelationshipField = () => {
        const isBereavement = attendanceCode?.value === 'L180';
        bereavementRelationshipField?.classList.toggle('d-none', !isBereavement);

        if (!isBereavement && bereavementRelationship) {
            bereavementRelationship.value = '';
            bereavementRelationship.tomselect?.clear?.();
        }
    };

    startDate?.addEventListener('change', syncHalfDayControls);
    endDate?.addEventListener('change', syncDateRules);
    durationType?.addEventListener('change', syncHalfDayControls);
    attendanceCode?.addEventListener('change', () => {
        syncSupportingDocumentNote();
        syncBereavementRelationshipField();
    });

    syncHalfDayControls();
    syncSupportingDocumentNote();
    syncBereavementRelationshipField();
})();
</script>
@endsection
