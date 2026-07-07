@extends('layouts.app')

@section('content')
@php
    $hasPreview = ! empty($previewRows);
    $invalidCount = collect($previewRows)->where('valid', false)->count();
    $validCount = collect($previewRows)->where('valid', true)->count();
@endphp
<div class="section-header">
    <div>
        <h1 class="h3 page-heading mb-1">Import Approved Leave</h1>
        <div class="text-muted">Preview a CSV before adding already-approved leave records.</div>
    </div>
    <div class="action-group">
        <a class="btn btn-outline-secondary" href="{{ route('admin.leave-plans.index') }}">All Leave Plans</a>
        <a class="btn btn-outline-primary" href="{{ route('admin.leave-plans.create') }}">Add Approved Leave</a>
    </div>
</div>

<div class="content-card mb-3">
    <div class="content-card-header">
        <h2 class="h5 mb-1">CSV template</h2>
        <div class="small text-muted">Use this exact header order. The uploaded file is parsed for preview and discarded immediately.</div>
    </div>
    <div class="content-card-body">
        <div class="table-responsive mb-3">
            <table class="table table-sm table-bordered mb-0">
                <thead>
                    <tr>
                        @foreach($headers as $header)
                            <th>{{ $header }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>MEC-HR-2026-101</td>
                        <td>L100</td>
                        <td>2026-03-10</td>
                        <td>2026-03-12</td>
                        <td>full_day</td>
                        <td></td>
                        <td></td>
                        <td>2026-02-20</td>
                        <td>Historical approved annual leave</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="row g-3 small text-muted">
            <div class="col-md-6">
                <div class="fw-semibold text-body mb-1">Required formats</div>
                <div>Dates use YYYY-MM-DD. Duration is full_day or half_day. Half-day period is blank, morning, or afternoon.</div>
            </div>
            <div class="col-md-6">
                <div class="fw-semibold text-body mb-1">Bereavement relationship</div>
                <div>Leave bereavement_relationship blank except for UAE L180 rows. UAE L180 accepts spouse or immediate_family.</div>
            </div>
            <div class="col-md-6">
                <div class="fw-semibold text-body mb-1">Philippines rows</div>
                <div>The same template supports Philippines leave codes such as L190, L160, L170, L210, L220, and L230 when the employee profile is eligible.</div>
            </div>
            <div class="col-md-6">
                <div class="fw-semibold text-body mb-1">Validation</div>
                <div>Rows must match active employee/HOD records, current departments, eligibility, balances, and non-overlapping active leave.</div>
            </div>
        </div>
    </div>
</div>

<form class="content-card mb-3" method="post" action="{{ route('admin.leave-plans.import.preview') }}" enctype="multipart/form-data">
    @csrf
    <div class="content-card-header">
        <h2 class="h5 mb-1">Upload and preview</h2>
        <div class="small text-muted">No leave records are created until the preview is valid and you choose Import approved leave.</div>
    </div>
    <div class="content-card-body">
        @if(! empty($uploadErrors))
            <div class="alert alert-danger">
                @foreach($uploadErrors as $uploadError)
                    <div>{{ $uploadError }}</div>
                @endforeach
            </div>
        @endif
        <div class="row g-3 align-items-end">
            <div class="col-md-8">
                <label class="form-label" for="csv_file">CSV file</label>
                <input id="csv_file" class="form-control @error('csv_file') is-invalid @enderror" type="file" name="csv_file" accept=".csv,text/csv" required>
                @error('csv_file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">Maximum file size is 2 MB. Uploaded files are discarded after parsing, whether preview succeeds or fails.</div>
            </div>
            <div class="col-md-4 text-md-end">
                <button class="btn btn-primary">Preview CSV</button>
            </div>
        </div>
    </div>
</form>

@if($hasPreview)
    <div class="content-card overflow-hidden">
        <div class="content-card-header">
            <div>
                <h2 class="h5 mb-1">Preview results</h2>
                <div class="small text-muted">{{ $validCount }} valid row(s), {{ $invalidCount }} row(s) needing fixes.</div>
            </div>
            @if($invalidCount === 0)
                <form method="post" action="{{ route('admin.leave-plans.import.store') }}" data-confirm="Import all previewed approved leave records?">
                    @csrf
                    <button class="btn btn-success">Import Approved Leave</button>
                </form>
            @endif
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Row</th>
                        <th>Employee</th>
                        <th>Leave Type</th>
                        <th>Date Range</th>
                        <th>Duration</th>
                        <th>Approved</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($previewRows as $previewRow)
                        @php($attributes = $previewRow['attributes'])
                        <tr>
                            <td>{{ $previewRow['row_number'] }}</td>
                            <td>
                                <div class="fw-semibold">{{ $previewRow['employee_name'] ?: $attributes['employee_code'] }}</div>
                                <div class="small text-muted">{{ $attributes['employee_code'] }}</div>
                            </td>
                            <td>{{ $attributes['attendance_code'] }}</td>
                            <td>{{ $attributes['start_date'] }} to {{ $attributes['end_date'] }}</td>
                            <td>
                                {{ str_replace('_', ' ', $attributes['duration_type']) }}
                                @if($attributes['half_day_period'])
                                    <span class="text-muted">- {{ $attributes['half_day_period'] }}</span>
                                @endif
                            </td>
                            <td>{{ $attributes['approved_at'] }}</td>
                            <td>
                                @if($previewRow['valid'])
                                    <span class="badge text-bg-success">Valid</span>
                                @else
                                    <span class="badge text-bg-danger mb-2">Fix required</span>
                                    <ul class="small text-danger mb-0 ps-3">
                                        @foreach($previewRow['errors'] as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection
