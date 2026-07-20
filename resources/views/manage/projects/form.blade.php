@extends('layouts.app')

@section('content')
<div class="section-header"><div><h1 class="h3 page-heading mb-1">{{ $project->exists ? 'Edit Project' : 'New Project' }}</h1><div class="text-muted">Maintain the project details and control who can charge time to it.</div></div></div>
<form class="content-card p-3" method="post" action="{{ $project->exists ? route('manage.projects.update', $project) : route('manage.projects.store') }}">
    @csrf @if($project->exists) @method('put') @endif
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
                <p class="small text-muted">Set each discipline's lifetime budget. Optional Job Level controls divide it into protected reservations and a shared remainder. Submitted and approved hours both consume allocation.</p>
                <div class="row g-3">
                    @foreach($departments as $department)
                        @php
                            $allocationKey = 'department_allocations.'.$department->id;
                            $hours = old($allocationKey, $allocationHours->get($department->id));
                            $controlKey = 'job_level_controls.'.$department->id;
                            $controlsEnabled = (bool) old($controlKey, $controlledDepartmentIds->contains($department->id));
                            $storedLevels = $jobLevelSettings->get($department->id, collect());
                        @endphp
                        <div class="col-12" data-department-allocation>
                            <section class="border rounded-3 overflow-hidden">
                                <div class="p-3 bg-body-tertiary d-flex flex-column flex-lg-row gap-3 align-items-lg-end justify-content-between">
                                    <div class="flex-grow-1">
                                        <label class="form-label fw-semibold" for="allocation_{{ $department->id }}">{{ $department->name }}{{ ($department->is_active ?? true) ? '' : ' (inactive)' }}</label>
                                        <div class="input-group" style="max-width: 280px;"><input class="form-control {{ $errors->has($allocationKey) ? 'is-invalid' : '' }}" id="allocation_{{ $department->id }}" name="department_allocations[{{ $department->id }}]" type="number" min="0.25" step="0.25" value="{{ $hours }}" placeholder="No allocation" data-department-hours><span class="input-group-text">hrs</span>@if($errors->has($allocationKey))<div class="invalid-feedback">{{ $errors->first($allocationKey) }}</div>@endif</div>
                                    </div>
                                    <div class="form-check form-switch mb-1">
                                        <input type="hidden" name="job_level_controls[{{ $department->id }}]" value="0">
                                        <input class="form-check-input" type="checkbox" role="switch" id="job_levels_{{ $department->id }}" name="job_level_controls[{{ $department->id }}]" value="1" @checked($controlsEnabled) data-job-level-toggle>
                                        <label class="form-check-label fw-semibold" for="job_levels_{{ $department->id }}">Control by Job Level</label>
                                    </div>
                                </div>
                                <div class="p-3 border-top {{ $controlsEnabled ? '' : 'd-none' }}" data-job-level-panel>
                                    <div class="small text-muted mb-3">Shared levels use the remainder together. Reserved hours are protected for one level. Not allowed levels cannot charge.</div>
                                    <div class="row g-2">
                                        @foreach(config('job_levels.labels') as $jobLevel => $jobLevelLabel)
                                            @php
                                                $modeKey = "job_level_allocations.{$department->id}.{$jobLevel}.mode";
                                                $hoursKey = "job_level_allocations.{$department->id}.{$jobLevel}.hours";
                                                $storedHasLevel = $storedLevels instanceof \Illuminate\Support\Collection ? $storedLevels->has($jobLevel) : array_key_exists($jobLevel, (array) $storedLevels);
                                                $storedValue = $storedHasLevel ? ($storedLevels instanceof \Illuminate\Support\Collection ? $storedLevels->get($jobLevel) : $storedLevels[$jobLevel]) : null;
                                                $defaultMode = ! $storedHasLevel || $storedValue === null ? 'shared' : ((float) $storedValue === 0.0 ? 'not_allowed' : 'reserved');
                                                $mode = old($modeKey, $defaultMode);
                                                $levelHours = old($hoursKey, $defaultMode === 'reserved' ? $storedValue : null);
                                            @endphp
                                            <div class="col-md-6 col-xl-4">
                                                <div class="border rounded-2 p-2 h-100" data-job-level-row>
                                                    <label class="form-label small fw-semibold" for="mode_{{ $department->id }}_{{ $jobLevel }}">{{ $jobLevelLabel }}</label>
                                                    <div class="input-group input-group-sm">
                                                        <select class="form-select" id="mode_{{ $department->id }}_{{ $jobLevel }}" name="job_level_allocations[{{ $department->id }}][{{ $jobLevel }}][mode]" data-job-level-mode>
                                                            <option value="shared" @selected($mode === 'shared')>Shared</option>
                                                            <option value="reserved" @selected($mode === 'reserved')>Reserved</option>
                                                            <option value="not_allowed" @selected($mode === 'not_allowed')>Not allowed</option>
                                                        </select>
                                                        <input class="form-control {{ $errors->has($hoursKey) ? 'is-invalid' : '' }}" name="job_level_allocations[{{ $department->id }}][{{ $jobLevel }}][hours]" type="number" min="0.25" step="0.25" value="{{ $levelHours }}" placeholder="Hours" data-job-level-hours>
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
                        <textarea class="form-control @error('allocation_change_reason') is-invalid @enderror" id="allocation_change_reason" name="allocation_change_reason" rows="2" maxlength="2000" placeholder="Required when department totals or Job Level controls change">{{ old('allocation_change_reason') }}</textarea>
                        <div class="form-text">Stored in the audit log whenever an allocation changes.</div>
                        @error('allocation_change_reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                @endif
            </fieldset>
        </div>
        @php
            $assignmentMode = old('timesheet_assignment_mode', $project->timesheet_assignment_mode ?? \App\Models\Project::ASSIGNMENT_SELECTED_USERS);
            $selectedUserIds = collect(old('assigned_user_ids', $assignedUserIds))->map(fn ($id) => (int) $id);
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
                @error('timesheet_assignment_mode')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
            </fieldset>
        </div>
        <div class="col-12" data-assignment-picker>
            <div class="border rounded-3 p-3">
                <div class="d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center mb-3">
                    <div><div class="fw-semibold">Assigned users</div><div class="small text-muted" data-assignment-summary>{{ $selectedUserIds->count() }} selected</div></div>
                    <input class="form-control form-control-sm" type="search" placeholder="Find by name, email, role, or department" aria-label="Find users" data-user-filter style="max-width: 360px;">
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
                                    <div class="col-md-6 col-xl-4" data-user-choice data-user-search="{{ strtolower($user->name.' '.$user->email.' '.$user->role.' '.$departmentName) }}">
                                        <label class="form-check border rounded-2 p-2 h-100">
                                            <input class="form-check-input ms-0 me-2" type="checkbox" name="assigned_user_ids[]" value="{{ $user->id }}" data-user-checkbox @checked($selectedUserIds->contains($user->id))>
                                            <span class="d-inline-block"><span class="d-block fw-medium">{{ $user->name }}</span><span class="d-block small text-muted">{{ $user->email }} · {{ ucfirst($user->role) }}</span></span>
                                        </label>
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
            </div>
        </div>
    </div>
    <div class="d-flex justify-content-between mt-3">@if($project->exists)<a class="btn btn-outline-secondary" href="{{ route('projects.utilization', $project) }}">View utilization</a>@else<span></span>@endif<button class="btn btn-primary">Save Project</button></div>
</form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
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

    const refresh = () => {
        const selectedMode = document.querySelector('input[name="timesheet_assignment_mode"]:checked')?.value;
        picker.classList.toggle('opacity-75', selectedMode === 'all_users');
        summary.textContent = selectedMode === 'all_users'
            ? `All active timesheet users (${[...userCheckboxes].filter(input => input.checked).length} saved selections retained)`
            : `${[...userCheckboxes].filter(input => input.checked).length} selected`;

        departmentGroups.forEach(group => {
            const departmentCheckboxes = [...group.querySelectorAll('[data-user-checkbox]')];
            const selectedCount = departmentCheckboxes.filter(input => input.checked).length;
            const toggle = group.querySelector('[data-department-toggle]');
            toggle.checked = selectedCount === departmentCheckboxes.length && departmentCheckboxes.length > 0;
            toggle.indeterminate = selectedCount > 0 && selectedCount < departmentCheckboxes.length;
            group.querySelector('[data-department-summary]').textContent = `${selectedCount} of ${departmentCheckboxes.length} selected`;
        });
    };

    modeInputs.forEach(input => input.addEventListener('change', refresh));
    userCheckboxes.forEach(input => input.addEventListener('change', refresh));
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
    refresh();
});
</script>
@endpush
