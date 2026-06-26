@extends('layouts.app')

@section('content')
<div class="section-header">
    <div>
        <h1 class="h3 page-heading mb-1">Department Leave Calendar</h1>
        <div class="text-muted">Visualize leave plans for your managed departments.</div>
    </div>
    <a class="btn btn-outline-secondary" href="{{ route('hod.leave-plans.index') }}">List View</a>
</div>
@include('shared.leave_plan_calendar_filters', ['resetRoute' => 'hod.leave-plans.calendar'])
@include('shared.leave_plan_calendar')
@endsection
