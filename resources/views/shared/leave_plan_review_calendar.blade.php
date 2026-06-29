@if(($reviewCalendarMonths ?? collect())->isNotEmpty())
<style>
    .review-leave-calendar-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(min(100%, 32rem), 1fr));
        gap: 1rem;
    }
    .review-leave-calendar-month {
        border: 1px solid var(--app-soft-border);
        border-radius: .65rem;
        overflow: hidden;
        background: var(--app-card-bg);
    }
    .review-leave-calendar-title {
        padding: .8rem 1rem;
        border-bottom: 1px solid var(--app-soft-border);
        background: color-mix(in srgb, var(--app-muted-bg) 72%, var(--app-card-bg));
        font-weight: 800;
    }
    .review-leave-calendar {
        display: grid;
        grid-template-columns: repeat(7, minmax(0, 1fr));
    }
    .review-leave-calendar-weekday,
    .review-leave-calendar-day {
        border-right: 1px solid var(--app-soft-border);
        border-bottom: 1px solid var(--app-soft-border);
    }
    .review-leave-calendar-weekday:nth-child(7n),
    .review-leave-calendar-day:nth-child(7n) {
        border-right: 0;
    }
    .review-leave-calendar-weekday {
        min-height: 2.1rem;
        display: flex;
        align-items: center;
        padding: .45rem;
        background: var(--app-muted-bg);
        color: var(--bs-secondary-color);
        font-size: .72rem;
        font-weight: 800;
        text-transform: uppercase;
    }
    .review-leave-calendar-day {
        min-height: 7.25rem;
        padding: .45rem;
        background: var(--app-card-bg);
    }
    .review-leave-calendar-day.is-muted {
        background: color-mix(in srgb, var(--app-muted-bg) 72%, var(--app-card-bg));
        color: var(--bs-secondary-color);
    }
    .review-leave-calendar-day.is-requested {
        box-shadow: inset 0 0 0 2px color-mix(in srgb, var(--bs-primary) 45%, transparent);
        background: color-mix(in srgb, var(--bs-primary-bg-subtle) 30%, var(--app-card-bg));
    }
    .review-leave-calendar-date {
        font-weight: 800;
        font-size: .82rem;
        margin-bottom: .35rem;
    }
    .review-leave-calendar-event {
        border: 1px solid var(--app-soft-border);
        border-left: .22rem solid var(--bs-primary);
        border-radius: .4rem;
        padding: .35rem .4rem;
        margin-bottom: .3rem;
        background: color-mix(in srgb, var(--app-muted-bg) 68%, var(--app-card-bg));
        font-size: .76rem;
        line-height: 1.2;
    }
    .review-leave-calendar-event.is-current {
        border-color: color-mix(in srgb, var(--bs-primary) 52%, var(--app-soft-border));
        border-left-color: var(--bs-primary);
        background: color-mix(in srgb, var(--bs-primary-bg-subtle) 42%, var(--app-card-bg));
    }
    .review-leave-calendar-event.approved { border-left-color: var(--bs-success); }
    .review-leave-calendar-event.submitted { border-left-color: var(--bs-primary); }
    .review-leave-calendar-event.cancellation_requested { border-left-color: var(--bs-warning); }
    .review-leave-calendar-event.holiday {
        border-left-color: var(--bs-info);
        background: color-mix(in srgb, var(--bs-info-bg-subtle) 62%, var(--app-card-bg));
    }
    .review-leave-calendar-event.is-clashing {
        border-color: color-mix(in srgb, var(--bs-danger) 48%, var(--app-soft-border));
        border-left-color: var(--bs-danger);
        background: color-mix(in srgb, var(--bs-danger-bg-subtle) 44%, var(--app-card-bg));
    }
    .review-leave-calendar-event-badge {
        display: inline-flex;
        align-items: center;
        width: fit-content;
        border-radius: 999px;
        padding: .1rem .42rem;
        margin-top: .2rem;
        background: color-mix(in srgb, var(--bs-danger-bg-subtle) 78%, var(--app-card-bg));
        color: var(--bs-danger-text-emphasis);
        font-size: .68rem;
        font-weight: 800;
        text-transform: uppercase;
    }
    .review-leave-calendar-event-label {
        font-weight: 800;
        overflow-wrap: anywhere;
    }
    .review-leave-calendar-event-meta {
        color: var(--bs-secondary-color);
        overflow-wrap: anywhere;
    }
    @media (max-width: 767.98px) {
        .review-leave-calendar {
            display: block;
        }
        .review-leave-calendar-weekday {
            display: none;
        }
        .review-leave-calendar-day {
            min-height: auto;
            border-right: 0;
        }
    }
</style>

<div class="content-card mt-3">
    <div class="content-card-header">
        <h2 class="h5 mb-1">Leave calendar view</h2>
        <div class="small text-muted">Shows this request, visible active leave, applicable holidays, and date clashes in the same calendar month.</div>
    </div>
    <div class="content-card-body">
        <div class="review-leave-calendar-grid">
            @foreach($reviewCalendarMonths as $calendarMonth)
                <div class="review-leave-calendar-month">
                    <div class="review-leave-calendar-title">{{ $calendarMonth['month']->format('F Y') }}</div>
                    <div class="review-leave-calendar">
                        @foreach(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $weekday)
                            <div class="review-leave-calendar-weekday">{{ $weekday }}</div>
                        @endforeach
                        @foreach($calendarMonth['weeks'] as $week)
                            @foreach($week as $day)
                                <div @class([
                                    'review-leave-calendar-day',
                                    'is-muted' => ! $day['in_month'],
                                    'is-requested' => $day['is_requested_range'],
                                ])>
                                    <div class="review-leave-calendar-date">{{ $day['date']->format('j') }}</div>
                                    <div class="review-leave-calendar-events">
                                        @foreach($day['events'] as $event)
                                            <div @class([
                                                'review-leave-calendar-event',
                                                $event['status'],
                                                'is-current' => $event['is_current'],
                                                'is-clashing' => $event['is_clashing'] ?? false,
                                            ]) title="{{ $event['leave_type'] }} - {{ $event['duration'] }}">
                                                <div class="review-leave-calendar-event-label">{{ $event['label'] }}</div>
                                                <div class="review-leave-calendar-event-meta">{{ $event['department'] }}</div>
                                                <div class="review-leave-calendar-event-meta text-capitalize">{{ str_replace('_', ' ', $event['status']) }}</div>
                                                @if($event['is_clashing'] ?? false)
                                                    <div class="review-leave-calendar-event-badge">Clash</div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endif
