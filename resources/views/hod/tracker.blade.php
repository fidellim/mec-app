@extends('layouts.app')

@section('content')
@php
    $missingEmployees = $period
        ? $employees->filter(function ($employee) {
            $timesheet = $employee->timesheets->first();

            return ! $timesheet || ! in_array($timesheet->status, ['submitted', 'approved'], true);
        })
        : collect();
    $cooldownCount = $missingEmployees->filter(fn ($employee) => $reminderCooldowns->get($employee->id))->count();
    $remindableCount = $missingEmployees->count() - $cooldownCount;
@endphp

<div class="section-header">
    <div>
        <h1 class="h3 page-heading mb-1">Department Submission Tracker</h1>
        <div class="text-muted">Track department submissions and remind employees who still need to submit.</div>
    </div>
</div>

<form class="filter-card mb-3" method="get">
    <div class="row g-3 align-items-end">
        <div class="col-md-8 col-lg-5">
            <label class="form-label" for="period_id">Weekly period</label>
            <select class="form-select" id="period_id" name="period_id">
                @foreach($periods as $availablePeriod)
                    <option value="{{ $availablePeriod->id }}" @selected($period && (int) $period->id === (int) $availablePeriod->id)>
                        Week {{ $availablePeriod->week_number }}, {{ $availablePeriod->year }}: {{ $availablePeriod->start_date->toDateString() }} to {{ $availablePeriod->end_date->toDateString() }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4 col-lg-3 d-flex gap-2">
            <button class="btn btn-primary flex-fill">View Period</button>
            <a class="btn btn-outline-secondary" href="{{ route('hod.tracker') }}">Reset</a>
        </div>
    </div>
</form>

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="content-card stat-card p-3">
            <div class="stat-label">Selected period</div>
            <div class="stat-value">
                @if($period)
                    Week {{ $period->week_number }}
                @else
                    -
                @endif
            </div>
            <div class="text-muted small">
                @if($period)
                    {{ $period->start_date->format('M d, Y') }} - {{ $period->end_date->format('M d, Y') }}
                @else
                    No period available
                @endif
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="content-card stat-card p-3">
            <div class="stat-label">Department employees</div>
            <div class="stat-value">{{ $employees->count() }}</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="content-card stat-card p-3">
            <div class="stat-label">Need reminder</div>
            <div class="stat-value">{{ $missingEmployees->count() }}</div>
        </div>
    </div>
</div>

<div class="content-card overflow-hidden">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 p-3 border-bottom">
        <div>
            <h2 class="h5 mb-1">Submission status</h2>
            <div class="text-muted small">Submitted and approved timesheets are treated as complete.</div>
        </div>
        @if($period && $missingEmployees->isNotEmpty())
            <form method="post" action="{{ route('hod.tracker.reminders') }}" data-confirm="Send reminder emails to all missing employees for this period?">
                @csrf
                <input type="hidden" name="period_id" value="{{ $period->id }}">
                <button class="btn btn-warning" @disabled($remindableCount === 0)>Notify All Missing</button>
                @if($remindableCount === 0)
                    <div class="text-muted small mt-1">All missing employees are on reminder cooldown.</div>
                @elseif($cooldownCount > 0)
                    <div class="text-muted small mt-1">{{ $cooldownCount }} missing employee(s) are on cooldown and will be skipped.</div>
                @endif
            </form>
        @endif
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Period status</th>
                    <th>Reminder</th>
                </tr>
            </thead>
            <tbody>
                @forelse($employees as $employee)
                    @php
                        $timesheet = $employee->timesheets->first();
                        $isMissing = $period && (! $timesheet || ! in_array($timesheet->status, ['submitted', 'approved'], true));
                        $cooldownLabel = $period ? $reminderCooldowns->get($employee->id) : null;
                    @endphp
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $employee->name }}</div>
                            <div class="text-muted small">{{ $employee->employee_code ?: $employee->email }}</div>
                        </td>
                        <td>
                            @if($timesheet)
                                @include('partials.status', ['status' => $timesheet->status])
                            @else
                                <span class="badge text-bg-warning">Not submitted</span>
                            @endif
                        </td>
                        <td>
                            @if($isMissing)
                                @if($cooldownLabel)
                                    <button class="btn btn-sm btn-outline-secondary" disabled>Send Reminder</button>
                                    <div class="text-muted small mt-1">Available again in {{ $cooldownLabel }}</div>
                                @else
                                    <form method="post" action="{{ route('hod.tracker.reminders') }}" data-confirm="Send a missing timesheet reminder to {{ $employee->name }}?">
                                        @csrf
                                        <input type="hidden" name="period_id" value="{{ $period->id }}">
                                        <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                                        <button class="btn btn-sm btn-outline-warning">Send Reminder</button>
                                    </form>
                                @endif
                            @else
                                <span class="text-muted small">No reminder needed</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="empty-state">No active employees found for your department.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
