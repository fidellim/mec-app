@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h3 mb-0">Projects / Job Numbers</h1><a class="btn btn-primary" href="{{ route('manage.projects.create') }}">New Project</a></div>
<div class="content-card p-3"><table class="table mb-0"><thead><tr><th>Code</th><th>Name</th><th>Client</th><th>Status</th><th></th></tr></thead><tbody>
@foreach($projects as $project)<tr><td>{{ $project->project_code }}</td><td>{{ $project->project_name }}</td><td>{{ $project->client_name }}</td><td>{{ $project->is_active ? 'Active' : 'Inactive' }}</td><td class="text-end"><a class="btn btn-sm btn-primary" href="{{ route('manage.projects.edit', $project) }}">Edit</a></td></tr>@endforeach
</tbody></table></div><div class="mt-3">{{ $projects->links() }}</div>
@endsection
