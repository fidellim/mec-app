<style>
    .leave-calendar-toolbar {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: .6rem;
        flex-wrap: wrap;
    }
    .leave-calendar-jump {
        display: grid;
        grid-template-columns: auto minmax(8.5rem, 1fr) minmax(6.25rem, .75fr) auto;
        gap: .5rem;
        align-items: center;
        min-width: min(100%, 28rem);
        padding: .35rem;
        border: 1px solid var(--app-soft-border);
        border-radius: .65rem;
        background: color-mix(in srgb, var(--app-muted-bg) 70%, var(--app-card-bg));
    }
    .leave-calendar-jump-label {
        color: var(--bs-secondary-color);
        font-size: .78rem;
        font-weight: 800;
        letter-spacing: .02em;
        padding-inline: .35rem;
        text-transform: uppercase;
        white-space: nowrap;
    }
    .leave-calendar-jump .form-select {
        min-height: 2.15rem;
        padding-top: .25rem;
        padding-bottom: .25rem;
    }
    .leave-calendar-nav {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        padding: .35rem;
        border: 1px solid var(--app-soft-border);
        border-radius: .65rem;
        background: color-mix(in srgb, var(--app-muted-bg) 70%, var(--app-card-bg));
    }
    .leave-calendar-icon-btn {
        min-width: 2.15rem;
        min-height: 2.15rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: .35rem;
        padding-inline: .55rem;
        white-space: nowrap;
    }
    .leave-calendar-icon-btn svg {
        width: .95rem;
        height: .95rem;
        flex: 0 0 auto;
        stroke-width: 2.25;
    }
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
        .leave-calendar-toolbar,
        .leave-calendar-nav {
            width: 100%;
        }
        .leave-calendar-jump {
            width: 100%;
            grid-template-columns: minmax(0, 1fr) minmax(0, .85fr) auto;
        }
        .leave-calendar-jump-label {
            grid-column: 1 / -1;
            padding-inline: 0;
        }
        .leave-calendar-jump button {
            min-width: 2.15rem;
        }
        .leave-calendar-nav .leave-calendar-icon-btn {
            flex: 1 1 0;
        }
    }
</style>

@php
    $calendarMonthOptions = collect(range(1, 12))->mapWithKeys(fn ($calendarMonthNumber) => [
        str_pad((string) $calendarMonthNumber, 2, '0', STR_PAD_LEFT) => \Carbon\Carbon::create(null, $calendarMonthNumber, 1)->format('F'),
    ]);
    $calendarYearStart = min($month->copy()->subYears(5)->year, now()->subYears(2)->year);
    $calendarYearEnd = max($month->copy()->addYears(5)->year, now()->addYears(5)->year);
@endphp

<div class="content-card overflow-hidden">
    <div class="content-card-header d-flex flex-column flex-lg-row justify-content-between gap-3">
        <div>
            <h2 class="h5 mb-1">{{ $month->format('F Y') }}</h2>
            <div class="small text-muted">Calendar shows submitted, approved, and cancellation-requested leave by default.</div>
        </div>
        <div class="leave-calendar-toolbar">
            <form class="leave-calendar-jump" method="get" data-calendar-month-selector>
                @foreach(request()->except(['month', 'calendar_month', 'calendar_year']) as $key => $value)
                    @if(is_array($value))
                        @foreach($value as $item)
                            <input type="hidden" name="{{ $key }}[]" value="{{ $item }}">
                        @endforeach
                    @else
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endif
                @endforeach
                <input type="hidden" name="month" value="{{ $month->format('Y-m') }}" data-calendar-month-value>
                <div class="leave-calendar-jump-label">Jump to</div>
                <select class="form-select form-select-sm" id="calendar_month" data-calendar-month data-searchable="false" aria-label="Calendar month">
                    @foreach($calendarMonthOptions as $calendarMonthValue => $calendarMonthLabel)
                        <option value="{{ $calendarMonthValue }}" @selected($month->format('m') === $calendarMonthValue)>{{ $calendarMonthLabel }}</option>
                    @endforeach
                </select>
                <select class="form-select form-select-sm" id="calendar_year" data-calendar-year data-searchable="false" aria-label="Calendar year">
                    @foreach(range($calendarYearStart, $calendarYearEnd) as $calendarYear)
                        <option value="{{ $calendarYear }}" @selected((int) $month->format('Y') === $calendarYear)>{{ $calendarYear }}</option>
                    @endforeach
                </select>
                <button class="btn btn-primary btn-sm leave-calendar-icon-btn" title="Go to selected month" aria-label="Go to selected month">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>
                    <span>Go</span>
                </button>
            </form>
            <div class="leave-calendar-nav" aria-label="Calendar navigation">
                <a class="btn btn-outline-secondary btn-sm leave-calendar-icon-btn" href="{{ request()->fullUrlWithQuery(['month' => $previousMonth]) }}" title="Previous month" aria-label="Previous month">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="m15 18-6-6 6-6"></path></svg>
                </a>
                <a class="btn btn-outline-secondary btn-sm leave-calendar-icon-btn" href="{{ request()->fullUrlWithQuery(['month' => now()->format('Y-m')]) }}" title="Current month">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="M8 2v4"></path><path d="M16 2v4"></path><path d="M3 10h18"></path><rect x="3" y="4" width="18" height="18" rx="2"></rect></svg>
                    <span>Current</span>
                </a>
                <a class="btn btn-outline-secondary btn-sm leave-calendar-icon-btn" href="{{ request()->fullUrlWithQuery(['month' => $nextMonth]) }}" title="Next month" aria-label="Next month">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="m9 18 6-6-6-6"></path></svg>
                </a>
            </div>
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

<script>
document.querySelectorAll('[data-calendar-month-selector]').forEach((form) => {
    form.addEventListener('submit', () => {
        const monthInput = form.querySelector('[data-calendar-month-value]');
        const monthSelect = form.querySelector('[data-calendar-month]');
        const yearSelect = form.querySelector('[data-calendar-year]');

        if (monthInput && monthSelect && yearSelect) {
            monthInput.value = `${yearSelect.value}-${monthSelect.value}`;
        }
    });
});
</script>
