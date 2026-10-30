@extends('layouts.app')

@section('content')
<style>
    .import-editor { min-width: 78rem; }
    .import-editor td { position: relative; }
    .import-editor th:nth-child(1) { min-width: 10rem; }
    .import-editor th:nth-child(2), .import-editor th:nth-child(4), .import-editor th:nth-child(5) { min-width: 12rem; }
    .import-editor th:nth-child(3), .import-editor th:nth-child(8) { min-width: 12rem; }
    .import-editor th:nth-child(6), .import-editor th:nth-child(7) { min-width: 6rem; }
</style>
<div class="section-header">
    <div>
        <h1 class="h3 page-heading mb-1">Import historical timesheets</h1>
        <p class="text-body-secondary mb-0">Review Excel records, correct classifications, and import them as previously approved.</p>
    </div>
    <a class="btn btn-outline-secondary" href="{{ route('admin.timesheets.index') }}">Back to timesheets</a>
</div>
@if($errors->any())
    <div class="alert alert-danger" role="alert"><p class="fw-semibold mb-1">The preview could not be saved.</p><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
@if(!isset($batch))
    <section class="content-card" aria-labelledby="upload-heading">
        <div class="content-card-header"><h2 class="h5 mb-1" id="upload-heading">Select workbooks</h2><p class="small text-body-secondary mb-0">Use the Employee Time Record template. Each workbook represents one employee and week.</p></div>
        <form class="content-card-body" method="post" enctype="multipart/form-data" action="{{ route('admin.timesheets.import.upload') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label" for="workbooks">Excel workbooks (required)</label>
                <input class="form-control @error('workbooks') is-invalid @enderror" id="workbooks" name="workbooks[]" type="file" accept=".xlsx,.xlsm" multiple required aria-describedby="workbooks-help">
                <div class="form-text" id="workbooks-help">Up to {{ config('timesheet_import.max_files') }} .xlsx or .xlsm files, {{ config('timesheet_import.max_file_kb') / 1024 }} MB each and {{ config('timesheet_import.max_batch_kb') / 1024 }} MB combined. Macros are never run.</div>
            </div>
            <p class="small text-body-secondary">Workbooks are deleted after reading, including on failure. Preview data expires after {{ config('timesheet_import.expires_after_minutes') }} minutes. Historical leave hours do not create leave records or deduct leave again.</p>
            <button class="btn btn-primary" type="submit">Upload and preview</button>
        </form>
    </section>
@elseif($batch['results'] !== null)
    <section class="content-card" aria-labelledby="results-heading">
        <div class="content-card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div><h2 class="h5 mb-1" id="results-heading">Import results</h2><div class="small text-body-secondary">{{ collect($batch['results'])->where('state', 'imported')->count() }} imported · {{ collect($batch['results'])->where('state', 'skipped')->count() }} skipped · {{ collect($batch['results'])->where('state', 'failed')->count() }} failed</div></div>
            <a class="btn btn-primary" href="{{ route('admin.timesheets.import.index') }}">Start another import</a>
        </div>
        <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Workbook</th><th>Employee / week beginning</th><th>Result</th><th>Details</th><th>Actions</th></tr></thead><tbody>
            @forelse($batch['results'] as $result)
                <tr><td class="text-break">{{ $result['filename'] }}</td><td>{{ $result['employee_code'] }}<div class="small text-body-secondary">{{ $result['start_date'] }}</div></td><td><span class="badge text-bg-{{ $result['state'] === 'imported' ? 'success' : ($result['state'] === 'skipped' ? 'secondary' : 'danger') }}">{{ ucfirst($result['state']) }}</span></td><td>{{ $result['message'] }}</td><td>@if($result['timesheet_id'])<a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.timesheets.show', $result['timesheet_id']) }}">View timesheet</a>@endif</td></tr>
            @empty
                <tr><td colspan="5" class="empty-state">This import was cancelled. Start another import to select workbooks.</td></tr>
            @endforelse
        </tbody></table></div>
        <div class="content-card-body small text-body-secondary">Existing timesheets were preserved. Correct any failed workbooks or project allocations before retrying. Batch: {{ $batchId }}</div>
    </section>
