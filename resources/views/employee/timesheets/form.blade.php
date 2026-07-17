@extends('layouts.app')

@section('content')
@php
    $isEdit = (bool) $timesheet;
    $timesheetEntryErrors = collect($errors->getMessages())
        ->filter(fn ($messages, $key) => $key === 'entries' || str_starts_with($key, 'entries.'));
@endphp
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
@if($timesheetEntryErrors->isNotEmpty())
    <div class="alert alert-warning">
        <div class="fw-semibold mb-1">Timesheet could not be saved or submitted</div>
        <div class="mb-2">Please correct the highlighted daily entries, then try Save Draft or Submit for Approval again.</div>
        <ul class="mb-0">
            @foreach($timesheetEntryErrors as $messages)
                @foreach($messages as $message)
                    <li>{{ $message }}</li>
                @endforeach
            @endforeach
        </ul>
    </div>
@endif
@if(($approvedLeavePlans ?? collect())->isNotEmpty())
    <div class="alert alert-warning">
        <div class="fw-semibold mb-1">Approved leave planned for this week</div>
        <div class="mb-2">Review these dates before saving your timesheet. The timesheet entries are not changed automatically.</div>
        <ul class="mb-0">
            @foreach($approvedLeavePlans as $leavePlan)
                <li>
                    {{ $leavePlan->start_date->toFormattedDateString() }} to {{ $leavePlan->end_date->toFormattedDateString() }}:
                    {{ $leavePlan->leaveLabel() }} ({{ $leavePlan->leaveLengthLabel() }})
                </li>
            @endforeach
        </ul>
    </div>
