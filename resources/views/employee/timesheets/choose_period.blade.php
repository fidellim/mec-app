@extends('layouts.app')

@section('content')
<div class="section-header">
    <div>
        <h1 class="h3 page-heading mb-1">Create Weekly Timesheet</h1>
        <div class="text-muted">Choose an open weekly period before entering your daily project hours.</div>
    </div>
</div>

<form class="content-card p-3" method="get" action="{{ route('employee.timesheets.create') }}">
    <div class="row g-3 align-items-end">
        <div class="col-lg-8">
            <label class="form-label fw-semibold">Weekly period</label>
            <select class="form-select" name="period_id" required>
                <option value="">Select an open period</option>
                @foreach($periods as $period)
                    <option value="{{ $period->id }}">
                        Week {{ $period->week_number }}, {{ $period->year }}:
                        {{ $period->start_date->format('M d, Y') }} to {{ $period->end_date->format('M d, Y') }}
                    </option>
                @endforeach
            </select>
            <div class="form-text">Past, current, and future periods are available only when Super Admin keeps them open.</div>
        </div>
        <div class="col-lg-4 text-lg-end">
            <button class="btn btn-primary">Continue</button>
        </div>
    </div>
</form>
@endsection
