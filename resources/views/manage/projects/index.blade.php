@extends('layouts.app')

@section('content')
<div class="section-header">
    <div>
        <h1 class="h3 page-heading mb-1">Projects / Job Numbers</h1>
        <div class="text-muted">Keep job numbers available for history and archive inactive work.</div>
    </div>
    <a class="btn btn-primary" href="{{ route('manage.projects.create') }}">New Project</a>
</div>

@php($hasProjectFilters = filled($search) || filled($status))
<form class="filter-card mb-3" method="get" action="{{ route('manage.projects.index') }}">
    <div class="row g-3 align-items-end">
        <div class="col-12 col-lg-6">
            <label class="form-label small text-muted" for="search">Search projects</label>
            <input
                class="form-control @error('search') is-invalid @enderror"
                id="search"
                name="search"
                type="search"
                value="{{ $search }}"
                maxlength="100"
                placeholder="Project code, name, or client"
            >
            @error('search')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="col-12 col-md-6 col-lg-3">
            <label class="form-label small text-muted" for="status">Status</label>
            <select class="form-select @error('status') is-invalid @enderror" id="status" name="status">
                <option value="">All statuses</option>
                <option value="active" @selected($status === 'active')>Active</option>
                <option value="inactive" @selected($status === 'inactive')>Inactive</option>
            </select>
            @error('status')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="col-12 col-md-6 col-lg-3">
            <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-primary" type="submit">Apply Filters</button>
                @if($hasProjectFilters)
                    <a class="btn btn-outline-secondary" href="{{ route('manage.projects.index') }}">Clear</a>
                @endif
            </div>
        </div>
    </div>
</form>

<div class="content-card p-3 mb-3">
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
        <div>
            <div class="fw-semibold">Current view</div>
            <div class="small text-muted">{{ $hasProjectFilters ? 'Showing projects matching the selected filters.' : 'Showing all projects.' }}</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @if(filled($search))
                <span class="badge filter-summary-badge px-3 py-2">Search: {{ $search }}</span>
            @endif
            @if(filled($status))
                <span class="badge filter-summary-badge px-3 py-2">Status: {{ ucfirst($status) }}</span>
            @endif
            <span class="badge filter-summary-badge px-3 py-2">{{ $projects->total() }} {{ \Illuminate\Support\Str::plural('project', $projects->total()) }}</span>
        </div>
    </div>
</div>

<div class="content-card overflow-hidden">
    <div class="table-responsive">
        <table class="table table-fixed align-middle mb-0">
            <thead>
                <tr>
                    <th style="width: 9rem;">Code</th>
                    <th>Name</th>
                    <th style="width: 9rem;">Client</th>
                    <th style="width: 7rem;">Status</th>
                    <th style="width: 8rem;">Usage</th>
                    <th style="width: 17rem;"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($projects as $project)
                    @php($canDelete = $project->entries_count === 0)
                    <tr>
                        <td>{{ $project->project_code }}</td>
                        <td class="project-name-cell">{{ $project->project_name }}</td>
                        <td>{{ $project->client_name ?: '-' }}</td>
                        <td>
                            <span class="badge {{ $project->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                {{ $project->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="small text-muted">{{ $project->entries_count }} entries</td>
                        <td class="text-end">
                            <div class="action-group">
                                <a class="btn btn-sm btn-primary" href="{{ route('manage.projects.edit', $project) }}">Edit</a>
                                <form method="post" action="{{ route('manage.projects.status', $project) }}" data-confirm="{{ $project->is_active ? 'Deactivate this project? Existing records will remain visible.' : 'Reactivate this project?' }}">
                                    @csrf
                                    @method('patch')
                                    <button class="btn btn-sm btn-outline-secondary">{{ $project->is_active ? 'Deactivate' : 'Reactivate' }}</button>
                                </form>
                                @if(auth()->user()->isSuperAdmin())
                                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteProjectModal{{ $project->id }}">
                                        Delete
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="empty-state text-center">No projects match the selected filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if(auth()->user()->isSuperAdmin())
    @foreach($projects as $project)
        @php($canDelete = $project->entries_count === 0)
        <div class="modal fade" id="deleteProjectModal{{ $project->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="post" action="{{ route('manage.projects.destroy', $project) }}">
                    @csrf
                    @method('delete')
                    <div class="modal-header">
                        <h5 class="modal-title">Delete project</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2">Delete <strong>{{ $project->project_code }}</strong>?</p>
                        @if($canDelete)
                            <p class="text-muted mb-0">This project has no timesheet entries, so it can be permanently deleted.</p>
                        @else
                            <div class="alert alert-warning mb-0">
                                This project has timesheet entries. Deactivate it instead to keep historical timesheets and exports accurate.
                            </div>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger" @disabled(! $canDelete)>Delete Project</button>
                    </div>
                </form>
            </div>
        </div>
        </div>
    @endforeach
@endif

@include('shared.pagination-footer', ['paginator' => $projects, 'label' => 'project'])
@endsection