@endif
<form method="post" action="{{ $isEdit ? route('employee.timesheets.update', $timesheet) : route('employee.timesheets.store') }}" data-prevent-enter-submit novalidate>
    @csrf
    @if($isEdit) @method('put') @endif
    <div class="alert alert-warning d-none" data-timesheet-client-warning role="alert" tabindex="-1">
        <div class="fw-semibold mb-1">Timesheet could not be saved or submitted</div>
        <div class="mb-2">Please correct the highlighted daily entries, then try Save Draft or Submit for Approval again.</div>
        <ul class="mb-0" data-timesheet-client-warning-list></ul>
    </div>
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
                <thead class="visually-hidden"><tr><th>Attendance Code</th><th>Project/Job</th><th>Regular</th><th>Overtime</th><th>Remarks</th><th>Actions</th></tr></thead>
                <tbody>
                @php($renderedDates = [])
                @foreach($entries as $i => $entry)
                    @php($row = is_array($entry) ? (object) $entry : $entry)
                    @php($workDate = $row->work_date instanceof \Carbon\CarbonInterface ? $row->work_date->toDateString() : $row->work_date)
                    @php($dayName = \Carbon\Carbon::parse($workDate)->format('l'))
                    @php($selectedAttendanceCode = old("entries.$i.attendance_code", $row->attendance_code ?? ''))
                    @php($rowHasErrors = $errors->has("entries.$i.*"))
                    @if(! in_array($workDate, $renderedDates, true))
                        @php($renderedDates[] = $workDate)
                        <tr class="timesheet-day-summary-row" data-day-summary-row data-work-date="{{ $workDate }}" data-day-name="{{ $dayName }}">
                            <td colspan="6">
                                <div class="timesheet-day-summary">
                                    <div>
                                        <span class="fw-semibold" data-date-label>{{ \Carbon\Carbon::parse($workDate)->format('F j, Y') }}</span>
                                        <span class="text-muted">({{ $workDate }})</span>
                                        <div class="small text-muted text-uppercase" data-day-label>{{ $dayName }}</div>
                                    </div>
                                    <div class="d-flex gap-2 flex-wrap">
                                        <span class="badge text-bg-light border text-dark px-3 py-2" data-day-regular-total>RT 0.00</span>
                                        <span class="badge text-bg-light border text-dark px-3 py-2" data-day-overtime-total>OT 0.00</span>
                                        <button type="button" class="btn btn-sm btn-outline-primary day-copy-button" data-copy-day data-work-date="{{ $workDate }}" data-bs-toggle="tooltip" data-bs-title="Copy this day's rows" aria-label="Copy {{ $dayName }} entries">
                                            <span class="action-icon action-icon-duplicate" aria-hidden="true"></span>
                                            <span>Copy Day</span>
                                        </button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr class="timesheet-day-column-row" data-day-column-row data-work-date="{{ $workDate }}">
                            <th scope="col">Attendance Code</th>
                            <th scope="col">Project/Job</th>
                            <th scope="col">Regular</th>
                            <th scope="col">Overtime</th>
                            <th scope="col">Remarks</th>
                            <th scope="col" class="text-end">Actions</th>
                        </tr>
                    @endif
                    <tr @class(['timesheet-entry-row-invalid' => $rowHasErrors]) data-entry-row data-work-date="{{ $workDate }}" data-day-name="{{ $dayName }}">
                        <td>
                            <input type="hidden" name="entries[{{ $i }}][work_date]" value="{{ old("entries.$i.work_date", $workDate) }}" data-field="work_date">
                            <select class="form-select attendance-select @error("entries.$i.attendance_code") is-invalid @enderror" name="entries[{{ $i }}][attendance_code]" data-field="attendance_code">
                                <option value="">Select</option>
                                @foreach($attendanceCodes as $code => $label)
                                    <option value="{{ $code }}" @selected($selectedAttendanceCode === $code)>{{ $code }} - {{ $label }}</option>
                                @endforeach
                            </select>
                            @error("entries.$i.attendance_code")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            <div class="invalid-feedback d-block d-none" data-client-error-for="attendance_code"></div>
                        </td>
                        <td>
                            <select class="form-select project-select @error("entries.$i.project_id") is-invalid @enderror" name="entries[{{ $i }}][project_id]" data-field="project_id">
                                <option value="">Select</option>
                                @foreach($projects as $project)
                                    <option value="{{ $project->id }}" title="{{ $project->project_name }}" @selected(old("entries.$i.project_id", $row->project_id) == $project->id)>{{ $project->project_code }}{{ $project->is_timesheet_accessible ? '' : ' — unavailable' }}</option>
                                @endforeach
                            </select>
                            @error("entries.$i.project_id")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            <div class="invalid-feedback d-block d-none" data-client-error-for="project_id"></div>
                        </td>
                        <td style="width: 110px;">
                            <input class="form-control @error("entries.$i.regular_hours") is-invalid @enderror" type="number" min="0" max="24" step="0.25" name="entries[{{ $i }}][regular_hours]" data-field="regular_hours" value="{{ old("entries.$i.regular_hours", $row->regular_hours ?? 0) }}">
                            @error("entries.$i.regular_hours")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            <div class="invalid-feedback d-block d-none" data-client-error-for="regular_hours"></div>
                        </td>
                        <td style="width: 110px;">
                            <input class="form-control @error("entries.$i.overtime_hours") is-invalid @enderror" type="number" min="0" max="24" step="0.25" name="entries[{{ $i }}][overtime_hours]" data-field="overtime_hours" value="{{ old("entries.$i.overtime_hours", $row->overtime_hours ?? 0) }}">
                            @error("entries.$i.overtime_hours")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            <div class="invalid-feedback d-block d-none" data-client-error-for="overtime_hours"></div>
                        </td>
                        <td class="remarks-cell">
                            <input class="form-control @error("entries.$i.remarks") is-invalid @enderror" name="entries[{{ $i }}][remarks]" data-field="remarks" value="{{ old("entries.$i.remarks", $row->remarks) }}">
                            @error("entries.$i.remarks")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </td>
                        <td>
                            <div class="timesheet-row-actions">
                                <button type="button" class="btn btn-sm btn-outline-primary action-icon-button" data-add-entry data-bs-toggle="tooltip" data-bs-title="Add blank project row for this day" aria-label="Add project row for this day">
                                    <span class="action-icon action-icon-add" aria-hidden="true"></span>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary action-icon-button" data-duplicate-entry data-bs-toggle="tooltip" data-bs-title="Duplicate this row below" aria-label="Duplicate this row below">
                                    <span class="action-icon action-icon-duplicate" aria-hidden="true"></span>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger action-icon-button" data-remove-entry data-bs-toggle="tooltip" data-bs-title="Remove row" aria-label="Remove row">
                                    <span class="action-icon action-icon-trash" aria-hidden="true"></span>
                                </button>
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="sticky-actions d-flex flex-column flex-sm-row gap-2 justify-content-end p-3">
            <button type="submit" class="btn btn-outline-secondary" name="submit" value="0">Save Draft</button>
            <button type="submit" class="btn btn-primary" name="submit" value="1" data-confirm="Submit this timesheet for approval?">Submit for Approval</button>
        </div>
    </div>
