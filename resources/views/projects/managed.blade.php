@extends('layouts.app')

@section('content')
<div class="section-header">
    <div>
        <h1 class="h3 page-heading mb-1">My Managed Projects</h1>
        <div class="text-muted">Open project utilization and monitor department manhour budgets.</div>
    </div>
</div>

<div class="content-card p-3 mb-3">
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
        <div><div class="fw-semibold">Project register</div><div class="small text-muted">Only projects assigned to you as project manager appear here.</div></div>
        <span class="badge filter-summary-badge px-3 py-2">{{ $projects->total() }} {{ \Illuminate\Support\Str::plural('project', $projects->total()) }}</span>
    </div>
</div>

<div class="content-card overflow-hidden">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Code</th><th>Project</th><th>Client</th><th>Starting date</th><th class="text-end">Disciplines</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($projects as $project)
                <tr>
                    <td class="fw-semibold">{{ $project->project_code }}</td>
                    <td class="project-name-cell">{{ $project->project_name }}</td>
                    <td>{{ $project->client_name ?: '-' }}</td>
                    <td>{{ $project->start_date?->toFormattedDateString() ?? 'Not set' }}</td>
                    <td class="text-end">{{ $project->department_allocations_count }}</td>
                    <td><span class="badge {{ $project->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $project->is_active ? 'Active' : 'Inactive' }}</span></td>
                    <td class="text-end"><a class="btn btn-sm btn-primary" href="{{ route('projects.utilization', $project) }}">View utilization</a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty-state text-center"><div class="fw-semibold mb-1">No managed projects</div><div class="small text-muted">Projects will appear here when an administrator assigns you as project manager.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@include('shared.pagination-footer', ['paginator' => $projects, 'label' => 'project'])
@endsection
