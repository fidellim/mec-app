@php
    $historyUrl = $historyUrl ?? match (true) {
        request()->routeIs('employee.timesheets.*') => route('employee.timesheets.history', $timesheet),
        request()->routeIs('hod.timesheets.*') => route('hod.timesheets.history', $timesheet),
        request()->routeIs('admin.timesheets.*') => route('admin.timesheets.history', $timesheet),
        default => null,
    };
@endphp

<div class="content-card mt-3" data-timesheet-history data-history-url="{{ $historyUrl }}">
    <div class="content-card-header d-flex flex-column flex-md-row gap-3 align-items-md-center justify-content-between">
        <div>
            <h2 class="h5 mb-1">Timesheet history</h2>
            <div class="small text-muted">Status changes, reviewer comments, and timestamps for this record.</div>
        </div>
        <button
            type="button"
            class="btn btn-outline-secondary btn-sm"
            data-history-toggle
            @disabled(! $historyUrl)
        >
            Show history
        </button>
    </div>
    <div class="content-card-body d-none" data-history-panel>
        <div data-history-content></div>
    </div>
</div>
