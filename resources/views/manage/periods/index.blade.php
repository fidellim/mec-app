@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h3 mb-0">Weekly Periods</h1><a class="btn btn-primary" href="{{ route('manage.periods.create') }}">New Period</a></div>
<div class="content-card p-3"><table class="table mb-0"><thead><tr><th>Week</th><th>Year</th><th>Start</th><th>End</th><th>Status</th><th></th></tr></thead><tbody>
@foreach($periods as $period)<tr><td>{{ $period->week_number }}</td><td>{{ $period->year }}</td><td>{{ $period->start_date->toDateString() }}</td><td>{{ $period->end_date->toDateString() }}</td><td>@include('partials.status', ['status' => $period->status])</td><td class="text-end"><a class="btn btn-sm btn-primary" href="{{ route('manage.periods.edit', $period) }}">Edit</a></td></tr>@endforeach
</tbody></table></div><div class="mt-3">{{ $periods->links() }}</div>
@endsection
