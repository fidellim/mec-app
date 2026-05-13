@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h3 mb-0">Users</h1><a class="btn btn-primary" href="{{ route('manage.users.create') }}">New User</a></div>
<div class="content-card p-3"><table class="table mb-0"><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Department</th><th>Status</th><th></th></tr></thead><tbody>
@foreach($users as $user)<tr><td>{{ $user->name }}</td><td>{{ $user->email }}</td><td>{{ str_replace('_', ' ', $user->role) }}</td><td>{{ $user->department?->name }}</td><td>{{ $user->is_active ? 'Active' : 'Inactive' }}</td><td class="text-end"><a class="btn btn-sm btn-primary" href="{{ route('manage.users.edit', $user) }}">Edit</a></td></tr>@endforeach
</tbody></table></div><div class="mt-3">{{ $users->links() }}</div>
@endsection
