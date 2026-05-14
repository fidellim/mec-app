@extends('layouts.app')

@section('content')
<div class="section-header"><div><h1 class="h3 page-heading mb-1">{{ $period->exists ? 'Edit Period' : 'New Period' }}</h1><div class="text-muted">Define the weekly submission window and status.</div></div></div>
<form class="content-card p-3" method="post" action="{{ $period->exists ? route('manage.periods.update', $period) : route('manage.periods.store') }}">
    @csrf @if($period->exists) @method('put') @endif
    <div class="row g-3">
        <div class="col-md-3"><label class="form-label">Week number</label><input class="form-control" type="number" name="week_number" value="{{ old('week_number', $period->week_number) }}" required></div>
        <div class="col-md-3"><label class="form-label">Year</label><input class="form-control" type="number" name="year" value="{{ old('year', $period->year ?: now()->year) }}" required></div>
        <div class="col-md-3"><label class="form-label">Start date</label><input class="form-control" type="date" name="start_date" value="{{ old('start_date', $period->start_date?->toDateString()) }}" required></div>
        <div class="col-md-3"><label class="form-label">End date</label><input class="form-control" type="date" name="end_date" value="{{ old('end_date', $period->end_date?->toDateString()) }}" required></div>
        <div class="col-md-3"><label class="form-label">Status</label><select class="form-select" name="status">@foreach(['open','closed'] as $status)<option value="{{ $status }}" @selected(old('status', $period->status ?: 'open') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></div>
    </div>
    <div class="text-end mt-3"><button class="btn btn-primary">Save Period</button></div>
</form>
@endsection