@else
    @php($readyCount = collect($batch['review'])->where('state', 'ready')->count())
    <div class="alert alert-info">{{ $readyCount }} of {{ count($batch['items']) }} timesheets are ready. Conflicts are skipped; blocked timesheets are not imported. Review them below before confirming. Preview expires {{ \Carbon\Carbon::createFromTimestamp($batch['expires_at'], config('app.timezone'))->format('g:i A T') }}.</div>
    <section class="content-card mb-3" aria-labelledby="batch-heading">
        <div class="content-card-header"><h2 class="h5 mb-1" id="batch-heading">Workbook review</h2><p class="small text-body-secondary mb-0">Files are checked in the order shown. Ready timesheets reserve allocation capacity in this preview.</p></div>
        <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Workbook / employee</th><th>Week beginning</th><th>Check</th><th>Actions</th></tr></thead><tbody>
            @foreach($batch['review'] as $index => $review)
                <tr @if($index === $selected) class="table-active" @endif><td class="text-break">{{ $review['filename'] }}<div class="small text-body-secondary">{{ $review['user']?->name ?? ($review['employee_name'] ?? 'Unreadable workbook') }} · {{ $review['employee_code'] ?? 'Unknown employee' }}</div></td><td>{{ $review['start_date'] ?? 'Unknown' }}</td><td><span class="badge text-bg-{{ $review['state'] === 'ready' ? 'success' : ($review['state'] === 'conflict' ? 'secondary' : 'danger') }}">{{ ucfirst($review['state']) }}</span>@if($review['warnings'])<div class="small text-body-secondary">{{ count($review['warnings']) }} warning(s)</div>@endif</td><td><a class="btn btn-sm btn-outline-secondary" @if($index === $selected) aria-current="true" @endif href="{{ route('admin.timesheets.import.show', ['batch' => $batchId, 'sheet' => $index]) }}#selected-workbook">Review workbook {{ $index + 1 }}</a></td></tr>
            @endforeach
        </tbody></table></div>
    </section>
    @php($item = $batch['review'][$selected])
    <section class="content-card mb-3" id="selected-workbook" aria-labelledby="selected-heading">
        <div class="content-card-header"><h2 class="h5 mb-1 text-break" id="selected-heading">{{ $item['filename'] }}</h2><p class="small text-body-secondary mb-0">Employee code: {{ $item['employee_code'] ?? 'Unknown' }}. Employee and week are fixed to the source workbook.</p></div>
        <div class="content-card-body">
            @if($item['errors'])<div class="alert alert-danger" role="alert"><p class="fw-semibold mb-1">Resolve before importing</p><ul class="mb-0">@foreach($item['errors'] as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            @if($item['warnings'])<div class="alert alert-warning"><p class="fw-semibold mb-1">Historical record warnings</p><ul class="mb-0">@foreach($item['warnings'] as $warning)<li>{{ $warning }}</li>@endforeach</ul></div>@endif
            @unless(isset($item['parse_error']))
                <p class="small text-body-secondary">Edit entries below, then save to update the display preview and allocation checks. Enter 0 in both hour fields to exclude an entry. Categories affect these historical entries only.</p>
                <form method="post" action="{{ route('admin.timesheets.import.update', ['batch' => $batchId, 'sheet' => $selected]) }}" id="entry-editor">
                    @csrf
                    <div class="row g-3 mb-3 align-items-end">
                        <div class="col-md-4"><label class="form-label" for="bulk-project">Apply category: project number</label><input class="form-control" id="bulk-project" list="import-projects"></div>
                        <div class="col-md-3"><label class="form-label" for="bulk-department">Discipline</label><select data-searchable="false" class="form-select" id="bulk-department">@foreach($departments as $department)<option value="{{ $department->id }}">{{ $department->name }}</option>@endforeach</select></div>
                        <div class="col-md-3"><label class="form-label" for="bulk-category">Manpower category</label><select data-searchable="false" class="form-select" id="bulk-category"><option value="">No category</option>@foreach(config('manpower_categories.labels') as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                        <div class="col-md-2"><button class="btn btn-outline-secondary" type="button" id="apply-category">Apply to matching entries</button></div>
                    </div>
                    <div class="table-responsive mb-3"><table class="table align-middle mb-0 import-editor"><caption class="small">Editable daily entries for {{ $item['employee_code'] }}. Required: date, attendance, discipline, and hours; project and category depend on the discipline.</caption><thead><tr><th>Date</th><th>Attendance</th><th>Project / job</th><th>Discipline</th><th>Manpower category</th><th>Regular</th><th>Overtime</th><th>Remarks</th></tr></thead><tbody>
                        @php($editableEntries = array_values(is_array(old('entries')) ? old('entries') : $item['entries']))
                        @foreach($editableEntries as $i => $entry)
                            @php($entry = array_replace(['work_date' => '', 'source_row' => '', 'attendance_code' => '', 'project_code' => '', 'department_id' => '', 'manpower_category' => '', 'regular_hours' => '', 'overtime_hours' => '', 'remarks' => ''], array_map(fn ($value) => is_scalar($value) ? $value : '', is_array($entry) ? $entry : [])))
                            <tr data-entry>
                                <td><label class="visually-hidden" for="entry-{{ $i }}-date">Entry {{ $i + 1 }} date</label><input class="form-control form-control-sm" id="entry-{{ $i }}-date" type="date" name="entries[{{ $i }}][work_date]" value="{{ $entry['work_date'] }}" min="{{ $item['start_date'] }}" max="{{ \Carbon\Carbon::parse($item['start_date'])->addDays(6)->toDateString() }}" required><input type="hidden" name="entries[{{ $i }}][source_row]" value="{{ $entry['source_row'] ?? '' }}"></td>
                                <td><label class="visually-hidden" for="entry-{{ $i }}-code">Entry {{ $i + 1 }} attendance</label><select data-searchable="false" class="form-select form-select-sm" id="entry-{{ $i }}-code" name="entries[{{ $i }}][attendance_code]" required><option value="">Select code</option>@foreach(config('timesheet.attendance_codes') as $code => $label)<option value="{{ $code }}" @selected($entry['attendance_code'] === $code)>{{ $code }} — {{ $label }}</option>@endforeach</select></td>
                                <td><label class="visually-hidden" for="entry-{{ $i }}-project">Entry {{ $i + 1 }} project</label><input class="form-control form-control-sm" id="entry-{{ $i }}-project" name="entries[{{ $i }}][project_code]" value="{{ $entry['project_code'] }}" list="import-projects" maxlength="100" data-project></td>
                                <td><label class="visually-hidden" for="entry-{{ $i }}-department">Entry {{ $i + 1 }} discipline</label><select data-searchable="false" class="form-select form-select-sm" id="entry-{{ $i }}-department" name="entries[{{ $i }}][department_id]" required data-department><option value="">Select discipline</option>@foreach($departments as $department)<option value="{{ $department->id }}" @selected((int) $entry['department_id'] === $department->id)>{{ $department->name }}{{ $department->is_active ? '' : ' (inactive)' }}</option>@endforeach</select></td>
                                <td><label class="visually-hidden" for="entry-{{ $i }}-category">Entry {{ $i + 1 }} manpower category</label><select data-searchable="false" class="form-select form-select-sm" id="entry-{{ $i }}-category" name="entries[{{ $i }}][manpower_category]" data-category><option value="">Select if required</option>@foreach(config('manpower_categories.labels') as $value => $label)<option value="{{ $value }}" @selected(($entry['manpower_category'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></td>
                                <td><label class="visually-hidden" for="entry-{{ $i }}-regular">Entry {{ $i + 1 }} regular hours</label><input class="form-control form-control-sm" id="entry-{{ $i }}-regular" name="entries[{{ $i }}][regular_hours]" type="number" min="0" max="24" step="0.01" value="{{ $entry['regular_hours'] }}" required></td>
                                <td><label class="visually-hidden" for="entry-{{ $i }}-overtime">Entry {{ $i + 1 }} overtime hours</label><input class="form-control form-control-sm" id="entry-{{ $i }}-overtime" name="entries[{{ $i }}][overtime_hours]" type="number" min="0" max="24" step="0.01" value="{{ $entry['overtime_hours'] }}" required></td>
                                <td><label class="visually-hidden" for="entry-{{ $i }}-remarks">Entry {{ $i + 1 }} remarks</label><textarea class="form-control form-control-sm" id="entry-{{ $i }}-remarks" name="entries[{{ $i }}][remarks]" maxlength="2000" rows="2">{{ $entry['remarks'] }}</textarea></td>
                            </tr>
                        @endforeach
                    </tbody></table></div>
                    <input type="hidden" name="revision" value="{{ $batch['revision'] }}">
                    <input type="hidden" name="entry_count" value="{{ count($editableEntries) }}">
                    <div class="d-flex flex-wrap gap-2 align-items-center"><button class="btn btn-primary" type="submit">Save changes and refresh preview</button><span class="small text-body-secondary" id="editor-status" role="status" aria-live="polite"></span></div>
                </form>
                <datalist id="import-projects">@foreach($projects as $project)<option value="{{ $project->project_code }}">{{ $project->project_name }}{{ $project->is_active ? '' : ' (inactive)' }}</option>@endforeach</datalist>
            @endunless
        </div>
    </section>
    @if($previewTimesheet)
        <details class="mb-3" open><summary class="h5 mb-3">Timesheet display preview (saved changes)</summary>@include('shared.timesheet_detail', ['timesheet' => $previewTimesheet, 'showTimesheetHistory' => false])</details>
    @endif
    <div class="content-card"><div class="content-card-body">
        <p class="mb-2">Import {{ $readyCount }} ready timesheet(s) as approved. Original approval details remain unknown. Every imported timesheet receives a history record. Leave balances are unchanged.</p>
        <p class="small text-body-secondary">Allocation capacity and existing timesheets are checked again on confirmation. Update project allocations before retrying blocked workbooks.</p>
        <div class="d-flex flex-wrap gap-2">
            <form method="post" action="{{ route('admin.timesheets.import.confirm', $batchId) }}">@csrf<input type="hidden" name="revision" value="{{ $batch['revision'] }}"><button class="btn btn-primary" type="submit" id="confirm-import" @disabled($readyCount === 0)>Import {{ $readyCount }} ready timesheet(s)</button></form>
            <form method="post" action="{{ route('admin.timesheets.import.cancel', $batchId) }}">@csrf @method('DELETE')<button class="btn btn-outline-secondary" type="submit">Cancel import</button></form>
        </div>
    </div></div>
@endif
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const editor = document.getElementById('entry-editor');
    const status = document.getElementById('editor-status');
    const confirm = document.getElementById('confirm-import');
    const dirty = () => { if (confirm) confirm.disabled = true; if (status) status.textContent = 'Unsaved changes. Save to refresh validation before importing.'; };
    editor?.addEventListener('input', dirty);
    editor?.addEventListener('change', dirty);
    document.getElementById('apply-category')?.addEventListener('click', () => {
        const project = document.getElementById('bulk-project').value.trim();
        const department = document.getElementById('bulk-department').value;
        const category = document.getElementById('bulk-category').value;
        let count = 0;
        editor.querySelectorAll('[data-entry]').forEach(row => {
            if (row.querySelector('[data-project]').value.trim() === project && row.querySelector('[data-department]').value === department) {
                row.querySelector('[data-category]').value = category;
                count++;
            }
        });
        if (count) dirty();
        status.textContent = count ? `Category applied to ${count} entries. Save to refresh validation.` : 'No matching entries. Check the project number and discipline.';
    });
    // JSON avoids PHP max_input_vars truncation on larger editable timesheets.
    editor?.addEventListener('submit', async event => {
        event.preventDefault();
        const button = editor.querySelector('button[type="submit"]');
        button.disabled = true;
        status.textContent = 'Saving and checking entries…';
        const fields = new FormData(editor);
        const entries = [...editor.querySelectorAll('[data-entry]')].map(row => Object.fromEntries([...row.querySelectorAll('[name]')].map(input => [input.name.match(/\[([^\]]+)\]$/)[1], input.value])));
        try {
            const response = await fetch(editor.action, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': fields.get('_token') }, body: JSON.stringify({ entries, revision: fields.get('revision'), entry_count: entries.length }) });
            if (response.ok) { window.location.assign(response.url); return; }
            const result = await response.json().catch(() => ({}));
            status.textContent = result.errors ? Object.values(result.errors).flat().join(' ') : (result.message || 'The preview could not be saved. Reload it and try again.');
        } catch { status.textContent = 'Connection interrupted. Your edits remain here. Save again to check their status.'; }
        finally { button.disabled = false; }
    });
});
</script>
@endpush
