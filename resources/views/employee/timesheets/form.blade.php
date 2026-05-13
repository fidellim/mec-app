@extends('layouts.app')

@section('content')
@php($isEdit = (bool) $timesheet)
<h1 class="h3 mb-3">{{ $isEdit ? 'Edit Timesheet' : 'Create Weekly Timesheet' }}</h1>
@if($timesheet?->status === 'rejected')
    <div class="alert alert-warning"><strong>Rejected:</strong> {{ $timesheet->rejection_comment }}</div>
@endif
<form method="post" action="{{ $isEdit ? route('employee.timesheets.update', $timesheet) : route('employee.timesheets.store') }}">
    @csrf
    @if($isEdit) @method('put') @endif
    <div class="content-card p-3 mb-3">
        <label class="form-label">Weekly period</label>
        <select class="form-select" name="timesheet_period_id" required>
            @foreach($periods as $period)
                <option value="{{ $period->id }}" @selected(old('timesheet_period_id', $timesheet?->timesheet_period_id) == $period->id)>
                    Week {{ $period->week_number }}, {{ $period->year }}: {{ $period->start_date->toDateString() }} to {{ $period->end_date->toDateString() }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="content-card p-3">
        <div class="small text-muted mb-2">
            Use Add project to split a day across multiple projects. Overtime-only project rows are allowed when regular hours are 0.
        </div>
        <div class="table-responsive">
            <table class="table timesheet-entry-table" id="timesheet-entry-table">
                <thead><tr><th>Date</th><th>Day</th><th>Attendance Code</th><th>Project/Job</th><th>Regular</th><th>Overtime</th><th>Description</th><th>Remarks</th><th></th></tr></thead>
                <tbody>
                @foreach($entries as $i => $entry)
                    @php($row = is_array($entry) ? (object) $entry : $entry)
                    @php($workDate = $row->work_date instanceof \Carbon\CarbonInterface ? $row->work_date->toDateString() : $row->work_date)
                    @php($dayName = \Carbon\Carbon::parse($workDate)->format('l'))
                    <tr data-entry-row data-work-date="{{ $workDate }}" data-day-name="{{ $dayName }}">
                        <td style="min-width: 145px;">
                            <input type="hidden" name="entries[{{ $i }}][work_date]" value="{{ old("entries.$i.work_date", $workDate) }}" data-field="work_date">
                            <span data-date-label>{{ old("entries.$i.work_date", $workDate) }}</span>
                        </td>
                        <td style="min-width: 110px;">
                            <span data-day-label>{{ $dayName }}</span>
                        </td>
                        <td>
                            <select class="form-select attendance-select" name="entries[{{ $i }}][attendance_code]" data-field="attendance_code" required>
                                @foreach($attendanceCodes as $code => $label)
                                    <option value="{{ $code }}" @selected(old("entries.$i.attendance_code", $row->attendance_code ?? 'O100') === $code)>{{ $code }} - {{ $label }}</option>
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
                        <td class="description-cell"><input class="form-control" name="entries[{{ $i }}][description]" data-field="description" value="{{ old("entries.$i.description", $row->description) }}"></td>
                        <td class="remarks-cell"><input class="form-control" name="entries[{{ $i }}][remarks]" data-field="remarks" value="{{ old("entries.$i.remarks", $row->remarks) }}"></td>
                        <td>
                            <div class="d-flex gap-1">
                                <button type="button" class="btn btn-sm btn-outline-primary" data-add-entry>Add project</button>
                                <button type="button" class="btn btn-sm btn-outline-danger" data-remove-entry>Remove</button>
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="d-flex gap-2 justify-content-end">
            <button class="btn btn-outline-secondary" name="submit" value="0">Save Draft</button>
            <button class="btn btn-primary" name="submit" value="1" data-confirm="Submit this timesheet for approval?">Submit for Approval</button>
        </div>
    </div>
</form>
<script>
(() => {
    const table = document.getElementById('timesheet-entry-table');
    let nextIndex = {{ count($entries) }};

    const renameRowFields = (row, index) => {
        row.querySelectorAll('[data-field]').forEach((field) => {
            field.name = `entries[${index}][${field.dataset.field}]`;
        });
    };

    const resequenceRows = () => {
        const seenDates = new Set();
        const rows = table.querySelectorAll('[data-entry-row]');

        rows.forEach((row, index) => {
            renameRowFields(row, index);

            const dateLabel = row.querySelector('[data-date-label]');
            const dayLabel = row.querySelector('[data-day-label]');
            const isFirstForDate = !seenDates.has(row.dataset.workDate);

            if (dateLabel && dayLabel) {
                dateLabel.textContent = isFirstForDate ? row.dataset.workDate : '';
                dayLabel.textContent = isFirstForDate ? row.dataset.dayName : '';
            }

            seenDates.add(row.dataset.workDate);
        });

        nextIndex = rows.length;
    };

    table.addEventListener('click', (event) => {
        const addButton = event.target.closest('[data-add-entry]');
        const removeButton = event.target.closest('[data-remove-entry]');

        if (addButton) {
            const currentRow = addButton.closest('[data-entry-row]');
            const newRow = currentRow.cloneNode(true);

            newRow.querySelector('[data-field="work_date"]').value = currentRow.dataset.workDate;
            newRow.querySelector('[data-field="attendance_code"]').value = 'O100';
            newRow.querySelector('[data-field="project_id"]').value = '';
            newRow.querySelector('[data-field="regular_hours"]').value = '0';
            newRow.querySelector('[data-field="overtime_hours"]').value = '0';
            newRow.querySelector('[data-field="description"]').value = '';
            newRow.querySelector('[data-field="remarks"]').value = '';
            newRow.querySelector('[data-date-label]').textContent = '';
            newRow.querySelector('[data-day-label]').textContent = '';
            renameRowFields(newRow, nextIndex++);

            let insertAfter = currentRow;
            while (insertAfter.nextElementSibling && insertAfter.nextElementSibling.dataset.workDate === currentRow.dataset.workDate) {
                insertAfter = insertAfter.nextElementSibling;
            }
            insertAfter.after(newRow);
        }

        if (removeButton) {
            const rows = Array.from(table.querySelectorAll('[data-entry-row]'));
            const currentRow = removeButton.closest('[data-entry-row]');
            const sameDayRows = rows.filter((row) => row.dataset.workDate === currentRow.dataset.workDate);

            if (sameDayRows.length === 1) {
                currentRow.querySelector('[data-field="attendance_code"]').value = 'O100';
                currentRow.querySelector('[data-field="project_id"]').value = '';
                currentRow.querySelector('[data-field="regular_hours"]').value = '0';
                currentRow.querySelector('[data-field="overtime_hours"]').value = '0';
                currentRow.querySelector('[data-field="description"]').value = '';
                currentRow.querySelector('[data-field="remarks"]').value = '';
                return;
            }

            currentRow.remove();
            resequenceRows();
        }
    });

    resequenceRows();
})();
</script>
@endsection
