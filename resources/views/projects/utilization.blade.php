@extends('layouts.app')

@section('content')
<div class="section-header">
    <div>
        <div class="small text-muted mb-1">{{ $project->project_code }}</div>
        <h1 class="h3 page-heading mb-1">{{ $project->project_name }} utilization</h1>
        <div class="text-muted">Lifetime manhour allocation and timesheet usage by discipline.</div>
    </div>
    @if(auth()->user()->isAdminLike())<a class="btn btn-outline-secondary" href="{{ route('manage.projects.edit', $project) }}">Edit project</a>@endif
</div>
<div class="content-card p-3 mb-3">
    <div class="row g-3">
        <div class="col-md-4"><div class="meta-label">Project manager</div><div class="meta-value">{{ $project->projectManager?->name ?? '-' }}</div></div>
        <div class="col-md-4"><div class="meta-label">Starting date</div><div class="meta-value">{{ $project->start_date?->toFormattedDateString() ?? 'Not set' }}</div></div>
        <div class="col-md-4"><div class="meta-label">Client</div><div class="meta-value">{{ $project->client_name ?: '-' }}</div></div>
    </div>
</div>
<div class="content-card p-3 mb-3">
    <form method="GET" action="{{ route('projects.utilization', $project) }}">
        <div class="row g-3 align-items-end">
            <div class="col-sm-6 col-lg-4">
                <label class="form-label" for="date_from">From date</label>
                <input class="form-control @error('date_from') is-invalid @enderror" id="date_from" name="date_from" type="date" value="{{ old('date_from', $filters['date_from'] ?? '') }}">
                @error('date_from')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-sm-6 col-lg-4">
                <label class="form-label" for="date_to">To date</label>
                <input class="form-control @error('date_to') is-invalid @enderror" id="date_to" name="date_to" type="date" value="{{ old('date_to', $filters['date_to'] ?? '') }}">
                @error('date_to')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-lg-4 d-flex flex-wrap gap-2">
                <button class="btn btn-primary" type="submit">Apply date range</button>
                @if(filled($filters['date_from'] ?? null) || filled($filters['date_to'] ?? null))
                    <a class="btn btn-outline-secondary" href="{{ route('projects.utilization', $project) }}">Show lifetime</a>
                @endif
            </div>
        </div>
        <div class="small text-muted mt-2">
            @if(filled($filters['date_from'] ?? null) || filled($filters['date_to'] ?? null))
                Showing entries {{ filled($filters['date_from'] ?? null) ? 'from '.$filters['date_from'] : 'up to '.$filters['date_to'] }}{{ filled($filters['date_from'] ?? null) && filled($filters['date_to'] ?? null) ? ' to '.$filters['date_to'] : '' }}.
            @else
                Showing lifetime utilization. Add dates to review a specific period.
            @endif
        </div>
    </form>
