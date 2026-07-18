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
                @php($remaining = (float) $allocation->allocated_hours - $allocation->approved_hours)
                @php($percent = (float) $allocation->allocated_hours > 0 ? ($allocation->approved_hours / (float) $allocation->allocated_hours) * 100 : 0)
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
@endsection
