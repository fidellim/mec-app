@php
    $historyUrl = $historyUrl ?? match (true) {
        request()->routeIs('employee.leave-plans.*') => route('employee.leave-plans.history', $leavePlan),
        request()->routeIs('assigned.leave-plans.*') => route('assigned.leave-plans.history', $leavePlan),
        request()->routeIs('hod.leave-plans.*') => route('hod.leave-plans.history', $leavePlan),
        request()->routeIs('admin.leave-plans.*') => route('admin.leave-plans.history', $leavePlan),
        default => null,
    };
@endphp

<div class="content-card mt-3" data-leave-plan-history data-history-url="{{ $historyUrl }}" data-history-label="Leave plan">
    <div class="content-card-header d-flex flex-column flex-md-row gap-3 align-items-md-center justify-content-between">
        <div>
            <h2 class="h5 mb-1">Leave plan history</h2>
            <div class="small text-muted">Status changes, approval stages, reviewer comments, and timestamps for this record.</div>
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