</div>
<div class="content-card overflow-hidden">
    <div class="content-card-header"><h2 class="h5 mb-1">Department budget ledger</h2><div class="small text-muted">Approved hours are official usage. Submitted hours remain pending until approval. Expand a department to see who charged the hours.</div></div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Discipline / department</th><th class="text-end">Allocated</th><th class="text-end">Approved</th><th class="text-end">Pending</th><th class="text-end">Remaining</th><th style="min-width: 12rem;">Utilization</th></tr></thead>
            <tbody>
            @forelse($allocations as $allocation)
                @php($remaining = (float) $allocation->allocated_hours - $allocation->approved_hours - $allocation->pending_hours)
                @php($percent = (float) $allocation->allocated_hours > 0 ? (($allocation->approved_hours + $allocation->pending_hours) / (float) $allocation->allocated_hours) * 100 : 0)
                @php($peopleId = 'department-people-'.$allocation->department_id)
                <tr>
                    <td><div class="fw-semibold">{{ $allocation->department->name }}</div><div class="small text-muted">{{ $allocation->department->code }}</div>@if((float) $allocation->allocated_hours <= 0)<span class="badge text-bg-warning mt-1">No allocation</span>@elseif($remaining < 0)<span class="badge text-bg-danger mt-1">Over allocation</span>@endif @if($allocation->charging_people->isNotEmpty())<button class="btn btn-sm btn-link px-0 ms-2 text-decoration-none collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $peopleId }}" aria-expanded="false" aria-controls="{{ $peopleId }}">People charging <span class="badge rounded-pill text-bg-secondary">{{ $allocation->charging_people->count() }}</span></button>@endif</td>
                    <td class="text-end">{{ number_format((float) $allocation->allocated_hours, 2) }}</td>
                    <td class="text-end fw-semibold">{{ number_format($allocation->approved_hours, 2) }}</td>
                    <td class="text-end">{{ number_format($allocation->pending_hours, 2) }}</td>
                    <td class="text-end fw-semibold {{ $remaining < 0 ? 'text-danger' : '' }}">{{ number_format($remaining, 2) }}</td>
                    <td><div class="progress" role="progressbar" aria-label="{{ $allocation->department->name }} utilization" aria-valuenow="{{ min(100, round($percent)) }}" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar {{ $percent > 100 ? 'bg-danger' : '' }}" style="width: {{ min(100, $percent) }}%"></div></div><div class="small text-muted mt-1">{{ number_format($percent, 1) }}%</div></td>
                </tr>
                @if($allocation->charging_people->isNotEmpty())
                    <tr class="collapse" id="{{ $peopleId }}">
                        <td class="p-0" colspan="6">
                            <div class="p-3 border-top bg-body-tertiary">
                                @if(($allocation->job_level_usage ?? collect())->isNotEmpty())
                                    <div class="small fw-semibold text-uppercase text-muted mb-2">Job Level allocation</div>
                                    <div class="table-responsive mb-3">
                                        <table class="table table-sm align-middle mb-0">
                                            <thead><tr><th>Level / pool</th><th>State</th><th class="text-end">Allocated</th><th class="text-end">Consumed</th><th class="text-end">Remaining</th></tr></thead>
                                            <tbody>
                                            @foreach($allocation->job_level_usage as $level)
                                                @php($consumed = $level->approved_hours + $level->pending_hours)
                                                @php($levelRemaining = $level->allocated_hours === null ? null : $level->allocated_hours - $consumed)
                                                <tr><td class="fw-semibold">{{ $level->label }}</td><td><span class="badge {{ $level->state === 'reserved' ? 'text-bg-primary' : ($level->state === 'shared' ? 'text-bg-secondary' : 'text-bg-light border text-dark') }}">{{ str_replace('_', ' ', ucfirst($level->state)) }}</span></td><td class="text-end">{{ $level->allocated_hours === null ? 'Shared' : number_format($level->allocated_hours, 2) }}</td><td class="text-end">{{ number_format($consumed, 2) }}</td><td class="text-end">{{ $levelRemaining === null ? '—' : number_format($levelRemaining, 2) }}</td></tr>
                                            @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif
                                <div class="small fw-semibold text-uppercase text-muted mb-2">People charging to {{ $allocation->department->name }}</div>
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-0">
                                        <thead><tr><th>Employee</th><th class="text-end">Approved</th><th class="text-end">Pending</th></tr></thead>
                                        <tbody>
                                        @foreach($allocation->charging_people as $person)
                                            <tr><td class="fw-semibold">{{ $person->name }}</td><td class="text-end">{{ number_format((float) $person->approved_hours, 2) }}</td><td class="text-end">{{ number_format((float) $person->pending_hours, 2) }}</td></tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </td>
                    </tr>
                @endif
            @empty
                <tr><td colspan="6" class="empty-state text-center">No department allocations have been set.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@if((int) $project->project_manager_id === (int) auth()->id())
