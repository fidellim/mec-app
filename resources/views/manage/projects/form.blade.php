@extends('layouts.app')

@section('content')
<div class="section-header"><div><h1 class="h3 page-heading mb-1">{{ $project->exists ? 'Edit Project' : 'New Project' }}</h1><div class="text-muted">Maintain project/job numbers available for timesheet entries.</div></div></div>
<form class="content-card p-3" method="post" action="{{ $project->exists ? route('manage.projects.update', $project) : route('manage.projects.store') }}">
    @csrf @if($project->exists) @method('put') @endif
    <div class="row g-3">
        <div class="col-md-4"><label class="form-label">Project code</label><input class="form-control" name="project_code" value="{{ old('project_code', $project->project_code) }}" required></div>
        <div class="col-md-4"><label class="form-label">Project name</label><input class="form-control" name="project_name" value="{{ old('project_name', $project->project_name) }}" required></div>
        <div class="col-md-4"><label class="form-label">Client name</label><input class="form-control" name="client_name" value="{{ old('client_name', $project->client_name) }}"></div>
        <div class="col-12"><input type="hidden" name="is_active" value="0"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="active" @checked(old('is_active', $project->is_active ?? true))><label class="form-check-label" for="active">Active</label></div></div>
    </div>
    <div class="text-end mt-3"><button class="btn btn-primary">Save Project</button></div>
</form>
@endsection
