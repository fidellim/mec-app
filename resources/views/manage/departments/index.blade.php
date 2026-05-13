@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h3 mb-0">Departments</h1><a class="btn btn-primary" href="{{ route('manage.departments.create') }}">New Department</a></div>
<div class="content-card p-3"><table class="table mb-0"><thead><tr><th>Name</th><th>Code</th><th>HOD</th><th></th></tr></thead><tbody>
@foreach($departments as $department)<tr><td>{{ $department->name }}</td><td>{{ $department->code }}</td><td>{{ $department->hod?->name }}</td><td class="text-end"><a class="btn btn-sm btn-primary" href="{{ route('manage.departments.edit', $department) }}">Edit</a></td></tr>@endforeach
</tbody></table></div><div class="mt-3">{{ $departments->links() }}</div>
@endsection
