@extends('layouts.app')

@section('content')
<div class="section-header">
    <div>
        <h1 class="h3 page-heading mb-1">{{ $period->exists ? 'Edit Period' : 'New Period' }}</h1>
        <div class="text-muted">Select the Monday start date. The Sunday end date, ISO week number, and year are calculated automatically.</div>
    </div>
</div>

<form class="content-card p-3" method="post" action="{{ $period->exists ? route('manage.periods.update', $period) : route('manage.periods.store') }}">
    @csrf
    @if($period->exists)
        @method('put')
    @endif

    <div class="row g-3">
        <div class="col-lg-3 col-md-6">
            <label class="form-label" for="start_date">Start date</label>
            <input
                class="form-control @error('start_date') is-invalid @enderror"
                id="start_date"
                type="date"
                name="start_date"
                value="{{ old('start_date', $period->start_date?->toDateString()) }}"
                data-period-start
                required
            >
            <div class="form-text">Must be a Monday.</div>
            <div class="text-danger small mt-1 d-none" data-period-start-feedback>Please select a Monday start date.</div>
            @error('start_date')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-lg-3 col-md-6">
            <label class="form-label" for="end_date">End date</label>
            <input
                class="form-control @error('end_date') is-invalid @enderror"
                id="end_date"
                type="date"
                name="end_date"
                value="{{ old('end_date', $period->end_date?->toDateString()) }}"
                data-period-end
                readonly
                required
            >
            <div class="form-text">Automatically set to the following Sunday.</div>
            @error('end_date')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-lg-2 col-md-4">
            <label class="form-label" for="week_number">Week number</label>
            <input
                class="form-control @error('week_number') is-invalid @enderror"
                id="week_number"
                type="number"
                name="week_number"
                value="{{ old('week_number', $period->week_number) }}"
                data-period-week
                readonly
                required
            >
            @error('week_number')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-lg-2 col-md-4">
            <label class="form-label" for="year">Year</label>
            <input
                class="form-control @error('year') is-invalid @enderror"
                id="year"
                type="number"
                name="year"
                value="{{ old('year', $period->year ?: now()->year) }}"
                data-period-year
                readonly
                required
            >
            @error('year')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-lg-2 col-md-4">
            <label class="form-label" for="status">Status</label>
            <select class="form-select @error('status') is-invalid @enderror" id="status" name="status">
                @foreach(['open','closed'] as $status)
                    <option value="{{ $status }}" @selected(old('status', $period->status ?: 'open') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
            @error('status')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2 mt-4">
        <a class="btn btn-outline-secondary" href="{{ route('manage.periods.index') }}">Cancel</a>
        <button class="btn btn-primary">Save Period</button>
    </div>
</form>

<script>
    (() => {
        const startInput = document.querySelector('[data-period-start]');
        const endInput = document.querySelector('[data-period-end]');
        const weekInput = document.querySelector('[data-period-week]');
        const yearInput = document.querySelector('[data-period-year]');
        const startFeedback = document.querySelector('[data-period-start-feedback]');

        if (!startInput || !endInput || !weekInput || !yearInput) {
            return;
        }

        const dateInputValue = (date) => {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');

            return `${year}-${month}-${day}`;
        };

        const isoWeekFromDate = (date) => {
            const target = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
            const dayNumber = target.getUTCDay() || 7;
            target.setUTCDate(target.getUTCDate() + 4 - dayNumber);

            const isoYear = target.getUTCFullYear();
            const yearStart = new Date(Date.UTC(isoYear, 0, 1));
            const week = Math.ceil((((target - yearStart) / 86400000) + 1) / 7);

            return { week, year: isoYear };
        };

        const updatePeriodFields = () => {
            if (!startInput.value) {
                return;
            }

            const startDate = new Date(`${startInput.value}T00:00:00`);

            if (Number.isNaN(startDate.getTime())) {
                return;
            }

            const endDate = new Date(startDate);
            endDate.setDate(startDate.getDate() + 6);

            const isoPeriod = isoWeekFromDate(startDate);
            const startsOnMonday = startDate.getDay() === 1;

            endInput.value = dateInputValue(endDate);
            weekInput.value = isoPeriod.week;
            yearInput.value = isoPeriod.year;
            window.syncDatePicker?.(endInput);
            startFeedback?.classList.toggle('d-none', startsOnMonday);
        };

        startInput.addEventListener('change', updatePeriodFields);
        startInput.addEventListener('input', updatePeriodFields);
        updatePeriodFields();
    })();
</script>
@endsection
