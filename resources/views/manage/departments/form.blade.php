@extends('layouts.app')

@section('content')
<h1 class="h3 mb-3">{{ $department->exists ? 'Edit Department' : 'New Department' }}</h1>
<form class="content-card p-3" method="post" action="{{ $department->exists ? route('manage.departments.update', $department) : route('manage.departments.store') }}">
    @csrf @if($department->exists) @method('put') @endif
    <div class="row g-3">
        <div class="col-md-4"><label class="form-label">Name</label><input class="form-control" name="name" value="{{ old('name', $department->name) }}" required></div>
        <div class="col-md-4"><label class="form-label">Code</label><input class="form-control" name="code" value="{{ old('code', $department->code) }}"></div>
        <div class="col-md-4"><label class="form-label">Head of Department</label><select class="form-select" name="hod_id"><option value="">None</option>@foreach($hods as $hod)<option value="{{ $hod->id }}" @selected(old('hod_id', $department->hod_id) == $hod->id)>{{ $hod->name }}</option>@endforeach</select></div>
        <div class="col-12"><input type="hidden" name="is_active" value="0"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="active" @checked(old('is_active', $department->is_active ?? true))><label class="form-check-label" for="active">Active</label></div></div>
    </div>
    <div class="text-end mt-3"><button class="btn btn-primary">Save Department</button></div>
</form>
@endsection
