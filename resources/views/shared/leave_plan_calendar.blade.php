<style>
    .leave-calendar {
        display: grid;
        grid-template-columns: repeat(7, minmax(0, 1fr));
        border-top: 1px solid var(--app-soft-border);
        border-left: 1px solid var(--app-soft-border);
    }
    .leave-calendar-weekday,
    .leave-calendar-day {
        border-right: 1px solid var(--app-soft-border);
        border-bottom: 1px solid var(--app-soft-border);
    }
    .leave-calendar-weekday {
        background: var(--app-muted-bg);
        color: var(--bs-secondary-color);
        font-size: .75rem;
        font-weight: 800;
        letter-spacing: .02em;
        padding: .75rem;
        text-transform: uppercase;
    }
    .leave-calendar-day {
        min-height: 8.5rem;
        padding: .65rem;
        background: var(--app-card-bg);
    }
    .leave-calendar-day-muted {
        background: color-mix(in srgb, var(--app-muted-bg) 68%, var(--app-card-bg));
        color: var(--bs-secondary-color);
    }
    .leave-calendar-date {
        font-weight: 800;
        font-size: .9rem;
        margin-bottom: .45rem;
    }
    .leave-calendar-event {
        display: block;
        border: 1px solid var(--app-soft-border);
        border-left: .25rem solid var(--bs-primary);
        border-radius: .45rem;
        padding: .45rem .5rem;
        margin-bottom: .4rem;
        background: color-mix(in srgb, var(--app-muted-bg) 72%, var(--app-card-bg));
        color: var(--bs-body-color);
        text-decoration: none;
        font-size: .8rem;
        line-height: 1.25;
    }
    .leave-calendar-event:hover {
        background: color-mix(in srgb, var(--bs-primary-bg-subtle) 35%, var(--app-card-bg));
        color: var(--bs-body-color);
    }
    .leave-calendar-event.approved { border-left-color: var(--bs-success); }
    .leave-calendar-event.submitted { border-left-color: var(--bs-primary); }
    .leave-calendar-event.cancellation_requested { border-left-color: var(--bs-warning); }
    .leave-calendar-event.rejected { border-left-color: var(--bs-danger); }
    .leave-calendar-event.recalled { border-left-color: var(--bs-warning); }
    .leave-calendar-event.cancelled { border-left-color: var(--bs-dark); }
    .leave-calendar-event.voided { border-left-color: var(--bs-dark); }
    @media (max-width: 767.98px) {
        .leave-calendar {
            display: block;
            border: 0;
        }
        .leave-calendar-weekday {
            display: none;
        }
        .leave-calendar-day {
            min-height: auto;
            border: 1px solid var(--app-soft-border);
            border-radius: .65rem;
            margin-bottom: .75rem;
        }
        .leave-calendar-day:empty {
            display: none;
        }
    }
</style>

<div class="content-card overflow-hidden">
    <div class="content-card-header d-flex flex-column flex-lg-row justify-content-between gap-3">
        <div>
            <h2 class="h5 mb-1">{{ $month->format('F Y') }}</h2>
            <div class="small text-muted">Calendar shows submitted, approved, and cancellation-requested leave by default.</div>
        </div>
        <div class="action-group">
            <a class="btn btn-outline-secondary btn-sm" href="{{ request()->fullUrlWithQuery(['month' => $previousMonth]) }}">Previous</a>
            <a class="btn btn-outline-secondary btn-sm" href="{{ request()->fullUrlWithQuery(['month' => now()->format('Y-m')]) }}">Current</a>
            <a class="btn btn-outline-secondary btn-sm" href="{{ request()->fullUrlWithQuery(['month' => $nextMonth]) }}">Next</a>
        </div>
    </div>
    <div class="content-card-body">
        <div class="leave-calendar">
            @foreach(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $weekday)
                <div class="leave-calendar-weekday">{{ $weekday }}</div>
            @endforeach
            @foreach($weeks as $week)
                @foreach($week as $day)
                    <div @class(['leave-calendar-day', 'leave-calendar-day-muted' => ! $day['in_month']])>
                        <div class="leave-calendar-date">{{ $day['date']->format('j') }}</div>
                        @foreach($day['events'] as $event)
                            <a class="leave-calendar-event {{ $event['status'] }}" href="{{ $event['url'] }}" title="{{ $event['title'] }}">
                                <div class="fw-semibold text-truncate">{{ $event['label'] }}</div>
                                <div class="text-muted text-capitalize">{{ str_replace('_', ' ', $event['status']) }}</div>
                                <div class="text-muted">{{ $event['duration'] }}</div>
                            </a>
                        @endforeach
                    </div>
                @endforeach
            @endforeach
        </div>
    </div>
</div>
