@extends('layouts.app')

@section('content')
<div class="section-header">
    <div>
        <h1 class="h3 page-heading mb-1">Department Leave Calendar</h1>
        <div class="text-muted">Visualize applied leave from your department before planning your dates.</div>
    </div>
    <div class="action-group">
        <a class="btn btn-outline-secondary" href="{{ route('employee.leave-plans.index') }}">List View</a>
        <a class="btn btn-primary" href="{{ route('employee.leave-plans.create') }}">Create Leave Plan</a>
    </div>
</div>
<form class="filter-card mb-3" method="get">
    <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
    <div class="row g-3 align-items-end">
        <div class="col-md-6">
            <label class="form-label">Status</label>
            <select class="form-select" name="status">
                <option value="">Submitted, approved, cancellation requested</option>
                @foreach($statuses as $status)
                    <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">Leave type</label>
            <select class="form-select" name="attendance_code">
                <option value="">All leave types</option>
                @foreach($attendanceCodes as $code => $label)
                    <option value="{{ $code }}" @selected($filters['attendance_code'] === $code)>{{ $code }} - {{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 d-flex justify-content-end gap-2">
            <button class="btn btn-primary">Filter</button>
            <a class="btn btn-outline-secondary" href="{{ route('employee.leave-plans.calendar', ['month' => $month->format('Y-m')]) }}">Reset</a>
        </div>
    </div>
</form>
@include('shared.leave_plan_calendar', [
    'calendarTitle' => $month->format('F Y'),
    'calendarDescription' => 'Calendar shows submitted, approved, and cancellation-requested leave in your department.',
    'calendarReadonly' => true,
])
@endsection
