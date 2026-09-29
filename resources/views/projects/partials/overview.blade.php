<div class="content-card p-3 mb-3">
    <div class="row g-3">
        <div class="col-sm-4">
            <div class="meta-label">Status</div>
            <span class="badge {{ $project->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $project->is_active ? 'Active' : 'Inactive' }}</span>
        </div>
        <div class="col-sm-8">
            <div class="meta-label">Timesheet access</div>
            <div class="meta-value">{{ $project->timesheet_assignment_mode === \App\Models\Project::ASSIGNMENT_ALL_USERS ? 'All users' : 'Selected users' }}</div>
            @unless($project->is_active)<div class="small text-muted mt-1">This project is inactive and unavailable for new timesheet selections.</div>@endunless
        </div>
    </div>
</div>
<div class="content-card mb-3">
    <div class="content-card-header">
        <h2 class="h5 mb-1">Department allocations</h2>
        <p class="small text-muted mb-0">Saved lifetime budgets and Manpower Category controls, including departments with no recorded hours.</p>
    </div>
    <div class="content-card-body">
        @forelse($project->departmentAllocations as $allocation)
            <section class="border rounded-3 p-3 {{ $loop->last ? '' : 'mb-3' }}">
                <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                    <h3 class="h6 mb-0">{{ $allocation->department->name }} <span class="text-muted">{{ $allocation->department->code }}</span></h3>
                    <span class="fw-semibold">{{ number_format((float) $allocation->allocated_hours, 2) }} hrs</span>
                </div>
                @if($allocation->manpowerCategoryAllocations->isNotEmpty())
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead><tr><th>Manpower Category</th><th>Access / allocation</th><th class="text-end">Reserved hours</th></tr></thead>
                            <tbody>
                                @foreach($allocation->manpowerCategoryAllocations as $category)
                                    <tr>
                                        <td>{{ config('manpower_categories.labels.'.$category->manpower_category, $category->manpower_category) }}</td>
                                        <td>{{ $category->allocated_hours === null ? 'Shared remainder' : ((float) $category->allocated_hours > 0 ? 'Reserved' : 'Not allowed') }}</td>
                                        <td class="text-end">{{ $category->allocated_hours === null ? '—' : number_format((float) $category->allocated_hours, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="small text-muted mt-2">Shared remainder: {{ number_format((float) $allocation->allocated_hours - (float) $allocation->manpowerCategoryAllocations->sum('allocated_hours'), 2) }} hrs</div>
                @else
                    <div class="small text-muted">Manpower Category controls are off.</div>
                @endif
            </section>
        @empty
            <p class="text-muted mb-0">No department allocations have been set.</p>
        @endforelse
    </div>
</div>
<div class="content-card overflow-hidden">
    <div class="content-card-header">
        <h2 class="h5 mb-1">Assigned employees <span class="badge text-bg-secondary">{{ $project->assignedUsers->count() }}</span></h2>
        <p class="small text-muted mb-0">{{ $project->timesheet_assignment_mode === \App\Models\Project::ASSIGNMENT_ALL_USERS ? 'All users access is enabled; individual assignments are not required.' : 'Saved assignments are shown even when an employee has not charged time to this project.' }}</p>
    </div>
    <div class="table-responsive">
        <table class="project-responsive-table table align-middle mb-0">
            <thead><tr><th>Employee</th><th>Department</th><th>Manpower Category</th><th>Status</th></tr></thead>
            <tbody>
                @forelse($project->assignedUsers as $employee)
                    <tr>
                        <td data-label="Employee">{{ $employee->name }}</td>
                        <td data-label="Department">{{ $employee->department?->name ?? 'Not assigned' }}</td>
                        <td data-label="Manpower Category">{{ $employee->pivot->manpower_category ? config('manpower_categories.labels.'.$employee->pivot->manpower_category, $employee->pivot->manpower_category) : 'Not set' }}</td>
                        <td data-label="Status"><span class="badge {{ $employee->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $employee->is_active ? 'Active' : 'Inactive' }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-4">No individual assignments.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