@php($hasReviewFilters = filled($filters['review_employee_id'] ?? null) || filled($filters['review_status'] ?? null) || filled($filters['review_week'] ?? null) || filled($filters['review_year'] ?? null))
<div class="content-card mt-3">
    <div class="content-card-header d-flex flex-wrap justify-content-between align-items-start gap-3">
        <div><h2 class="h5 mb-1">Entry review docket</h2><div class="small text-muted">Review one employee week at a time. Open a packet to inspect entries and raise one focused correction request.</div></div>
        <span class="badge text-bg-secondary">{{ $reviewTimesheets->total() }} timesheets</span>
    </div>
    <div class="content-card-body border-bottom bg-body-tertiary">
        <form method="get" action="{{ route('projects.utilization', $project) }}">
            @if(filled($filters['date_from'] ?? null))<input type="hidden" name="date_from" value="{{ $filters['date_from'] }}">@endif
            @if(filled($filters['date_to'] ?? null))<input type="hidden" name="date_to" value="{{ $filters['date_to'] }}">@endif
            <div class="row g-3 align-items-end">
                <div class="col-12 col-md-6 col-xl-4"><label class="form-label" for="review_employee_id">Employee</label><select class="form-select" id="review_employee_id" name="review_employee_id" aria-label="Filter by employee"><option value="">All project employees</option>@foreach($reviewEmployees as $reviewEmployee)<option value="{{ $reviewEmployee->id }}" @selected((int)($filters['review_employee_id'] ?? 0) === (int)$reviewEmployee->id)>{{ $reviewEmployee->name }}</option>@endforeach</select></div>
                <div class="col-12 col-md-6 col-xl-2"><label class="form-label" for="review_status">Status</label><select class="form-select" id="review_status" name="review_status" data-searchable="false"><option value="">All statuses</option><option value="submitted" @selected(($filters['review_status'] ?? '') === 'submitted')>Submitted</option><option value="approved" @selected(($filters['review_status'] ?? '') === 'approved')>Approved</option></select></div>
                <div class="col-6 col-md-4 col-xl-2"><label class="form-label" for="review_week">Week</label><select class="form-select" id="review_week" name="review_week" data-searchable="false"><option value="">All weeks</option>@foreach($reviewPeriods->pluck('week_number')->unique() as $week)<option value="{{ $week }}" @selected((string)($filters['review_week'] ?? '') === (string)$week)>Week {{ $week }}</option>@endforeach</select></div>
                <div class="col-6 col-md-4 col-xl-2"><label class="form-label" for="review_year">Year</label><select class="form-select" id="review_year" name="review_year" data-searchable="false"><option value="">All years</option>@foreach($reviewPeriods->pluck('year')->unique() as $year)<option value="{{ $year }}" @selected((string)($filters['review_year'] ?? '') === (string)$year)>{{ $year }}</option>@endforeach</select></div>
                <div class="col-12 col-md-4 col-xl-2"><div class="d-grid d-sm-flex gap-2"><button class="btn btn-primary flex-grow-1 text-nowrap">Apply filters</button>@if($hasReviewFilters)<a class="btn btn-outline-secondary" href="{{ route('projects.utilization', array_filter(['project' => $project, 'date_from' => $filters['date_from'] ?? null, 'date_to' => $filters['date_to'] ?? null])) }}">Reset</a>@endif</div></div>
            </div>
        </form>
    </div>
    <div class="content-card-body">
        @error('entry_ids')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror
        @error('comment')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror
        <div class="vstack gap-3">
        @forelse($reviewTimesheets as $reviewTimesheet)
            @php($packetId = 'review-packet-'.$reviewTimesheet->id)
            @php($flaggedCount = $reviewTimesheet->entries->filter(fn($entry) => $openEntryRequestIds->has($entry->id))->count())
            <section class="border rounded-3 overflow-hidden">
                <button class="btn w-100 text-start p-3 rounded-0 border-0 bg-body d-flex flex-wrap justify-content-between align-items-center gap-3 collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $packetId }}" aria-expanded="false" aria-controls="{{ $packetId }}">
                    <span><span class="fw-semibold d-block">{{ $reviewTimesheet->user->name }}</span><span class="small text-muted">Week {{ $reviewTimesheet->period->week_number }}, {{ $reviewTimesheet->period->year }} · {{ $reviewTimesheet->period->start_date->format('d M') }}–{{ $reviewTimesheet->period->end_date->format('d M Y') }}</span></span>
                    <span class="d-flex flex-wrap align-items-center gap-2">@include('partials.status', ['status' => $reviewTimesheet->status])<span class="badge text-bg-light border">{{ $reviewTimesheet->project_entries_count }} entries</span><span class="badge text-bg-light border">{{ number_format((float)$reviewTimesheet->project_regular_hours + (float)$reviewTimesheet->project_overtime_hours, 2) }} h</span>@if($flaggedCount)<span class="badge text-bg-warning">{{ $flaggedCount }} flagged</span>@endif<span class="small text-muted">Open</span></span>
                </button>
                <div class="collapse" id="{{ $packetId }}">
                    <form method="post" action="{{ route('timesheet-corrections.store') }}" data-confirm="Send this correction request for the selected entries?">
                        @csrf
                        <div class="table-responsive border-top"><table class="table table-sm align-middle mb-0"><thead><tr><th class="text-center" style="width:3rem"><span class="visually-hidden">Select</span></th><th>Date</th><th>Discipline</th><th class="text-end">Regular</th><th class="text-end">OT</th><th>Description</th><th>Review state</th></tr></thead><tbody>
                        @foreach($reviewTimesheet->entries as $entry)
                            @php($openRequestId = $openEntryRequestIds->get($entry->id))
                            <tr><td class="text-center"><input class="form-check-input" type="checkbox" name="entry_ids[]" value="{{ $entry->id }}" aria-label="Select {{ $entry->work_date->toDateString() }} entry" @disabled($openRequestId)></td><td class="text-nowrap">{{ $entry->work_date->format('D, d M') }}</td><td>{{ $entry->department?->code ?? '—' }}</td><td class="text-end">{{ number_format((float)$entry->regular_hours, 2) }}</td><td class="text-end">{{ number_format((float)$entry->overtime_hours, 2) }}</td><td>{{ $entry->description ?: '—' }}</td><td>@if($openRequestId)<span class="badge text-bg-warning">Request #{{ $openRequestId }}</span>@else<span class="small text-muted">Available</span>@endif</td></tr>
                        @endforeach
                        </tbody></table></div>
                        @if($reviewTimesheet->entries->contains(fn($entry) => ! $openEntryRequestIds->has($entry->id)))
                        <div class="p-3 border-top bg-body-tertiary"><label class="form-label" for="correction_comment_{{ $reviewTimesheet->id }}">What needs correction?</label><textarea id="correction_comment_{{ $reviewTimesheet->id }}" class="form-control" name="comment" rows="2" required maxlength="2000" placeholder="Describe the discrepancy for the HOD."></textarea><div class="d-flex justify-content-between align-items-center gap-3 mt-2"><span class="small text-muted">Only selected entries in this employee week will be included.</span><button class="btn btn-warning" type="submit">Send correction request</button></div></div>
                        @endif
                    </form>
                </div>
            </section>
        @empty
            <div class="empty-state text-center"><div class="fw-semibold mb-1">No timesheets match these filters</div><div class="small text-muted">Adjust the employee, status, week, or date range to widen the review queue.</div></div>
        @endforelse
        </div>
    </div>
    @if($reviewTimesheets->hasPages())<div class="content-card-body border-top">{{ $reviewTimesheets->links() }}</div>@endif
