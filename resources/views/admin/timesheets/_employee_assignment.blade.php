@php
    $assignment = $item['employee_assignment'] ?? null;
    $sourceEmployee = $item['source_user'];
    $needsIncorrectConfirmation = $sourceEmployee && !$assignment;
    $oldEmployeeId = is_scalar(old('employee_id')) ? (string) old('employee_id') : '';
    $oldReason = is_string(old('assignment_reason')) ? old('assignment_reason') : '';
    $restoring = old('assignment_action') === 'restore';
@endphp
<section class="mb-4 pb-3 border-bottom" aria-labelledby="employee-assignment-heading">
    <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
        <h3 class="h5 mb-0" id="employee-assignment-heading">Employee assignment</h3>
        <span class="badge text-bg-{{ $assignment ? 'warning' : ($item['user'] ? 'secondary' : 'danger') }}">{{ $assignment ? 'Manually assigned' : ($item['user'] ? 'Matched · Locked' : 'Unmatched') }}</span>
    </div>
    <div class="row g-3 mb-3">
        <div class="col-md-6"><div class="meta-label">Workbook identity</div><div class="meta-value">{{ $item['employee_name'] ?: 'Name not supplied' }}</div><div class="small text-body-secondary">{{ $item['employee_code'] }}</div></div>
        <div class="col-md-6"><div class="meta-label">Import belongs to</div><div class="meta-value">{{ $item['user']?->name ?? 'Assign an employee to continue' }}</div>@if($item['user'])<div class="small text-body-secondary">{{ $item['user']->employee_code }} · {{ $item['user']->department?->name ?? 'No department' }}</div>@endif</div>
    </div>
    @if($assignment)
        <p class="small mb-2">Assignment reason: {{ $assignment['reason'] }}</p>
    @endif
    <details @if(!$item['user'] || (!$restoring && $errors->hasAny(['employee_id', 'assignment_reason', 'incorrect_employee_match']))) open @endif>
        <summary class="text-primary">{{ $assignment ? 'Change assigned employee' : ($sourceEmployee ? 'Report incorrect employee match' : 'Assign employee') }}</summary>
        <form class="mt-3 employee-assignment-form" method="post" action="{{ route('admin.timesheets.import.assign-employee', ['batch' => $batchId, 'sheet' => $selected]) }}">
            @csrf
            <input type="hidden" name="revision" value="{{ $batch['revision'] }}">
            <input type="hidden" name="assignment_action" value="assign">
            @if($needsIncorrectConfirmation)
                <div class="form-check mb-3">
                    <input class="form-check-input @error('incorrect_employee_match') is-invalid @enderror" type="checkbox" id="incorrect-employee-match" name="incorrect_employee_match" value="1" required @checked(old('incorrect_employee_match'))>
                    <label class="form-check-label" for="incorrect-employee-match">I have verified that the workbook employee ID matches the wrong employee.</label>
                    @error('incorrect_employee_match')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            @endif
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="assignment-employee">Correct employee (required)</label>
                    <select class="form-select @error('employee_id') is-invalid @enderror" id="assignment-employee" name="employee_id" required aria-describedby="assignment-employee-help">
                        <option value="">Search by name or employee ID</option>
                        @foreach($employees as $employee)
                            <option value="{{ $employee->id }}" @selected($oldEmployeeId === (string) $employee->id) @disabled($employee->id === $item['user']?->id || $employee->id === $sourceEmployee?->id)>{{ $employee->name }} — {{ $employee->employee_code }} — {{ $employee->department?->name ?? 'No department' }}{{ $employee->is_active ? '' : ' (inactive)' }}</option>
                        @endforeach
                    </select>
                    <div class="form-text" id="assignment-employee-help">Applies to this workbook only. Employee master records are unchanged.</div>
                    @error('employee_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="assignment-reason">Reason for correction (required)</label>
                    <textarea class="form-control @error('assignment_reason') is-invalid @enderror" id="assignment-reason" name="assignment_reason" maxlength="2000" rows="3" required>{{ $oldReason }}</textarea>
                    @error('assignment_reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <p class="small text-body-secondary mt-3">Hours, dates, projects, and remarks are preserved. Discipline and category suggestions reset for the selected employee. Conflicts and allocations will be checked again. The correction and reason will be recorded in the imported timesheet’s history.</p>
            <button class="btn btn-outline-secondary" type="submit">Assign employee and revalidate</button>
            <div class="small text-body-secondary mt-2" data-assignment-status role="status" aria-live="polite"></div>
        </form>
    </details>
    @if($assignment && $sourceEmployee)
        <details class="mt-3" id="restore-employee-match" @if($restoring && $errors->any()) open @endif>
            <summary class="text-primary">Restore workbook match</summary>
            <form class="mt-3 employee-assignment-form" method="post" action="{{ route('admin.timesheets.import.assign-employee', ['batch' => $batchId, 'sheet' => $selected]) }}">
                @csrf
                <input type="hidden" name="revision" value="{{ $batch['revision'] }}">
                <input type="hidden" name="assignment_action" value="restore">
                <label class="form-label" for="restore-assignment-reason">Reason for restoring {{ $sourceEmployee->name }} (required)</label>
                <textarea class="form-control mb-2" id="restore-assignment-reason" name="assignment_reason" maxlength="2000" rows="2" required>{{ $restoring ? $oldReason : '' }}</textarea>
                <button class="btn btn-outline-secondary" type="submit">Restore workbook match and revalidate</button>
                <div class="small text-body-secondary mt-2" data-assignment-status role="status" aria-live="polite"></div>
            </form>
        </details>
    @endif
</section>
