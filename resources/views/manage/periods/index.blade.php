@extends('layouts.app')

@section('content')
<div class="section-header"><div><h1 class="h3 page-heading mb-1">Weekly Periods</h1><div class="text-muted">Open and close weekly submission windows.</div></div><a class="btn btn-primary" href="{{ route('manage.periods.create') }}">New Period</a></div>
<div class="content-card overflow-hidden"><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Week</th><th>Year</th><th>Start</th><th>End</th><th>Status</th><th></th></tr></thead><tbody>
@foreach($periods as $period)<tr><td class="fw-semibold">{{ $period->week_number }}</td><td>{{ $period->year }}</td><td>{{ $period->start_date->toDateString() }}</td><td>{{ $period->end_date->toDateString() }}</td><td>@include('partials.status', ['status' => $period->status])</td><td class="text-end"><a class="btn btn-sm btn-primary" href="{{ route('manage.periods.edit', $period) }}">Edit</a></td></tr>@endforeach
</tbody></table></div></div>
@include('shared.pagination-footer', ['paginator' => $periods, 'label' => 'period'])
@endsection
