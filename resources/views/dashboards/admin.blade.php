@extends('layouts.app')

@section('content')
<h1 class="h3 mb-4">Admin Dashboard</h1>
<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="content-card p-3"><div class="text-muted">Submitted this week</div><div class="fs-3">{{ $summary['submitted'] }}</div></div></div>
    <div class="col-md-3"><div class="content-card p-3"><div class="text-muted">Approved this week</div><div class="fs-3">{{ $summary['approved'] }}</div></div></div>
    <div class="col-md-3"><div class="content-card p-3"><div class="text-muted">Rejected this week</div><div class="fs-3">{{ $summary['rejected'] }}</div></div></div>
    <div class="col-md-3"><div class="content-card p-3"><div class="text-muted">Missing submissions</div><div class="fs-3">{{ $missing }}</div></div></div>
</div>
<div class="content-card p-3">
    <h2 class="h5">Department summary</h2>
    <table class="table mb-0"><thead><tr><th>Department</th><th>Timesheets this week</th></tr></thead><tbody>
        @foreach($departments as $department)<tr><td>{{ $department->name }}</td><td>{{ $department->timesheets_count }}</td></tr>@endforeach
    </tbody></table>
</div>
@endsection
