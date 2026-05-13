@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h3 mb-0">Projects / Job Numbers</h1><a class="btn btn-primary" href="{{ route('manage.projects.create') }}">New Project</a></div>
<div class="content-card p-3">
    <div class="table-responsive">
        <table class="table table-fixed mb-0">
            <thead><tr><th style="width: 9rem;">Code</th><th>Name</th><th style="width: 9rem;">Client</th><th style="width: 7rem;">Status</th><th style="width: 6rem;"></th></tr></thead>
            <tbody>
            @foreach($projects as $project)
                <tr>
                    <td>{{ $project->project_code }}</td>
                    <td class="text-truncate-cell" title="{{ $project->project_name }}">{{ $project->project_name }}</td>
                    <td>{{ $project->client_name }}</td>
                    <td>{{ $project->is_active ? 'Active' : 'Inactive' }}</td>
                    <td class="text-end"><a class="btn btn-sm btn-primary" href="{{ route('manage.projects.edit', $project) }}">Edit</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $projects->links() }}</div>
@endsection
