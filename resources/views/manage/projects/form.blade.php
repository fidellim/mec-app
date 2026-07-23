@extends('layouts.app')

@section('content')
<div class="section-header"><div><h1 class="h3 page-heading mb-1">{{ $project->exists ? 'Edit Project' : 'New Project' }}</h1><div class="text-muted">Maintain the project details and control who can charge time to it.</div></div></div>
<form class="content-card p-3" method="post" action="{{ $project->exists ? route('manage.projects.update', $project) : route('manage.projects.store') }}">
    @csrf @if($project->exists) @method('put') @endif
    <input type="hidden" name="assignment_import_token" value="{{ old('assignment_import_token') }}" data-assignment-import-token>
    <div class="row g-3">
        <div class="col-md-4"><label class="form-label">Project code</label><input class="form-control" name="project_code" value="{{ old('project_code', $project->project_code) }}" required></div>
        <div class="col-md-4"><label class="form-label">Project name</label><input class="form-control" name="project_name" value="{{ old('project_name', $project->project_name) }}" required></div>
        <div class="col-md-4"><label class="form-label">Client name</label><input class="form-control" name="client_name" value="{{ old('client_name', $project->client_name) }}"></div>
        <div class="col-md-4"><label class="form-label" for="start_date">Starting date</label><input class="form-control @error('start_date') is-invalid @enderror" id="start_date" type="date" name="start_date" value="{{ old('start_date', $project->start_date?->toDateString()) }}" required>@error('start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-md-8"><label class="form-label" for="project_manager_id">Project manager</label><select class="form-select @error('project_manager_id') is-invalid @enderror" id="project_manager_id" name="project_manager_id" required><option value="">Select an employee or HOD</option>@foreach($projectManagers as $manager)<option value="{{ $manager->id }}" @selected(old('project_manager_id', $project->project_manager_id) == $manager->id)>{{ $manager->name }} · {{ ucfirst($manager->role) }}{{ $manager->is_active ? '' : ' · inactive' }}</option>@endforeach</select>@error('project_manager_id')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-12"><input type="hidden" name="is_active" value="0"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="active" @checked(old('is_active', $project->is_active ?? true))><label class="form-check-label" for="active">Active</label></div></div>
        <div class="col-12">
            <fieldset class="border rounded-3 p-3">
                <legend class="h6 px-2">Department manhour allocations</legend>
                <p class="small text-muted">Set each discipline's lifetime budget. Optional Manpower Category controls divide it into protected reservations and a shared remainder. Submitted and approved hours both consume allocation.</p>
                @if(($legacyCategoryDepartmentIds ?? collect())->isNotEmpty())
                    <div class="alert alert-warning py-2" role="alert">
                        <div class="fw-semibold">Manpower Category setup needs review</div>
                        <div class="small">Legacy Job Level settings were preserved for history. Review the highlighted departments and save the four standard categories before employees submit new hours.</div>
                    </div>
                @endif
                <div class="row g-3">
                    @foreach($departments as $department)
                        @php
                            $allocationKey = 'department_allocations.'.$department->id;
                            $hours = old($allocationKey, $allocationHours->get($department->id));
                            $controlKey = 'job_level_controls.'.$department->id;
                            $controlsEnabled = (bool) old($controlKey, $controlledDepartmentIds->contains($department->id));
                            $storedCategories = $manpowerCategorySettings->get($department->id, collect());
                            $hasLegacyCategories = ($legacyCategoryDepartmentIds ?? collect())->contains($department->id);
                        @endphp
                        <div class="col-12" data-department-allocation>
                            <section class="border rounded-3 overflow-hidden">
                                <div class="p-3 bg-body-tertiary d-flex flex-column flex-lg-row gap-3 align-items-lg-end justify-content-between">
                                    <div class="flex-grow-1">
                                        <label class="form-label fw-semibold" for="allocation_{{ $department->id }}">{{ $department->name }}{{ ($department->is_active ?? true) ? '' : ' (inactive)' }} @if($hasLegacyCategories)<span class="badge text-bg-warning ms-1">Review categories</span>@endif</label>
                                        <div class="input-group" style="max-width: 280px;"><input class="form-control {{ $errors->has($allocationKey) ? 'is-invalid' : '' }}" id="allocation_{{ $department->id }}" name="department_allocations[{{ $department->id }}]" type="number" min="0.25" step="0.25" value="{{ $hours }}" placeholder="No allocation" data-department-hours><span class="input-group-text">hrs</span>@if($errors->has($allocationKey))<div class="invalid-feedback">{{ $errors->first($allocationKey) }}</div>@endif</div>
                                    </div>
                                    <div class="form-check form-switch mb-1">
                                        <input type="hidden" name="job_level_controls[{{ $department->id }}]" value="0">
                                        <input class="form-check-input" type="checkbox" role="switch" id="job_levels_{{ $department->id }}" name="job_level_controls[{{ $department->id }}]" value="1" @checked($controlsEnabled) data-job-level-toggle>
                                        <label class="form-check-label fw-semibold" for="job_levels_{{ $department->id }}">Control by Manpower Category</label>
                                    </div>
                                </div>
                                <div class="p-3 border-top {{ $controlsEnabled ? '' : 'd-none' }}" data-job-level-panel>
                                    <div class="small text-muted mb-3">Shared categories use the remainder together. Reserved hours are protected for one category. Not allowed categories cannot be selected on a timesheet.</div>
                                    <div class="row g-2">
                                        @foreach(config('manpower_categories.labels') as $manpowerCategory => $manpowerCategoryLabel)
                                            @php
                                                $modeKey = "job_level_allocations.{$department->id}.{$manpowerCategory}.mode";
                                                $hoursKey = "job_level_allocations.{$department->id}.{$manpowerCategory}.hours";
                                                $storedHasCategory = $storedCategories instanceof \Illuminate\Support\Collection ? $storedCategories->has($manpowerCategory) : array_key_exists($manpowerCategory, (array) $storedCategories);
                                                $storedValue = $storedHasCategory ? ($storedCategories instanceof \Illuminate\Support\Collection ? $storedCategories->get($manpowerCategory) : $storedCategories[$manpowerCategory]) : null;
                                                $defaultMode = ! $storedHasCategory || $storedValue === null ? 'shared' : ((float) $storedValue === 0.0 ? 'not_allowed' : 'reserved');
                                                $mode = old($modeKey, $defaultMode);
                                                $categoryHours = old($hoursKey, $defaultMode === 'reserved' ? $storedValue : null);
                                            @endphp
                                            <div class="col-md-6 col-xl-4">
                                                <div class="border rounded-2 p-2 h-100" data-job-level-row>
                                                    <label class="form-label small fw-semibold" for="mode_{{ $department->id }}_{{ $manpowerCategory }}">{{ $manpowerCategoryLabel }}</label>
                                                    <div class="input-group input-group-sm">
                                                        <select class="form-select" id="mode_{{ $department->id }}_{{ $manpowerCategory }}" name="job_level_allocations[{{ $department->id }}][{{ $manpowerCategory }}][mode]" data-job-level-mode>
                                                            <option value="shared" @selected($mode === 'shared')>Shared</option>
                                                            <option value="reserved" @selected($mode === 'reserved')>Reserved</option>
                                                            <option value="not_allowed" @selected($mode === 'not_allowed')>Not allowed</option>
                                                        </select>
                                                        <input class="form-control {{ $errors->has($hoursKey) ? 'is-invalid' : '' }}" name="job_level_allocations[{{ $department->id }}][{{ $manpowerCategory }}][hours]" type="number" min="0.25" step="0.25" value="{{ $categoryHours }}" placeholder="Hours" data-job-level-hours>
                                                        <span class="input-group-text">hrs</span>
                                                    </div>
                                                    @if($errors->has($hoursKey))<div class="text-danger small mt-1">{{ $errors->first($hoursKey) }}</div>@endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                    @if($errors->has('job_level_allocations.'.$department->id))<div class="text-danger small mt-2">{{ $errors->first('job_level_allocations.'.$department->id) }}</div>@endif
                                </div>
                            </section>
                        </div>
                    @endforeach
                </div>
                @error('department_allocations')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                @if($project->exists)
                    <div class="mt-3">
                        <label class="form-label" for="allocation_change_reason">Allocation change reason</label>
                        <textarea class="form-control @error('allocation_change_reason') is-invalid @enderror" id="allocation_change_reason" name="allocation_change_reason" rows="2" maxlength="2000" placeholder="Required when department totals or Manpower Category controls change">{{ old('allocation_change_reason') }}</textarea>
                        <div class="form-text">Stored in the audit log whenever an allocation changes.</div>
                        @error('allocation_change_reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                @endif
            </fieldset>
        </div>
        @php
            $assignmentMode = old('timesheet_assignment_mode', $project->timesheet_assignment_mode ?? \App\Models\Project::ASSIGNMENT_SELECTED_USERS);
            $selectedUserIds = collect(old('assigned_user_ids', $assignedUserIds))->map(fn ($id) => (int) $id);
            $selectedUserCategories = collect(old('assigned_user_categories', $assignedUserCategories ?? collect()));
            $unconfirmedAssignedUserCount = $selectedUserIds->filter(fn ($userId) => blank($selectedUserCategories->get($userId)))->count();
        @endphp
        <div class="col-12">
            <fieldset>
                <legend class="h6 mb-1">Timesheet access</legend>
                <p class="small text-muted mb-3">Choose who can select this project when entering their own timesheet. Approval and reporting access are unchanged.</p>
                <div class="row g-2">
                    <div class="col-lg-6">
                        <label class="border rounded-3 p-3 d-flex gap-3 h-100" for="assignment-all-users">
                            <input class="form-check-input mt-1" type="radio" name="timesheet_assignment_mode" id="assignment-all-users" value="{{ \App\Models\Project::ASSIGNMENT_ALL_USERS }}" @checked($assignmentMode === \App\Models\Project::ASSIGNMENT_ALL_USERS)>
                            <span><span class="d-block fw-semibold">All timesheet users</span><span class="d-block small text-muted mt-1">Every active employee, HOD, admin, and super-admin can charge time. New users are included automatically.</span></span>
                        </label>
                    </div>
                    <div class="col-lg-6">
                        <label class="border rounded-3 p-3 d-flex gap-3 h-100" for="assignment-selected-users">
                            <input class="form-check-input mt-1" type="radio" name="timesheet_assignment_mode" id="assignment-selected-users" value="{{ \App\Models\Project::ASSIGNMENT_SELECTED_USERS }}" @checked($assignmentMode === \App\Models\Project::ASSIGNMENT_SELECTED_USERS)>
                            <span><span class="d-block fw-semibold">Selected users</span><span class="d-block small text-muted mt-1">Only selected employees and HODs can charge time. An empty selection blocks everyone.</span></span>
                        </label>
                    </div>
                </div>
                <div class="small text-muted mt-2 d-none" data-controlled-access-note>Manpower Category controls require Selected users access.</div>
                @error('timesheet_assignment_mode')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
            </fieldset>
        </div>
        <div class="col-12" data-assignment-picker
             data-project-id="{{ $project->exists ? $project->id : '' }}"
             data-import-preview-url="{{ route('manage.projects.assignment-import.preview') }}">
            <div class="border rounded-3 p-3">
                @if($project->exists && ($controlledDepartmentIds ?? collect())->isNotEmpty() && $unconfirmedAssignedUserCount > 0)
                    <div class="alert alert-warning py-2" role="alert">
                        <div class="fw-semibold">Manpower Category assignments need review</div>
                        <div class="small">{{ $unconfirmedAssignedUserCount }} selected {{ $unconfirmedAssignedUserCount === 1 ? 'user has' : 'users have' }} no confirmed project category and can use uncontrolled departments only.</div>
                    </div>
                @endif
                @error('assignment_import_token')<div class="alert alert-danger py-2" role="alert">{{ $message }}</div>@enderror
                <section class="rounded-3 border bg-body-tertiary p-3 mb-3" aria-labelledby="assignment-import-heading">
                    <div class="d-flex flex-column flex-lg-row gap-3 justify-content-between align-items-lg-start">
                        <div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge text-bg-primary">Excel</span>
                                <h2 class="h6 mb-0" id="assignment-import-heading">Import project assignments</h2>
                            </div>
                            <p class="small text-muted mb-0 mt-2">Configure discipline allocations first, then preview an .xlsx roster. Nothing is saved until you apply the preview and save the project.</p>
                        </div>
                        <a class="btn btn-sm btn-outline-secondary flex-shrink-0" href="{{ route('manage.projects.assignment-template', $project->exists ? ['project_id' => $project->id] : []) }}">
                            Download Excel template
                        </a>
                    </div>
                    <div class="row g-2 align-items-end mt-1">
                        <div class="col-lg-7">
                            <label class="form-label small fw-semibold" for="assignment-import-file">Completed assignment template</label>
                            <input class="form-control form-control-sm" id="assignment-import-file" type="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" data-assignment-import-file>
                        </div>
                        <div class="col-lg-auto">
                            <button class="btn btn-sm btn-primary w-100" type="button" data-preview-assignment-import disabled>
                                Preview import
                            </button>
                        </div>
                    </div>
                    <div class="small text-muted mt-2" data-assignment-import-status>Enter at least one discipline allocation to enable import.</div>
                    <div class="d-none mt-3" data-assignment-import-preview aria-live="polite">
                        <div class="alert alert-danger py-2 d-none" data-import-global-errors role="alert"></div>
                        <div class="row g-2 mb-3" data-import-summary></div>
                        <div class="d-flex flex-column flex-sm-row gap-2 justify-content-between align-items-sm-center mb-2">
                            <div class="small fw-semibold">Assignment changes</div>
                            <button class="btn btn-sm btn-link p-0 d-none" type="button" data-toggle-unchanged>Show unchanged rows</button>
                        </div>
                        <div class="table-responsive border rounded-2">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Current</th>
                                        <th>Imported</th>
                                        <th>Result</th>
                                        <th>Issues</th>
                                    </tr>
                                </thead>
                                <tbody data-import-preview-rows></tbody>
                            </table>
                        </div>
                        <div class="d-flex flex-column flex-sm-row gap-2 justify-content-between align-items-sm-center mt-3">
                            <div class="small text-muted" data-import-preview-message></div>
                            <div class="d-flex gap-2">
                                <button class="btn btn-sm btn-outline-secondary" type="button" data-close-import-preview>Close preview</button>
                                <button class="btn btn-sm btn-primary" type="button" data-apply-assignment-import disabled>Apply to form</button>
                            </div>
                        </div>
                    </div>
                </section>
                <div class="d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center mb-3">
                    <div><div class="fw-semibold">Assigned users</div><div class="small text-muted" data-assignment-summary>{{ $selectedUserIds->count() }} selected</div></div>
                    <input class="form-control form-control-sm" type="search" placeholder="Find by name, employee number, email, role, or department" aria-label="Find users" data-user-filter style="max-width: 420px;">
                </div>
                <div class="rounded-3 border bg-body-tertiary p-3 mb-3">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-7 col-xl-5">
                            <label class="form-label small fw-semibold" for="bulk-manpower-category">Set category for selected users</label>
                            <select class="form-select form-select-sm" id="bulk-manpower-category" data-bulk-manpower-category data-searchable="false">
                                <option value="">Uncontrolled departments only</option>
                                @foreach(config('manpower_categories.labels') as $category => $label)
                                    <option value="{{ $category }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-auto">
                            <button class="btn btn-sm btn-outline-primary w-100" type="button" data-apply-bulk-manpower-category>Apply to selected users</button>
                        </div>
                    </div>
                    <div class="small text-muted mt-2">A category gives access to controlled departments where that category is Shared or Reserved. Users without a category can use uncontrolled departments only.</div>
                </div>
                <div class="d-grid gap-3" data-user-list>
                    @forelse($timesheetUsers as $departmentKey => $departmentUsers)
                        @php
                            $departmentName = $departmentUsers->first()->department?->name ?? 'No department assigned';
                            $departmentControlId = 'department-users-'.preg_replace('/[^a-zA-Z0-9_-]/', '-', (string) $departmentKey);
                        @endphp
                        <section class="border rounded-3 overflow-hidden" data-department-group data-department-search="{{ strtolower($departmentName) }}">
                            <div class="d-flex flex-column flex-sm-row gap-2 align-items-sm-center justify-content-between p-3 bg-body-tertiary border-bottom">
                                <label class="form-check mb-0 fw-semibold" for="{{ $departmentControlId }}">
                                    <input class="form-check-input" type="checkbox" id="{{ $departmentControlId }}" data-department-toggle>
                                    {{ $departmentName }}
                                </label>
                                <span class="small text-muted" data-department-summary></span>
                            </div>
                            <div class="row g-2 p-3" data-department-users>
                                @foreach($departmentUsers as $user)
                                    <div class="col-md-6 col-xl-4" data-user-choice data-user-search="{{ strtolower($user->name.' '.$user->email.' '.$user->employee_code.' '.$user->role.' '.$departmentName) }}">
                                        @php($selectedCategory = old("assigned_user_categories.{$user->id}", $selectedUserCategories->get($user->id)))
                                        <div class="border rounded-2 p-2 h-100">
                                            <label class="form-check mb-2">
                                                <input class="form-check-input ms-0 me-2" type="checkbox" name="assigned_user_ids[]" value="{{ $user->id }}" data-user-checkbox @checked($selectedUserIds->contains($user->id))>
                                                <span class="d-inline-block"><span class="d-block fw-medium">{{ $user->name }}</span><span class="d-block small text-muted">{{ $user->employee_code }} · {{ ucfirst($user->role) }}</span><span class="d-block small text-muted">{{ $user->email }}</span></span>
                                            </label>
                                            <label class="form-label small mb-1" for="assigned-user-category-{{ $user->id }}">Project Manpower Category</label>
                                            <select class="form-select form-select-sm {{ $errors->has("assigned_user_categories.{$user->id}") ? 'is-invalid' : '' }}" id="assigned-user-category-{{ $user->id }}" name="assigned_user_categories[{{ $user->id }}]" data-user-manpower-category data-searchable="false" @disabled(! $selectedUserIds->contains($user->id))>
                                                <option value="">Uncontrolled departments only</option>
                                                @foreach(config('manpower_categories.labels') as $category => $label)
                                                    <option value="{{ $category }}" @selected($selectedCategory === $category)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            @if($errors->has("assigned_user_categories.{$user->id}"))<div class="invalid-feedback">{{ $errors->first("assigned_user_categories.{$user->id}") }}</div>@endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @empty
                        <div class="text-muted small">No active employees or HODs are available.</div>
                    @endforelse
                </div>
                @error('assigned_user_ids')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                @error('assigned_user_ids.*')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                @error('assigned_user_categories')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
    <div class="d-flex justify-content-between mt-3">@if($project->exists)<a class="btn btn-outline-secondary" href="{{ route('projects.utilization', $project) }}">View utilization</a>@else<span></span>@endif<button class="btn btn-primary">Save Project</button></div>
</form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const allUsersMode = document.getElementById('assignment-all-users');
    const selectedUsersMode = document.getElementById('assignment-selected-users');
    const controlledAccessNote = document.querySelector('[data-controlled-access-note]');
    const refreshAccessConstraint = () => {
        const hasCategoryControls = [...document.querySelectorAll('[data-department-allocation]')].some(allocation => {
            const toggle = allocation.querySelector('[data-job-level-toggle]');
            const hours = allocation.querySelector('[data-department-hours]');
            return toggle.checked && hours.value !== '';
        });
        allUsersMode.disabled = hasCategoryControls;
        if (hasCategoryControls) selectedUsersMode.checked = true;
        controlledAccessNote.classList.toggle('d-none', ! hasCategoryControls);
        document.querySelectorAll('input[name="timesheet_assignment_mode"]').forEach(input => input.dispatchEvent(new Event('manpower-access-change')));
    };

    document.querySelectorAll('[data-department-allocation]').forEach(allocation => {
        const toggle = allocation.querySelector('[data-job-level-toggle]');
        const panel = allocation.querySelector('[data-job-level-panel]');
        const departmentHours = allocation.querySelector('[data-department-hours]');
        const refresh = () => {
            const enabled = toggle.checked && departmentHours.value !== '';
            panel.classList.toggle('d-none', ! enabled);
            panel.querySelectorAll('[data-job-level-row]').forEach(row => {
                const mode = row.querySelector('[data-job-level-mode]');
                const hours = row.querySelector('[data-job-level-hours]');
                mode.disabled = ! enabled;
                hours.disabled = ! enabled || mode.value !== 'reserved';
            });
        };
        toggle.addEventListener('change', refresh);
        departmentHours.addEventListener('input', refresh);
        toggle.addEventListener('change', refreshAccessConstraint);
        departmentHours.addEventListener('input', refreshAccessConstraint);
        panel.querySelectorAll('[data-job-level-mode]').forEach(mode => mode.addEventListener('change', refresh));
        refresh();
    });

    const picker = document.querySelector('[data-assignment-picker]');
    if (! picker) return;

    const modeInputs = document.querySelectorAll('input[name="timesheet_assignment_mode"]');
    const userCheckboxes = picker.querySelectorAll('[data-user-checkbox]');
    const departmentGroups = picker.querySelectorAll('[data-department-group]');
    const summary = picker.querySelector('[data-assignment-summary]');
    const filter = picker.querySelector('[data-user-filter]');
    const bulkCategory = picker.querySelector('[data-bulk-manpower-category]');
    const applyBulkCategory = picker.querySelector('[data-apply-bulk-manpower-category]');
    const projectForm = picker.closest('form');
    const importFile = picker.querySelector('[data-assignment-import-file]');
    const previewImportButton = picker.querySelector('[data-preview-assignment-import]');
    const importStatus = picker.querySelector('[data-assignment-import-status]');
    const importPreview = picker.querySelector('[data-assignment-import-preview]');
    const importGlobalErrors = picker.querySelector('[data-import-global-errors]');
    const importSummary = picker.querySelector('[data-import-summary]');
    const importRows = picker.querySelector('[data-import-preview-rows]');
    const importMessage = picker.querySelector('[data-import-preview-message]');
    const applyImportButton = picker.querySelector('[data-apply-assignment-import]');
    const closeImportButton = picker.querySelector('[data-close-import-preview]');
    const toggleUnchangedButton = picker.querySelector('[data-toggle-unchanged]');
    const importToken = projectForm.querySelector('[data-assignment-import-token]');
    let lastImportPreview = null;
    let showUnchangedRows = false;
    let importLoading = false;

    const setCategoryEnabled = (checkbox) => {
        const category = checkbox.closest('[data-user-choice]')?.querySelector('[data-user-manpower-category]');
        if (! category) return;
        category.disabled = ! checkbox.checked;
        if (category.tomselect) checkbox.checked ? category.tomselect.enable() : category.tomselect.disable();
    };

    const refresh = () => {
        const selectedMode = document.querySelector('input[name="timesheet_assignment_mode"]:checked')?.value;
        const selectedCheckboxes = [...userCheckboxes].filter(input => input.checked);
        const uncontrolledOnlyCount = selectedCheckboxes.filter(input => ! input.closest('[data-user-choice]')?.querySelector('[data-user-manpower-category]')?.value).length;
        picker.classList.toggle('opacity-75', selectedMode === 'all_users');
        summary.textContent = selectedMode === 'all_users'
            ? `All active timesheet users (${selectedCheckboxes.length} saved selections retained)`
            : `${selectedCheckboxes.length} selected · ${uncontrolledOnlyCount} uncontrolled-only`;

        userCheckboxes.forEach(setCategoryEnabled);

        departmentGroups.forEach(group => {
            const departmentCheckboxes = [...group.querySelectorAll('[data-user-checkbox]')];
            const selectedCount = departmentCheckboxes.filter(input => input.checked).length;
            const toggle = group.querySelector('[data-department-toggle]');
            toggle.checked = selectedCount === departmentCheckboxes.length && departmentCheckboxes.length > 0;
            toggle.indeterminate = selectedCount > 0 && selectedCount < departmentCheckboxes.length;
            group.querySelector('[data-department-summary]').textContent = `${selectedCount} of ${departmentCheckboxes.length} selected`;
        });
    };

    const hasDisciplineAllocation = () => [...document.querySelectorAll('[data-department-hours]')]
        .some(input => Number.parseFloat(input.value) > 0);

    const refreshImportAvailability = () => {
        const hasAllocation = hasDisciplineAllocation();
        const hasFile = Boolean(importFile?.files?.length);
        if (previewImportButton) previewImportButton.disabled = importLoading || ! hasAllocation || ! hasFile;
        if (! importStatus || importLoading) return;
        if (! hasAllocation) {
            importStatus.textContent = 'Enter at least one discipline allocation to enable import.';
        } else if (! hasFile) {
            importStatus.textContent = 'Choose the completed .xlsx template to prepare a preview.';
        } else {
            importStatus.textContent = 'Ready to validate the workbook against the current allocations.';
        }
    };

    const element = (tag, className, text) => {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    };

    const issueList = (messages, className) => {
        const wrapper = element('div');
        messages.forEach(message => wrapper.append(element('div', className, message)));
        return wrapper;
    };

    const renderImportPreview = (preview) => {
        lastImportPreview = preview;
        showUnchangedRows = false;
        importPreview.classList.remove('d-none');
        importGlobalErrors.classList.toggle('d-none', !(preview.errors || []).length);
        importGlobalErrors.replaceChildren();
        if ((preview.errors || []).length) {
            importGlobalErrors.append(issueList(preview.errors, 'small'));
        }

        const summaryItems = [
            ['assigned', 'New assignments'],
            ['category_changed', 'Category changes'],
            ['removed', 'Removals'],
            ['uncontrolled_only', 'Uncontrolled-only'],
            ['unchanged', 'Unchanged'],
            ['errors', 'Errors'],
        ];
        importSummary.replaceChildren();
        summaryItems.forEach(([key, label]) => {
            const column = element('div', 'col-6 col-md-4 col-xl-2');
            const card = element('div', `border rounded-2 p-2 h-100 bg-body${key === 'errors' && preview.summary[key] ? ' border-danger' : ''}`);
            card.append(element('div', 'fs-5 fw-semibold', String(preview.summary[key] || 0)));
            card.append(element('div', 'small text-muted', label));
            column.append(card);
            importSummary.append(column);
        });

        importRows.replaceChildren();
        let hiddenUnchangedCount = 0;
        (preview.rows || []).forEach(row => {
            const tr = element('tr');
            const hasIssues = (row.errors || []).length || (row.warnings || []).length;
            const isQuietUnchanged = row.change === 'unchanged' && ! hasIssues;
            if (isQuietUnchanged) {
                tr.dataset.unchangedRow = '';
                tr.classList.toggle('d-none', ! showUnchangedRows);
                hiddenUnchangedCount += 1;
            }
            if ((row.errors || []).length) tr.classList.add('table-danger');

            const employeeCell = element('td');
            employeeCell.append(element('div', 'fw-medium', row.employee_name));
            employeeCell.append(element('div', 'small text-muted', `${row.employee_code || 'No employee number'} · ${row.department}`));
            employeeCell.append(element('div', 'small text-muted', `Excel row ${row.row_number}`));
            tr.append(employeeCell);
            tr.append(element('td', 'small', row.current_category_label));
            tr.append(element('td', 'small', row.category_label));

            const resultCell = element('td');
            const badgeClass = {
                assigned: 'text-bg-primary',
                category_changed: 'text-bg-info',
                removed: 'text-bg-danger',
                unchanged: 'text-bg-secondary',
            }[row.change] || 'text-bg-secondary';
            resultCell.append(element('span', `badge ${badgeClass}`, row.change_label));
            tr.append(resultCell);

            const issuesCell = element('td');
            if (!(row.errors || []).length && !(row.warnings || []).length) {
                issuesCell.append(element('span', 'small text-muted', '—'));
            } else {
                issuesCell.append(issueList(row.errors || [], 'small text-danger'));
                issuesCell.append(issueList(row.warnings || [], 'small text-warning-emphasis'));
            }
            tr.append(issuesCell);
            importRows.append(tr);
        });

        toggleUnchangedButton.classList.toggle('d-none', hiddenUnchangedCount === 0);
        toggleUnchangedButton.textContent = `Show unchanged rows (${hiddenUnchangedCount})`;
        applyImportButton.disabled = ! preview.valid;
        importMessage.textContent = preview.valid
            ? 'Validation passed. Apply these changes to the form, then save the project.'
            : 'Nothing has been applied. Correct every error in Excel and preview the file again.';
    };

    const validationMessages = (payload) => {
        if (Array.isArray(payload?.errors)) return payload.errors;
        if (payload?.errors && typeof payload.errors === 'object') return Object.values(payload.errors).flat();
        return [payload?.message || 'The Excel preview could not be prepared.'];
    };

    modeInputs.forEach(input => {
        input.addEventListener('change', refresh);
        input.addEventListener('manpower-access-change', refresh);
    });
    userCheckboxes.forEach(input => input.addEventListener('change', refresh));
    picker.querySelectorAll('[data-user-manpower-category]').forEach(input => input.addEventListener('change', refresh));
    applyBulkCategory?.addEventListener('click', () => {
        [...userCheckboxes].filter(input => input.checked).forEach(input => {
            const category = input.closest('[data-user-choice]')?.querySelector('[data-user-manpower-category]');
            if (! category) return;
            if (category.tomselect) category.tomselect.setValue(bulkCategory.value, true);
            else category.value = bulkCategory.value;
        });
        refresh();
    });
    departmentGroups.forEach(group => {
        group.querySelector('[data-department-toggle]').addEventListener('change', event => {
            group.querySelectorAll('[data-user-checkbox]').forEach(input => {
                input.checked = event.target.checked;
            });
            refresh();
        });
    });
    filter?.addEventListener('input', () => {
        const query = filter.value.trim().toLowerCase();
        departmentGroups.forEach(group => {
            let visibleUsers = 0;
            group.querySelectorAll('[data-user-choice]').forEach(choice => {
                const matches = choice.dataset.userSearch.includes(query);
                choice.classList.toggle('d-none', ! matches);
                visibleUsers += matches ? 1 : 0;
            });
            group.classList.toggle('d-none', visibleUsers === 0);
        });
    });
    document.querySelectorAll('[data-department-hours]').forEach(input => {
        input.addEventListener('input', refreshImportAvailability);
    });
    importFile?.addEventListener('change', () => {
        importPreview.classList.add('d-none');
        lastImportPreview = null;
        refreshImportAvailability();
    });
    previewImportButton?.addEventListener('click', async () => {
        if (! importFile.files.length || ! hasDisciplineAllocation() || importLoading) return;

        importLoading = true;
        previewImportButton.setAttribute('aria-busy', 'true');
        importStatus.textContent = 'Validating assignments and checking open timesheets…';
        refreshImportAvailability();

        const payload = new FormData(projectForm);
        payload.delete('_method');
        payload.set('assignment_file', importFile.files[0]);
        if (picker.dataset.projectId) payload.set('project_id', picker.dataset.projectId);
        else payload.delete('project_id');

        try {
            const response = await fetch(picker.dataset.importPreviewUrl, {
                method: 'POST',
                body: payload,
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            const result = await response.json().catch(() => ({
                message: 'The server returned an unreadable response.',
            }));
            if (! response.ok) {
                renderImportPreview({
                    valid: false,
                    errors: validationMessages(result),
                    rows: [],
                    summary: { errors: validationMessages(result).length },
                    token: null,
                });
                importStatus.textContent = 'The workbook could not be previewed.';
                return;
            }

            renderImportPreview(result);
            importStatus.textContent = result.valid
                ? 'Preview ready. Review the changes below.'
                : 'Preview found errors that must be corrected.';
        } catch (error) {
            renderImportPreview({
                valid: false,
                errors: ['The preview request failed. Check your connection and try again.'],
                rows: [],
                summary: { errors: 1 },
                token: null,
            });
            importStatus.textContent = 'The workbook could not be previewed.';
        } finally {
            importLoading = false;
            previewImportButton.removeAttribute('aria-busy');
            refreshImportAvailability();
        }
    });
    toggleUnchangedButton?.addEventListener('click', () => {
        showUnchangedRows = ! showUnchangedRows;
        importRows.querySelectorAll('[data-unchanged-row]').forEach(row => {
            row.classList.toggle('d-none', ! showUnchangedRows);
        });
        const count = importRows.querySelectorAll('[data-unchanged-row]').length;
        toggleUnchangedButton.textContent = showUnchangedRows
            ? 'Hide unchanged rows'
            : `Show unchanged rows (${count})`;
    });
    closeImportButton?.addEventListener('click', () => {
        importPreview.classList.add('d-none');
    });
    applyImportButton?.addEventListener('click', () => {
        if (! lastImportPreview?.valid || ! lastImportPreview.token) return;

        lastImportPreview.rows.forEach(row => {
            if (! row.user_id || row.assigned === null) return;
            const checkbox = [...userCheckboxes].find(input => Number(input.value) === Number(row.user_id));
            if (! checkbox) return;
            checkbox.checked = row.assigned === true;
            setCategoryEnabled(checkbox);
            const category = checkbox.closest('[data-user-choice]')?.querySelector('[data-user-manpower-category]');
            if (category) {
                const value = row.assigned === true ? (row.category || '') : '';
                if (category.tomselect) category.tomselect.setValue(value, true);
                else category.value = value;
            }
        });

        selectedUsersMode.checked = true;
        selectedUsersMode.dispatchEvent(new Event('change'));
        importToken.value = lastImportPreview.token;
        applyImportButton.disabled = true;
        importMessage.textContent = 'Applied to the project form. Review any manual changes, then select Save Project.';
        importStatus.textContent = 'Excel assignments are staged and will be saved with the project.';
        refresh();
    });
    refreshAccessConstraint();
    refresh();
    refreshImportAvailability();
});
</script>
@endpush
