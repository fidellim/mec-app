@extends('layouts.app')

@section('content')
@php
    $periodLabel = $period
        ? 'Week '.$period->week_number.', '.$period->year.' ('.$period->start_date->format('M d, Y').' - '.$period->end_date->format('M d, Y').')'
        : 'No open weekly period available';
    $canCreateTimesheet = auth()->user()->department_id && $period;
    $currentStatus = $current?->status;
    $currentActionLabel = match ($currentStatus) {
        'draft', 'withdrawn', 'recalled' => 'Continue Draft',
        'rejected' => 'Fix and Resubmit',
        'submitted', 'approved' => 'View Timesheet',
        default => 'Create Weekly Timesheet',
    };
    $currentActionRoute = $current
        ? ($current->editableBy(auth()->user()) ? route('employee.timesheets.edit', $current) : route('employee.timesheets.show', $current))
        : route('employee.timesheets.create');
    $currentCardClass = in_array($currentStatus, ['rejected', null], true) ? 'is-urgent' : (in_array($currentStatus, ['submitted', 'approved'], true) ? 'is-success' : '');
@endphp
<div class="section-header">
    <div>
        <h1 class="h3 page-heading mb-1">Employee Dashboard</h1>
        <div class="text-muted">Track your current week, drafts, and timesheets that need attention.</div>
        <div class="dashboard-period-pill">Open week: {{ $periodLabel }}</div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="dashboard-action-card {{ $currentCardClass }}">
            <div>
                <div class="dashboard-kicker">Current week</div>
                <h2 class="dashboard-action-title mt-1">{{ $current ? 'Your timesheet is '.str_replace('_', ' ', $current->status) : 'Timesheet not submitted yet' }}</h2>
                <div class="dashboard-action-meta mt-2">
                    @if($current)
                        @include('partials.status', ['status' => $current->status])
                        <span class="ms-1">for {{ $periodLabel }}.</span>
                    @elseif($period)
                        Create and submit your weekly timesheet for {{ $periodLabel }}.
                    @else
                        A weekly period needs to be opened before a timesheet can be created.
                    @endif
                </div>
            </div>
            <div>
                @if($canCreateTimesheet || $current)
                    <a class="btn btn-primary" href="{{ $currentActionRoute }}">{{ $currentActionLabel }}</a>
                @elseif(auth()->user()->department_id)
                    <button class="btn btn-outline-secondary" type="button" disabled>No Open Period</button>
                @else
                    <button class="btn btn-outline-secondary" type="button" disabled>Department Required</button>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="content-card stat-card p-3">
            <div class="stat-label">Drafts</div>
            <div class="stat-value">{{ $drafts->count() }}</div>
            <div class="small text-muted">Saved but not submitted</div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="content-card stat-card p-3">
            <div class="stat-label">Rejected requiring action</div>
            <div class="stat-value">{{ $rejected->count() }}</div>
            <div class="small text-muted">Revise and resubmit</div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="content-card h-100">
            <div class="content-card-header">
                <h2 class="h5 mb-1">Draft timesheets</h2>
                <div class="small text-muted">Finish saved weeks before submitting.</div>
            </div>
            <div class="content-card-body">
                <div class="dashboard-worklist">
                    @forelse($drafts->take(3) as $draft)
                        <div class="dashboard-work-row">
                            <div class="dashboard-work-row-main">
                                <div class="dashboard-work-title">Week {{ $draft->period->week_number }}, {{ $draft->period->year }}</div>
                                <div class="dashboard-work-meta">{{ $draft->period->start_date->format('M d') }} - {{ $draft->period->end_date->format('M d, Y') }}</div>
                            </div>
                            <a class="btn btn-sm btn-primary" href="{{ route('employee.timesheets.edit', $draft) }}">Continue</a>
                        </div>
                    @empty
                        <div class="dashboard-empty">No saved drafts right now.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="content-card h-100">
            <div class="content-card-header">
                <h2 class="h5 mb-1">Rejected timesheets</h2>
                <div class="small text-muted">Revise these and submit again.</div>
            </div>
            <div class="content-card-body">
                <div class="dashboard-worklist">
                    @forelse($rejected->take(3) as $timesheet)
                        <div class="dashboard-work-row">
                            <div class="dashboard-work-row-main">
                                <div class="dashboard-work-title">Week {{ $timesheet->period->week_number }}, {{ $timesheet->period->year }}</div>
                                <div class="dashboard-work-meta">{{ $timesheet->period->start_date->format('M d') }} - {{ $timesheet->period->end_date->format('M d, Y') }}</div>
                            </div>
                            <a class="btn btn-sm btn-primary" href="{{ route('employee.timesheets.edit', $timesheet) }}">Fix</a>
                        </div>
                    @empty
                        <div class="dashboard-empty">No rejected timesheets need action.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

@include('shared.leave_balance_cards', ['leaveBalances' => $leaveBalances])
<div class="section-header">
    <div>
        <h2 class="h5 mb-1">Recent submissions</h2>
        <div class="text-muted">Your latest weekly timesheets.</div>
    </div>
    @if(auth()->user()->department_id)
        <a class="btn btn-primary" href="{{ route('employee.timesheets.create') }}">Create Weekly Timesheet</a>
    @else
        <button class="btn btn-outline-secondary" type="button" disabled>Department Required</button>
    @endif
</div>
@unless(auth()->user()->department_id)
    <div class="alert alert-warning">
        You need to be assigned to a department before creating or submitting a timesheet. Please contact Super Admin.
    </div>
@endunless
@include('employee.timesheets._table', ['timesheets' => $recent])
@endsection
