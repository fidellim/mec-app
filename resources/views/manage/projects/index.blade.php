@extends('layouts.app')

@section('content')
<div class="section-header">
    <div>
        <h1 class="h3 page-heading mb-1">Projects / Job Numbers</h1>
        <div class="text-muted">Keep job numbers available for history and archive inactive work.</div>
    </div>
    <a class="btn btn-primary" href="{{ route('manage.projects.create') }}">New Project</a>
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
                @foreach($projects as $project)
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
                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteProjectModal{{ $project->id }}">
                                    Delete
                                </button>
                            </div>
                        </td>
                    </tr>

                @endforeach
            </tbody>
        </table>
    </div>
</div>

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

<div class="mt-3">{{ $projects->links() }}</div>
@endsection
