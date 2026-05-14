@extends('layouts.app')

@section('content')
<div class="section-header">
    <div>
        <h1 class="h3 page-heading mb-1">Admin Dashboard</h1>
        <div class="text-muted">Monitor weekly submission progress across departments.</div>
    </div>
</div>
<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="content-card stat-card p-3"><div class="stat-label">Submitted this week</div><div class="stat-value">{{ $summary['submitted'] }}</div></div></div>
    <div class="col-md-3"><div class="content-card stat-card p-3"><div class="stat-label">Approved this week</div><div class="stat-value">{{ $summary['approved'] }}</div></div></div>
    <div class="col-md-3"><div class="content-card stat-card p-3"><div class="stat-label">Rejected this week</div><div class="stat-value">{{ $summary['rejected'] }}</div></div></div>
    <div class="col-md-3"><div class="content-card stat-card p-3"><div class="stat-label">Missing submissions</div><div class="stat-value">{{ $missing }}</div></div></div>
</div>
<div class="content-card overflow-hidden">
    <div class="content-card-header">
        <h2 class="h5 mb-1">Department summary</h2>
        <div class="small text-muted">Timesheets recorded for the current weekly period.</div>
    </div>
    <div class="table-responsive">
    <table class="table mb-0"><thead><tr><th>Department</th><th>Timesheets this week</th></tr></thead><tbody>
        @foreach($departments as $department)<tr><td>{{ $department->name }}</td><td>{{ $department->timesheets_count }}</td></tr>@endforeach
    </tbody></table>
    </div>
</div>
@endsection
