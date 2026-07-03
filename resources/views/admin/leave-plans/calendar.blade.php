@extends('layouts.app')

@section('content')
<div class="section-header">
    <div>
        <h1 class="h3 page-heading mb-1">All Leave Calendar</h1>
        <div class="text-muted">Visualize leave plans across all departments.</div>
    </div>
    <a class="btn btn-outline-secondary" href="{{ route('admin.leave-plans.index') }}">List View</a>
</div>
@include('shared.leave_plan_calendar_filters', ['resetRoute' => 'admin.leave-plans.calendar'])
<div data-calendar-shell>
    @include('shared.leave_plan_calendar')
</div>
@endsection
