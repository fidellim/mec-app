@extends('layouts.app')

@section('content')
@php($isEdit = (bool) $timesheet)
<h1 class="h3 mb-3">{{ $isEdit ? 'Edit Timesheet' : 'Create Weekly Timesheet' }}</h1>
@if($timesheet?->status === 'rejected')
    <div class="alert alert-warning"><strong>Rejected:</strong> {{ $timesheet->rejection_comment }}</div>
@endif
<form method="post" action="{{ $isEdit ? route('employee.timesheets.update', $timesheet) : route('employee.timesheets.store') }}">
    @csrf
    @if($isEdit) @method('put') @endif
    <div class="content-card p-3 mb-3">
        <label class="form-label">Weekly period</label>
        <select class="form-select" name="timesheet_period_id" required>
            @foreach($periods as $period)
                <option value="{{ $period->id }}" @selected(old('timesheet_period_id', $timesheet?->timesheet_period_id) == $period->id)>
                    Week {{ $period->week_number }}, {{ $period->year }}: {{ $period->start_date->toDateString() }} to {{ $period->end_date->toDateString() }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="content-card p-3">
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>Date</th><th>Day</th><th>Project/Job</th><th>Regular</th><th>Overtime</th><th>Description</th><th>Remarks</th></tr></thead>
                <tbody>
                @foreach($entries as $i => $entry)
                    @php($row = is_array($entry) ? (object) $entry : $entry)
                    <tr>
                        @php($workDate = $row->work_date instanceof \Carbon\CarbonInterface ? $row->work_date->toDateString() : $row->work_date)
                        <td style="min-width: 145px;"><input class="form-control" type="date" name="entries[{{ $i }}][work_date]" value="{{ old("entries.$i.work_date", $workDate) }}" required></td>
                        <td style="min-width: 110px;">{{ $row->day_name }}</td>
                        <td style="min-width: 210px;">
                            <select class="form-select" name="entries[{{ $i }}][project_id]">
                                <option value="">Select</option>
                                @foreach($projects as $project)
                                    <option value="{{ $project->id }}" @selected(old("entries.$i.project_id", $row->project_id) == $project->id)>{{ $project->project_code }} - {{ $project->project_name }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td style="width: 110px;"><input class="form-control" type="number" min="0" max="24" step="0.25" name="entries[{{ $i }}][regular_hours]" value="{{ old("entries.$i.regular_hours", $row->regular_hours ?? 0) }}"></td>
                        <td style="width: 110px;"><input class="form-control" type="number" min="0" max="24" step="0.25" name="entries[{{ $i }}][overtime_hours]" value="{{ old("entries.$i.overtime_hours", $row->overtime_hours ?? 0) }}"></td>
                        <td><input class="form-control" name="entries[{{ $i }}][description]" value="{{ old("entries.$i.description", $row->description) }}"></td>
                        <td><input class="form-control" name="entries[{{ $i }}][remarks]" value="{{ old("entries.$i.remarks", $row->remarks) }}"></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="d-flex gap-2 justify-content-end">
            <button class="btn btn-outline-secondary" name="submit" value="0">Save Draft</button>
            <button class="btn btn-primary" name="submit" value="1" data-confirm="Submit this timesheet for approval?">Submit for Approval</button>
        </div>
    </div>
</form>
@endsection