</div>
<div class="content-card mt-3 overflow-hidden">
    <div class="content-card-header"><h2 class="h5 mb-1">My recent requests</h2><div class="small text-muted">Open requests can be withdrawn but not edited.</div></div>
    <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Request</th><th>Employee</th><th>Entries</th><th>Status</th><th>Created</th><th></th></tr></thead><tbody>
    @forelse($myRequests as $requestItem)<tr><td class="fw-semibold">#{{ $requestItem->id }}</td><td>{{ $requestItem->timesheet->user->name }}</td><td>{{ $requestItem->entries_count }}</td><td><span class="badge text-bg-{{ $requestItem->status === 'open' ? 'warning' : ($requestItem->status === 'accepted' ? 'danger' : 'secondary') }}">{{ ucfirst($requestItem->status) }}</span></td><td>{{ $requestItem->created_at->format('d M Y H:i') }}</td><td class="text-end">@if($requestItem->status === 'open')<form method="post" action="{{ route('timesheet-corrections.withdraw', $requestItem) }}" data-confirm="Withdraw this correction request?">@csrf<button class="btn btn-sm btn-outline-secondary">Withdraw</button></form>@endif</td></tr>
    @empty<tr><td colspan="6" class="empty-state text-center">No correction requests for this project.</td></tr>@endforelse
    </tbody></table></div>
</div>
@endif
@endsection
