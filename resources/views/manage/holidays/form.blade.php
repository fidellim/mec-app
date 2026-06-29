@extends('layouts.app')

@section('content')
<div class="section-header">
    <div>
        <h1 class="h3 page-heading mb-1">{{ $holiday->exists ? 'Edit Holiday' : 'New Holiday' }}</h1>
        <div class="text-muted">Regional holidays count only for matching employee numbers. Global holidays count for everyone.</div>
    </div>
</div>

<form class="content-card p-3" method="post" action="{{ $holiday->exists ? route('manage.holidays.update', $holiday) : route('manage.holidays.store') }}">
    @csrf
    @if($holiday->exists)
        @method('put')
    @endif

    <div class="row g-3">
        <div class="col-lg-4 col-md-6">
            <label class="form-label" for="name">Holiday name</label>
            <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $holiday->name) }}" required>
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-lg-3 col-md-6">
            <label class="form-label" for="holiday_date">Date</label>
            <input class="form-control @error('holiday_date') is-invalid @enderror" id="holiday_date" type="date" name="holiday_date" value="{{ old('holiday_date', $holiday->holiday_date?->toDateString()) }}" required>
            @error('holiday_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-lg-3 col-md-6">
            <label class="form-label" for="region">Region</label>
            <select class="form-select @error('region') is-invalid @enderror" id="region" name="region" required>
                @foreach($regions as $code => $label)
                    <option value="{{ $code }}" @selected(old('region', $holiday->region ?: 'global') === $code)>{{ $label }}</option>
                @endforeach
            </select>
            @error('region')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-lg-2 col-md-6 d-flex align-items-end">
            <input type="hidden" name="is_active" value="0">
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" @checked(old('is_active', $holiday->is_active ?? true))>
                <label class="form-check-label" for="is_active">Active</label>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2 mt-4">
        <a class="btn btn-outline-secondary" href="{{ route('manage.holidays.index') }}">Cancel</a>
        <button class="btn btn-primary">Save Holiday</button>
    </div>
</form>
@endsection
