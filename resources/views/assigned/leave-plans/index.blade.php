@extends('layouts.app')

@section('content')
<div class="section-header">
    <div>
        <h1 class="h3 page-heading mb-1">Assigned Leave Plans</h1>
        <div class="text-muted">Review leave plans waiting for your Director or HR approval.</div>
    </div>
</div>

@include('shared.leave_plan_table', ['leavePlans' => $leavePlans, 'showEmployee' => true, 'showDepartment' => true, 'showRoute' => 'assigned.leave-plans.show'])
@include('shared.pagination-footer', ['paginator' => $leavePlans, 'label' => 'leave plan'])
@endsection