</form>
<div class="modal fade" id="copyDayModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title">Paste copied day</h5>
                    <div class="small text-muted" id="copyDaySourceLabel"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning mb-3">
                    Pasting will replace all existing entries for the selected day(s). Review your choices before continuing.
                </div>
                <div class="copy-day-target-list" id="copyDayTargetList"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="copyDayPasteButton" disabled>Paste to selected days</button>
            </div>
        </div>
    </div>
</div>
<script>
(() => {
    const table = document.getElementById('timesheet-entry-table');
    const form = document.querySelector('[data-prevent-enter-submit]');
    const copyDayModalElement = document.getElementById('copyDayModal');
    const copyDaySourceLabel = document.getElementById('copyDaySourceLabel');
    const copyDayTargetList = document.getElementById('copyDayTargetList');
    const copyDayPasteButton = document.getElementById('copyDayPasteButton');
    const clientWarning = form?.querySelector('[data-timesheet-client-warning]');
    const clientWarningList = form?.querySelector('[data-timesheet-client-warning-list]');
    let nextIndex = {{ count($entries) }};
    let copiedDay = null;
    const leaveAttendanceCodes = @json($leaveAttendanceCodes ?? config('timesheet.leave_attendance_codes', []));
    const projectOptionalAttendanceCodes = @json($projectOptionalAttendanceCodes ?? config('timesheet.project_optional_attendance_codes', config('timesheet.leave_attendance_codes', [])));
    const isLeaveAttendanceCode = (value) => leaveAttendanceCodes.includes(value);
    const isProjectOptionalAttendanceCode = (value) => projectOptionalAttendanceCodes.includes(value);

    form?.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' || event.isComposing) {
            return;
        }

        const target = event.target;

        if (!target || target.closest('button, textarea, .ts-control')) {
            return;
        }

        if (target.matches('input, select')) {
            event.preventDefault();
        }
    });

    form?.addEventListener('submit', (event) => {
        const messages = validateTimesheetEntries();

        if (!messages.length) {
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();
        showClientWarning(messages);
        window.showAppToast?.('Please correct the highlighted timesheet rows before saving or submitting.', 'warning', 'Timesheet needs attention');
    });

    const renameRowFields = (row, index) => {
        row.querySelectorAll('[data-field]').forEach((field) => {
            field.name = `entries[${index}][${field.dataset.field}]`;
        });
    };

    const formatDisplayDate = (workDate) => {
        const date = new Date(`${workDate}T00:00:00`);

        if (Number.isNaN(date.getTime())) {
            return workDate;
        }

        return date.toLocaleDateString('en-US', {
            month: 'long',
            day: 'numeric',
            year: 'numeric',
        });
    };

    const getSearchableSelectValue = (row, fieldName) => {
        const select = row.querySelector(`[data-field="${fieldName}"]`);

        return select.tomselect?.getValue() ?? select.value;
    };

    const setRowFieldValue = (row, fieldName, value) => {
        const field = row.querySelector(`[data-field="${fieldName}"]`);

        if (!field) {
            return;
        }

        if (field.matches('select')) {
            setSearchableSelectValue(field, value);
            return;
        }

        field.value = value;
    };

    const cloneEntryRow = (currentRow) => {
        const currentValues = {
            attendance_code: getSearchableSelectValue(currentRow, 'attendance_code'),
            project_id: getSearchableSelectValue(currentRow, 'project_id'),
        };

        destroySearchableSelects(currentRow);
        const newRow = currentRow.cloneNode(true);
        initializeSearchableSelects(currentRow);
        setSearchableSelectValue(currentRow.querySelector('[data-field="attendance_code"]'), currentValues.attendance_code);
        setSearchableSelectValue(currentRow.querySelector('[data-field="project_id"]'), currentValues.project_id);

        return newRow;
    };

    const prepareClonedRow = (newRow, values) => {
        Object.entries(values).forEach(([fieldName, value]) => {
            setRowFieldValue(newRow, fieldName, value);
        });

        clearRowClientValidation(newRow);
        renameRowFields(newRow, nextIndex++);
    };

    const getDayRows = (workDate) => Array.from(table.querySelectorAll(`[data-entry-row][data-work-date="${workDate}"]`));

    const getDaySummaries = () => Array.from(table.querySelectorAll('[data-day-summary-row]'));

    const getDayLabel = (summaryRow) => {
        const dateLabel = summaryRow.querySelector('[data-date-label]')?.textContent.trim() || summaryRow.dataset.workDate;
        const dayLabel = summaryRow.querySelector('[data-day-label]')?.textContent.trim() || summaryRow.dataset.dayName;

        return `${dateLabel} (${summaryRow.dataset.workDate}) - ${dayLabel}`;
    };

    const getRowValues = (row) => ({
        attendance_code: getSearchableSelectValue(row, 'attendance_code'),
        project_id: getSearchableSelectValue(row, 'project_id'),
        regular_hours: row.querySelector('[data-field="regular_hours"]').value || '0',
        overtime_hours: row.querySelector('[data-field="overtime_hours"]').value || '0',
        remarks: row.querySelector('[data-field="remarks"]').value || '',
    });

    const clearRowClientValidation = (row) => {
        row.classList.remove('timesheet-entry-row-client-invalid');
        row.querySelectorAll('.timesheet-client-invalid').forEach((field) => {
            field.classList.remove('timesheet-client-invalid', 'is-invalid');
            field.tomselect?.wrapper.classList.remove('timesheet-client-invalid', 'is-invalid');
        });
        row.querySelectorAll('[data-client-error-for]').forEach((error) => {
            error.textContent = '';
            error.classList.add('d-none');
        });
    };

    const clearClientValidation = () => {
        clientWarning?.classList.add('d-none');
        if (clientWarningList) {
            clientWarningList.innerHTML = '';
        }
        table.querySelectorAll('[data-entry-row]').forEach(clearRowClientValidation);
    };

    const setClientFieldError = (row, fieldName, message) => {
        const field = row.querySelector(`[data-field="${fieldName}"]`);
        const error = row.querySelector(`[data-client-error-for="${fieldName}"]`);

        row.classList.add('timesheet-entry-row-client-invalid');
        field?.classList.add('timesheet-client-invalid', 'is-invalid');
        field?.tomselect?.wrapper.classList.add('timesheet-client-invalid', 'is-invalid');

        if (error) {
            error.textContent = message;
            error.classList.remove('d-none');
        }
    };

    const getRowLabel = (row) => {
        const summary = table.querySelector(`[data-day-summary-row][data-work-date="${row.dataset.workDate}"]`);

        return summary ? getDayLabel(summary) : row.dataset.workDate;
    };

    const validateTimesheetEntries = () => {
        const messages = [];
        clearClientValidation();

        table.querySelectorAll('[data-entry-row]').forEach((row) => {
            const regular = parseFloat(row.querySelector('[data-field="regular_hours"]').value) || 0;
            const overtime = parseFloat(row.querySelector('[data-field="overtime_hours"]').value) || 0;
            const hasHours = regular > 0 || overtime > 0;
            const attendanceCode = getSearchableSelectValue(row, 'attendance_code');
            const projectId = getSearchableSelectValue(row, 'project_id');
            const label = getRowLabel(row);

            if (!hasHours) {
                return;
            }

            if (!attendanceCode) {
                const message = `${label} needs an attendance code when hours are entered.`;
                messages.push(message);
                setClientFieldError(row, 'attendance_code', 'Select an attendance code.');
            }

            if (!isProjectOptionalAttendanceCode(attendanceCode) && !projectId) {
                const message = `${label} needs a project/job number when hours are entered.`;
                messages.push(message);
                setClientFieldError(row, 'project_id', 'Select a project/job number.');
            }
        });

        return messages;
    };

    const showClientWarning = (messages) => {
        if (!clientWarning || !clientWarningList) {
            return;
        }

        clientWarningList.innerHTML = '';
        messages.forEach((message) => {
            const item = document.createElement('li');
            item.textContent = message;
            clientWarningList.appendChild(item);
        });
        clientWarning.classList.remove('d-none');
        clientWarning.focus({ preventScroll: true });
        clientWarning.scrollIntoView({ behavior: 'smooth', block: 'center' });
    };

    const getCopiedRowsForDay = (workDate) => getDayRows(workDate).map(getRowValues);

    const setEntryRowValues = (row, workDate, values) => {
        row.dataset.workDate = workDate;
        setRowFieldValue(row, 'work_date', workDate);
        setRowFieldValue(row, 'attendance_code', values.attendance_code);
        setRowFieldValue(row, 'project_id', values.project_id);
        setRowFieldValue(row, 'regular_hours', values.regular_hours);
        setRowFieldValue(row, 'overtime_hours', values.overtime_hours);
        setRowFieldValue(row, 'remarks', values.remarks);
        updateRowRequirements(row);
    };

    const refreshCopyDayPasteButton = () => {
        if (!copyDayPasteButton || !copyDayTargetList) {
            return;
        }

        copyDayPasteButton.disabled = !copyDayTargetList.querySelector('[data-copy-day-target]:checked');
    };

    const getCopyDayModal = () => {
        if (!copyDayModalElement || !window.bootstrap) {
            return null;
        }

        return bootstrap.Modal.getOrCreateInstance(copyDayModalElement);
    };

    const openCopyDayModal = (sourceDate) => {
        const copyDayModal = getCopyDayModal();

        if (!copyDayModal || !copyDayTargetList || !copyDaySourceLabel) {
            return;
        }

        const sourceSummary = table.querySelector(`[data-day-summary-row][data-work-date="${sourceDate}"]`);
        copiedDay = {
            sourceDate,
            sourceLabel: sourceSummary ? getDayLabel(sourceSummary) : sourceDate,
            rows: getCopiedRowsForDay(sourceDate),
        };

        copyDaySourceLabel.textContent = `Copied from ${copiedDay.sourceLabel}`;
        copyDayTargetList.innerHTML = '';

        getDaySummaries()
            .filter((summaryRow) => summaryRow.dataset.workDate !== sourceDate)
            .forEach((summaryRow, index) => {
                const id = `copy-day-target-${index}`;
                const wrapper = document.createElement('label');
                wrapper.className = 'copy-day-target';
                wrapper.setAttribute('for', id);

                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.className = 'form-check-input';
                checkbox.id = id;
                checkbox.value = summaryRow.dataset.workDate;
                checkbox.dataset.copyDayTarget = 'true';

                const labelText = document.createElement('span');
                labelText.textContent = getDayLabel(summaryRow);

                wrapper.append(checkbox, labelText);
                copyDayTargetList.appendChild(wrapper);
            });

        refreshCopyDayPasteButton();
        copyDayModal.show();
    };

    const pasteCopiedDayToTargets = () => {
        if (!copiedDay || !copyDayTargetList) {
            return;
        }

        const targetDates = Array.from(copyDayTargetList.querySelectorAll('[data-copy-day-target]:checked'))
            .map((checkbox) => checkbox.value)
            .filter((workDate) => workDate && workDate !== copiedDay.sourceDate);

        if (!targetDates.length) {
            refreshCopyDayPasteButton();
            return;
        }

        targetDates.forEach((workDate) => {
            const targetRows = getDayRows(workDate);
            const firstTargetRow = targetRows[0];

            if (!firstTargetRow) {
                return;
            }

            targetRows.slice(1).forEach((row) => {
                destroySearchableSelects(row);
                row.remove();
            });

            setEntryRowValues(firstTargetRow, workDate, copiedDay.rows[0] ?? {
                attendance_code: '',
                project_id: '',
                regular_hours: '0',
                overtime_hours: '0',
                remarks: '',
            });

            let insertAfter = firstTargetRow;

            copiedDay.rows.slice(1).forEach((values) => {
                const newRow = cloneEntryRow(firstTargetRow);
                prepareClonedRow(newRow, {
                    work_date: workDate,
                    ...values,
                });
                insertAfter.after(newRow);
                insertAfter = newRow;
                initializeTooltips(newRow);
                initializeSearchableSelects(newRow);
                updateRowRequirements(newRow);
            });
        });

        getCopyDayModal()?.hide();
        resequenceRows();
    };

    const removeTooltipElements = () => {
        document.querySelectorAll('.tooltip').forEach((element) => element.remove());
    };

    const hideActionTooltip = (button) => {
        if (!button || !window.bootstrap) {
            return;
        }

        const tooltip = bootstrap.Tooltip.getOrCreateInstance(button);

        tooltip.hide();
        tooltip.dispose();
        removeTooltipElements();
        requestAnimationFrame(removeTooltipElements);
        setTimeout(removeTooltipElements, 150);
        button.addEventListener('mouseleave', () => bootstrap.Tooltip.getOrCreateInstance(button), { once: true });
        button.blur();
    };

    const calculateDayTotals = (workDate) => {
        const rows = Array.from(table.querySelectorAll(`[data-entry-row][data-work-date="${workDate}"]`));
        const regular = rows.reduce((sum, row) => sum + (parseFloat(row.querySelector('[data-field="regular_hours"]').value) || 0), 0);
        const overtime = rows.reduce((sum, row) => sum + (parseFloat(row.querySelector('[data-field="overtime_hours"]').value) || 0), 0);

        const summaryRow = table.querySelector(`[data-day-summary-row][data-work-date="${workDate}"]`);
        const regularTotal = summaryRow?.querySelector('[data-day-regular-total]');
        const overtimeTotal = summaryRow?.querySelector('[data-day-overtime-total]');

        if (regularTotal) {
            regularTotal.textContent = `RT ${regular.toFixed(2)}`;
        }

        if (overtimeTotal) {
            overtimeTotal.textContent = `OT ${overtime.toFixed(2)}`;
        }
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

            const addButton = row.querySelector('[data-add-entry]');
            const isFirstForDate = !seenDates.has(row.dataset.workDate);

            if (addButton) {
                addButton.classList.toggle('d-none', ! isFirstForDate);
            }

            seenDates.add(row.dataset.workDate);
            updateRowRequirements(row);
        });

        seenDates.forEach((workDate) => {
            const summaryRow = table.querySelector(`[data-day-summary-row][data-work-date="${workDate}"]`);

            if (summaryRow) {
                summaryRow.querySelector('[data-date-label]').textContent = formatDisplayDate(workDate);
                summaryRow.querySelector('[data-day-label]').textContent = summaryRow.dataset.dayName;
            }

            calculateDayTotals(workDate);
        });
        calculateWeekTotals();
        nextIndex = rows.length;
    };

    table.addEventListener('click', (event) => {
        const addButton = event.target.closest('[data-add-entry]');
        const duplicateButton = event.target.closest('[data-duplicate-entry]');
        const removeButton = event.target.closest('[data-remove-entry]');
        const copyDayButton = event.target.closest('[data-copy-day]');
        const actionButton = addButton || duplicateButton || removeButton;

        hideActionTooltip(actionButton);

        if (copyDayButton) {
            hideActionTooltip(copyDayButton);
            openCopyDayModal(copyDayButton.dataset.workDate);
        }

        if (addButton) {
            const currentRow = addButton.closest('[data-entry-row]');
            const newRow = cloneEntryRow(currentRow);
            prepareClonedRow(newRow, {
                work_date: currentRow.dataset.workDate,
                attendance_code: '',
                project_id: '',
                regular_hours: '0',
                overtime_hours: '0',
                remarks: '',
            });

            let insertAfter = currentRow;
            while (insertAfter.nextElementSibling && insertAfter.nextElementSibling.dataset.workDate === currentRow.dataset.workDate) {
                insertAfter = insertAfter.nextElementSibling;
            }
            insertAfter.after(newRow);
            initializeTooltips(newRow);
            initializeSearchableSelects(newRow);
            resequenceRows();
        }

        if (duplicateButton) {
            const currentRow = duplicateButton.closest('[data-entry-row]');
            const newRow = cloneEntryRow(currentRow);
            prepareClonedRow(newRow, {
                work_date: currentRow.dataset.workDate,
                attendance_code: getSearchableSelectValue(currentRow, 'attendance_code'),
                project_id: getSearchableSelectValue(currentRow, 'project_id'),
                regular_hours: currentRow.querySelector('[data-field="regular_hours"]').value || '0',
                overtime_hours: currentRow.querySelector('[data-field="overtime_hours"]').value || '0',
                remarks: currentRow.querySelector('[data-field="remarks"]').value || '',
            });

            currentRow.after(newRow);
            initializeTooltips(newRow);
            initializeSearchableSelects(newRow);
            updateRowRequirements(newRow);
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

    copyDayTargetList?.addEventListener('change', (event) => {
        if (event.target.matches('[data-copy-day-target]')) {
            refreshCopyDayPasteButton();
        }
    });

    copyDayPasteButton?.addEventListener('click', pasteCopiedDayToTargets);

    table.addEventListener('input', (event) => {
        if (event.target.matches('[data-field="regular_hours"], [data-field="overtime_hours"]')) {
            const row = event.target.closest('[data-entry-row]');
            clearRowClientValidation(row);
            updateRowRequirements(row);
            calculateDayTotals(row.dataset.workDate);
            calculateWeekTotals();
        }
    });

    table.addEventListener('change', (event) => {
        if (event.target.matches('[data-field="attendance_code"], [data-field="project_id"]')) {
            const row = event.target.closest('[data-entry-row]');
            clearRowClientValidation(row);
            updateRowRequirements(row);
            calculateDayTotals(row.dataset.workDate);
            calculateWeekTotals();
        }
    });

    resequenceRows();
})();
</script>
@endsection
