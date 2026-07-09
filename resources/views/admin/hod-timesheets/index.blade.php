@extends('layouts.app')

@section('content')
@php
    $hasTimesheetFilters = collect(request()->only(['status', 'hod_id', 'week_number', 'year', 'department_id']))
        ->contains(fn ($value) => filled($value));
@endphp
<div class="section-header">
    <div>
        <h1 class="h3 page-heading mb-1">HOD Timesheets</h1>
        <div class="text-muted">Review Head of Department timesheets submitted for admin approval.</div>
    </div>
</div>
<form class="filter-card mb-3" method="get">
    <div class="row g-3 align-items-end">
        <div class="col-md-6 col-xl-3">
            <label class="form-label" for="department_id">Department</label>
            <select class="form-select" id="department_id" name="department_id">
                <option value="">All departments</option>
                @foreach($departments as $department)
                    <option value="{{ $department->id }}" @selected((int) request('department_id') === (int) $department->id)>{{ $department->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6 col-xl-3">
            <label class="form-label" for="status">Status</label>
            <select class="form-select" id="status" name="status">
                <option value="">All statuses</option>
                @foreach(['submitted','approved','rejected','withdrawn','recalled','draft','voided'] as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6 col-xl-3">
            <label class="form-label" for="hod_id">HOD</label>
            <select class="form-select" id="hod_id" name="hod_id">
                <option value="">All HODs</option>
                @foreach($hods as $hod)
                    <option value="{{ $hod->id }}" @selected((int) request('hod_id') === (int) $hod->id)>{{ $hod->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4 col-xl-2">
            <label class="form-label" for="week_number">Week</label>
            <select class="form-select" id="week_number" name="week_number">
                <option value="">All weeks</option>
                @foreach($periods->pluck('week_number')->unique() as $weekNumber)
                    <option value="{{ $weekNumber }}" @selected((string) request('week_number') === (string) $weekNumber)>Week {{ $weekNumber }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4 col-xl-2">
            <label class="form-label" for="year">Year</label>
            <select class="form-select" id="year" name="year">
                <option value="">All years</option>
                @foreach($periods->pluck('year')->unique() as $year)
                    <option value="{{ $year }}" @selected((string) request('year') === (string) $year)>{{ $year }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4 col-xl-2 d-flex gap-2">
            <button class="btn btn-primary flex-fill">Filter</button>
            @if($hasTimesheetFilters)
                <a class="btn btn-outline-secondary" href="{{ route('admin.hod-timesheets.index') }}">Reset</a>
            @endif
        </div>
    </div>
</form>
<div class="content-card overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>HOD</th>
                    <th>Department</th>
                    <th>Week</th>
                    <th>Status</th>
                    <th>Total</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($timesheets as $timesheet)
                    <tr>
                        <td class="fw-semibold">{{ $timesheet->user->name }}</td>
                        <td>{{ $timesheet->department?->name ?: '-' }}</td>
                        <td>{{ $timesheet->period->week_number }} / {{ $timesheet->period->year }}</td>
                        <td>@include('partials.status', ['status' => $timesheet->status])</td>
                        <td><span class="fw-semibold">{{ $timesheet->total_hours }}</span></td>
                        <td class="text-end"><a class="btn btn-sm btn-primary" href="{{ route('admin.timesheets.show', $timesheet) }}">Review</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty-state">No HOD timesheets found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@include('shared.pagination-footer', ['paginator' => $timesheets, 'label' => 'HOD timesheet'])
@endsection
