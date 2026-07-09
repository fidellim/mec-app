@extends('layouts.app')

@section('content')
<div class="section-header">
    <div>
        <h1 class="h3 page-heading mb-1">Holidays</h1>
        <div class="text-muted">Maintain global and regional company holidays used for leave day counting.</div>
    </div>
    <a class="btn btn-primary" href="{{ route('manage.holidays.create') }}">New Holiday</a>
</div>

<div class="content-card overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Name</th>
                    <th style="width: 10rem;">Date</th>
                    <th style="width: 14rem;">Region</th>
                    <th style="width: 8rem;">Status</th>
                    <th style="width: 14rem;"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($holidays as $holiday)
                    <tr>
                        <td class="fw-semibold">{{ $holiday->name }}</td>
                        <td>{{ $holiday->dateRangeLabel() }}</td>
                        <td>{{ $holiday->regionLabel() }}</td>
                        <td>
                            <span class="badge {{ $holiday->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                {{ $holiday->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="text-end">
                            <div class="action-group">
                                <a class="btn btn-sm btn-primary" href="{{ route('manage.holidays.edit', $holiday) }}">Edit</a>
                                <form method="post" action="{{ route('manage.holidays.status', $holiday) }}" data-confirm="{{ $holiday->is_active ? 'Deactivate this holiday?' : 'Reactivate this holiday?' }}">
                                    @csrf
                                    @method('patch')
                                    <button class="btn btn-sm btn-outline-secondary">{{ $holiday->is_active ? 'Deactivate' : 'Reactivate' }}</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty-state">No holidays found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@include('shared.pagination-footer', ['paginator' => $holidays, 'label' => 'holiday'])
@endsection
