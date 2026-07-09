@extends('layouts.app')

@section('content')
<div class="section-header">
    <div>
        <h1 class="h3 page-heading mb-1">HOD Submission Tracker</h1>
        <div class="text-muted">Track Head of Department timesheet submissions and remind missing HODs.</div>
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
        <div class="col-md-8 col-lg-4">
            <label class="form-label" for="department_id">Department</label>
            <select class="form-select" id="department_id" name="department_id">
                <option value="">All departments</option>
                @foreach($departments as $department)
                    <option value="{{ $department->id }}" @selected((int) $selectedDepartmentId === (int) $department->id)>{{ $department->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4 col-lg-3 d-flex gap-2">
            <button class="btn btn-primary flex-fill">View Period</button>
            @if(request()->filled('period_id') || request()->filled('department_id'))
                <a class="btn btn-outline-secondary" href="{{ route('admin.hod-tracker') }}">Reset</a>
            @endif
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
            <div class="stat-label">Active HODs</div>
            <div class="stat-value">{{ $hods->total() }}</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="content-card stat-card p-3">
            <div class="stat-label">Need reminder</div>
            <div class="stat-value">{{ $missingHodsCount }}</div>
        </div>
    </div>
</div>

<div class="content-card overflow-hidden">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 p-3 border-bottom">
        <div>
            <h2 class="h5 mb-1">Submission status</h2>
            <div class="text-muted small">Submitted and approved HOD timesheets are treated as complete.</div>
        </div>
        @if($period && $missingHodsCount > 0)
            <form method="post" action="{{ route('admin.hod-tracker.reminders') }}" data-confirm="Send reminder emails to all missing HODs for this period?">
                @csrf
                <input type="hidden" name="period_id" value="{{ $period->id }}">
                @if($selectedDepartmentId)
                    <input type="hidden" name="department_id" value="{{ $selectedDepartmentId }}">
                @endif
                <button class="btn btn-warning" @disabled($remindableCount === 0)>Notify All Missing</button>
                @if($remindableCount === 0)
                    <div class="text-muted small mt-1">All missing HODs are on reminder cooldown.</div>
                @elseif($cooldownCount > 0)
                    <div class="text-muted small mt-1">{{ $cooldownCount }} missing HOD(s) are on cooldown and will be skipped.</div>
                @endif
            </form>
        @endif
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>HOD</th>
                    <th>Department</th>
                    <th>Period status</th>
                    <th>Reminder</th>
                </tr>
            </thead>
            <tbody>
                @forelse($hods as $hod)
                    @php
                        $timesheet = $hod->timesheets->first();
                        $isMissing = $period && (! $timesheet || ! in_array($timesheet->status, ['submitted', 'approved'], true));
                        $cooldownLabel = $period ? $reminderCooldowns->get($hod->id) : null;
                    @endphp
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $hod->name }}</div>
                            <div class="text-muted small">{{ $hod->employee_code ?: $hod->email }}</div>
                        </td>
                        <td>{{ $hod->department?->name ?: '-' }}</td>
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
                                    <form method="post" action="{{ route('admin.hod-tracker.reminders') }}" data-confirm="Send a missing timesheet reminder to {{ $hod->name }}?">
                                        @csrf
                                        <input type="hidden" name="period_id" value="{{ $period->id }}">
                                        @if($selectedDepartmentId)
                                            <input type="hidden" name="department_id" value="{{ $selectedDepartmentId }}">
                                        @endif
                                        <input type="hidden" name="hod_id" value="{{ $hod->id }}">
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
                        <td colspan="4" class="empty-state">No active HODs found for the selected view.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@include('shared.pagination-footer', ['paginator' => $hods, 'label' => 'HOD'])
@endsection
