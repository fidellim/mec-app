@extends('layouts.app')

@section('content')
@php($isEdit = (bool) $timesheet)
<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-start gap-3 mb-4">
    <div>
        <h1 class="h3 page-heading mb-1">{{ $isEdit ? 'Edit Timesheet' : 'Create Weekly Timesheet' }}</h1>
        <div class="text-muted">Record regular and overtime hours by attendance code and project/job number.</div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <span class="badge text-bg-primary fs-6 px-3 py-2" id="weekRegularTotal">Week RT 0.00</span>
        <span class="badge text-bg-warning fs-6 px-3 py-2" id="weekOvertimeTotal">Week OT 0.00</span>
        <span class="badge text-bg-secondary fs-6 px-3 py-2" id="weekGrandTotal">Week Total 0.00</span>
    </div>
</div>
@if($timesheet?->status === 'rejected')
    <div class="alert alert-warning">
        <div class="fw-semibold mb-1">Rejected timesheet</div>
        <div>{{ $timesheet->rejection_comment }}</div>
    </div>
@endif
<form method="post" action="{{ $isEdit ? route('employee.timesheets.update', $timesheet) : route('employee.timesheets.store') }}">
    @csrf
    @if($isEdit) @method('put') @endif
    <div class="toolbar-card p-3 mb-3">
        <div class="row g-3 align-items-end">
            <div class="col-lg-5">
                <label class="form-label fw-semibold">Weekly period</label>
                <select class="form-select" name="timesheet_period_id" required>
                    @foreach($periods as $period)
                        <option value="{{ $period->id }}" @selected(old('timesheet_period_id', $timesheet?->timesheet_period_id) == $period->id)>
                            Week {{ $period->week_number }}, {{ $period->year }}: {{ $period->start_date->toDateString() }} to {{ $period->end_date->toDateString() }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-7">
                <div class="small text-muted">
                    Use Add project to split a day across multiple projects. Project/job number is optional for leave codes. Leave codes accept regular hours only. Remarks are optional.
                </div>
            </div>
        </div>
    </div>
    <div class="content-card overflow-hidden">
        <div class="content-card-header d-flex flex-column flex-lg-row justify-content-between gap-2">
            <div>
                <div class="fw-semibold">Daily entries</div>
                <div class="small text-muted">Each row is saved against the selected week and locked after submission.</div>
            </div>
            <div class="d-flex gap-2 small text-muted">
                <span>RT: Regular time</span>
                <span>OT: Overtime</span>
            </div>
        </div>
        <div class="table-responsive content-card-body p-0">
            <table class="table timesheet-entry-table" id="timesheet-entry-table">
                <thead><tr><th>Date</th><th>Day</th><th>Day Total</th><th>Attendance Code</th><th>Project/Job</th><th>Regular</th><th>Overtime</th><th>Remarks</th><th></th></tr></thead>
                <tbody>
                @foreach($entries as $i => $entry)
                    @php($row = is_array($entry) ? (object) $entry : $entry)
                    @php($workDate = $row->work_date instanceof \Carbon\CarbonInterface ? $row->work_date->toDateString() : $row->work_date)
                    @php($dayName = \Carbon\Carbon::parse($workDate)->format('l'))
                    @php($selectedAttendanceCode = old("entries.$i.attendance_code", $row->attendance_code ?? ''))
                    <tr data-entry-row data-work-date="{{ $workDate }}" data-day-name="{{ $dayName }}">
                        <td style="min-width: 145px;">
                            <input type="hidden" name="entries[{{ $i }}][work_date]" value="{{ old("entries.$i.work_date", $workDate) }}" data-field="work_date">
                            <span class="fw-semibold" data-date-label>{{ old("entries.$i.work_date", $workDate) }}</span>
                        </td>
                        <td style="min-width: 110px;">
                            <span class="text-muted" data-day-label>{{ $dayName }}</span>
                        </td>
                        <td style="min-width: 150px;">
                            <span class="badge text-bg-light border text-dark px-3 py-2" data-day-total></span>
                        </td>
                        <td>
                            <select class="form-select attendance-select" name="entries[{{ $i }}][attendance_code]" data-field="attendance_code">
                                <option value="">Select</option>
                                @foreach($attendanceCodes as $code => $label)
                                    <option value="{{ $code }}" @selected($selectedAttendanceCode === $code)>{{ $code }} - {{ $label }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <select class="form-select project-select" name="entries[{{ $i }}][project_id]" data-field="project_id">
                                <option value="">Select</option>
                                @foreach($projects as $project)
                                    <option value="{{ $project->id }}" title="{{ $project->project_name }}" @selected(old("entries.$i.project_id", $row->project_id) == $project->id)>{{ $project->project_code }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td style="width: 110px;"><input class="form-control" type="number" min="0" max="24" step="0.25" name="entries[{{ $i }}][regular_hours]" data-field="regular_hours" value="{{ old("entries.$i.regular_hours", $row->regular_hours ?? 0) }}"></td>
                        <td style="width: 110px;"><input class="form-control" type="number" min="0" max="24" step="0.25" name="entries[{{ $i }}][overtime_hours]" data-field="overtime_hours" value="{{ old("entries.$i.overtime_hours", $row->overtime_hours ?? 0) }}"></td>
                        <td class="remarks-cell"><input class="form-control" name="entries[{{ $i }}][remarks]" data-field="remarks" value="{{ old("entries.$i.remarks", $row->remarks) }}"></td>
                        <td>
                            <div class="d-flex gap-1 justify-content-end">
                                <button type="button" class="btn btn-sm btn-outline-primary" data-add-entry data-bs-toggle="tooltip" data-bs-title="Add another project row for this specific day">Add project</button>
                                <button type="button" class="btn btn-sm btn-outline-danger" data-remove-entry>Remove</button>
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="sticky-actions d-flex flex-column flex-sm-row gap-2 justify-content-end p-3">
            <button class="btn btn-outline-secondary" name="submit" value="0">Save Draft</button>
            <button class="btn btn-primary" name="submit" value="1" data-confirm="Submit this timesheet for approval?">Submit for Approval</button>
        </div>
    </div>
</form>
<script>
(() => {
    const table = document.getElementById('timesheet-entry-table');
    let nextIndex = {{ count($entries) }};
    const leaveAttendanceCodes = @json(config('timesheet.leave_attendance_codes', []));
    const projectOptionalAttendanceCodes = @json(config('timesheet.project_optional_attendance_codes', config('timesheet.leave_attendance_codes', [])));
    const isLeaveAttendanceCode = (value) => leaveAttendanceCodes.includes(value);
    const isProjectOptionalAttendanceCode = (value) => projectOptionalAttendanceCodes.includes(value);

    const renameRowFields = (row, index) => {
        row.querySelectorAll('[data-field]').forEach((field) => {
            field.name = `entries[${index}][${field.dataset.field}]`;
        });
    };

    const calculateDayTotals = (workDate) => {
        const rows = Array.from(table.querySelectorAll(`[data-entry-row][data-work-date="${workDate}"]`));
        const regular = rows.reduce((sum, row) => sum + (parseFloat(row.querySelector('[data-field="regular_hours"]').value) || 0), 0);
        const overtime = rows.reduce((sum, row) => sum + (parseFloat(row.querySelector('[data-field="overtime_hours"]').value) || 0), 0);

        rows.forEach((row, index) => {
            const total = row.querySelector('[data-day-total]');
            if (!total) {
                return;
            }
            total.textContent = index === 0 ? `RT ${regular.toFixed(2)} / OT ${overtime.toFixed(2)}` : '';
            total.classList.toggle('d-none', index !== 0);
        });
    };

    const calculateWeekTotals = () => {
        const rows = Array.from(table.querySelectorAll('[data-entry-row]'));
        const regular = rows.reduce((sum, row) => sum + (parseFloat(row.querySelector('[data-field="regular_hours"]').value) || 0), 0);
        const overtime = rows.reduce((sum, row) => sum + (parseFloat(row.querySelector('[data-field="overtime_hours"]').value) || 0), 0);

        document.getElementById('weekRegularTotal').textContent = `Week RT ${regular.toFixed(2)}`;
        document.getElementById('weekOvertimeTotal').textContent = `Week OT ${overtime.toFixed(2)}`;
        document.getElementById('weekGrandTotal').textContent = `Week Total ${(regular + overtime).toFixed(2)}`;
    };

    const updateRowRequirements = (row) => {
        const regular = parseFloat(row.querySelector('[data-field="regular_hours"]').value) || 0;
        const overtime = parseFloat(row.querySelector('[data-field="overtime_hours"]').value) || 0;
        const hasHours = regular > 0 || overtime > 0;
        const attendanceSelect = row.querySelector('[data-field="attendance_code"]');
        const projectSelect = row.querySelector('[data-field="project_id"]');
        const overtimeInput = row.querySelector('[data-field="overtime_hours"]');
        const selectedAttendanceCode = attendanceSelect.tomselect?.getValue() ?? attendanceSelect.value;
        const isLeave = isLeaveAttendanceCode(selectedAttendanceCode);
        const isProjectOptional = isProjectOptionalAttendanceCode(selectedAttendanceCode);

        attendanceSelect.required = hasHours;
        attendanceSelect.tomselect?.wrapper.classList.toggle('is-required', hasHours);

        projectSelect.required = hasHours && ! isProjectOptional;
        projectSelect.tomselect?.wrapper.classList.toggle('is-required', hasHours && ! isProjectOptional);

        if (isLeave) {
            overtimeInput.value = '0';
        }

        overtimeInput.disabled = isLeave;
    };

    const resequenceRows = () => {
        const seenDates = new Set();
        const rows = table.querySelectorAll('[data-entry-row]');

        rows.forEach((row, index) => {
            renameRowFields(row, index);

            const dateLabel = row.querySelector('[data-date-label]');
            const dayLabel = row.querySelector('[data-day-label]');
            const addButton = row.querySelector('[data-add-entry]');
            const isFirstForDate = !seenDates.has(row.dataset.workDate);

            if (dateLabel && dayLabel) {
                dateLabel.textContent = isFirstForDate ? row.dataset.workDate : '';
                dayLabel.textContent = isFirstForDate ? row.dataset.dayName : '';
            }

            if (addButton) {
                addButton.classList.toggle('d-none', ! isFirstForDate);
            }

            seenDates.add(row.dataset.workDate);
            updateRowRequirements(row);
        });

        seenDates.forEach((workDate) => calculateDayTotals(workDate));
        calculateWeekTotals();
        nextIndex = rows.length;
    };

    table.addEventListener('click', (event) => {
        const addButton = event.target.closest('[data-add-entry]');
        const removeButton = event.target.closest('[data-remove-entry]');

        if (addButton) {
            const currentRow = addButton.closest('[data-entry-row]');
            const currentAttendanceValue = currentRow.querySelector('[data-field="attendance_code"]').tomselect?.getValue()
                ?? currentRow.querySelector('[data-field="attendance_code"]').value;
            const currentProjectValue = currentRow.querySelector('[data-field="project_id"]').tomselect?.getValue()
                ?? currentRow.querySelector('[data-field="project_id"]').value;

            destroySearchableSelects(currentRow);
            const newRow = currentRow.cloneNode(true);
            initializeSearchableSelects(currentRow);
            setSearchableSelectValue(currentRow.querySelector('[data-field="attendance_code"]'), currentAttendanceValue);
            setSearchableSelectValue(currentRow.querySelector('[data-field="project_id"]'), currentProjectValue);

            newRow.querySelector('[data-field="work_date"]').value = currentRow.dataset.workDate;
            newRow.querySelector('[data-field="attendance_code"]').value = '';
            newRow.querySelector('[data-field="project_id"]').value = '';
            newRow.querySelector('[data-field="regular_hours"]').value = '0';
            newRow.querySelector('[data-field="overtime_hours"]').value = '0';
            newRow.querySelector('[data-field="remarks"]').value = '';
            newRow.querySelector('[data-date-label]').textContent = '';
            newRow.querySelector('[data-day-label]').textContent = '';
            newRow.querySelector('[data-day-total]').textContent = '';
            renameRowFields(newRow, nextIndex++);

            let insertAfter = currentRow;
            while (insertAfter.nextElementSibling && insertAfter.nextElementSibling.dataset.workDate === currentRow.dataset.workDate) {
                insertAfter = insertAfter.nextElementSibling;
            }
            insertAfter.after(newRow);
            initializeTooltips(newRow);
            initializeSearchableSelects(newRow);
            resequenceRows();
        }

        if (removeButton) {
            const rows = Array.from(table.querySelectorAll('[data-entry-row]'));
            const currentRow = removeButton.closest('[data-entry-row]');
            const sameDayRows = rows.filter((row) => row.dataset.workDate === currentRow.dataset.workDate);

            if (sameDayRows.length === 1) {
                setSearchableSelectValue(currentRow.querySelector('[data-field="attendance_code"]'), '');
                setSearchableSelectValue(currentRow.querySelector('[data-field="project_id"]'), '');
                currentRow.querySelector('[data-field="regular_hours"]').value = '0';
                currentRow.querySelector('[data-field="overtime_hours"]').value = '0';
                currentRow.querySelector('[data-field="remarks"]').value = '';
                updateRowRequirements(currentRow);
                calculateDayTotals(currentRow.dataset.workDate);
                calculateWeekTotals();
                return;
            }

            currentRow.remove();
            resequenceRows();
        }
    });

    table.addEventListener('input', (event) => {
        if (event.target.matches('[data-field="regular_hours"], [data-field="overtime_hours"]')) {
            const row = event.target.closest('[data-entry-row]');
            updateRowRequirements(row);
            calculateDayTotals(row.dataset.workDate);
            calculateWeekTotals();
        }
    });

    table.addEventListener('change', (event) => {
        if (event.target.matches('[data-field="attendance_code"]')) {
            const row = event.target.closest('[data-entry-row]');
            updateRowRequirements(row);
            calculateDayTotals(row.dataset.workDate);
            calculateWeekTotals();
        }
    });

    resequenceRows();
})();
</script>
@endsection
