<style>
    .leave-calendar-toolbar {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: .45rem;
        flex-wrap: wrap;
    }
    .leave-calendar-jump {
        display: grid;
        grid-template-columns: auto minmax(7.75rem, 1fr) minmax(5.75rem, .7fr) auto;
        gap: .42rem;
        align-items: center;
        min-width: min(100%, 28rem);
        padding: .28rem;
        border: 1px solid var(--app-soft-border);
        border-radius: .65rem;
        background: color-mix(in srgb, var(--app-muted-bg) 56%, var(--app-card-bg));
    }
    .leave-calendar-jump.has-current-link {
        grid-template-columns: auto minmax(7.75rem, 1fr) minmax(5.75rem, .7fr) auto auto;
        min-width: min(100%, 31rem);
    }
    .leave-calendar-jump-label {
        position: absolute !important;
        width: 1px !important;
        height: 1px !important;
        padding: 0 !important;
        margin: -1px !important;
        overflow: hidden !important;
        clip: rect(0, 0, 0, 0) !important;
        white-space: nowrap;
        border: 0 !important;
    }
    .leave-calendar-jump .form-select {
        min-height: 2rem;
        padding-top: .25rem;
        padding-bottom: .25rem;
    }
    .leave-calendar-nav {
        display: inline-flex;
        align-items: center;
        gap: .28rem;
        padding: .28rem;
        border: 1px solid var(--app-soft-border);
        border-radius: .65rem;
        background: color-mix(in srgb, var(--app-muted-bg) 56%, var(--app-card-bg));
    }
    .leave-calendar-nav .btn-outline-secondary {
        border-color: color-mix(in srgb, var(--bs-secondary-color) 42%, var(--app-soft-border));
        color: var(--bs-body-color);
    }
    .leave-calendar-icon-btn {
        min-width: 2rem;
        min-height: 2rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: .35rem;
        padding-inline: .52rem;
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
    .leave-calendar-day-selected {
        box-shadow: inset 0 0 0 2px color-mix(in srgb, var(--bs-primary) 48%, transparent);
        background: color-mix(in srgb, var(--bs-primary-bg-subtle) 28%, var(--app-card-bg));
    }
    .leave-calendar-date {
        font-weight: 800;
        font-size: .9rem;
        margin-bottom: .45rem;
    }
    .leave-calendar-event {
        --leave-calendar-accent: var(--bs-primary);
        --leave-calendar-accent-bg: var(--bs-primary-bg-subtle);
        display: block;
        border: 1px solid var(--app-soft-border);
        border-left: .25rem solid var(--leave-calendar-accent);
        border-radius: .45rem;
        padding: .45rem .5rem;
        margin-bottom: .4rem;
        background: color-mix(in srgb, var(--leave-calendar-accent-bg) 18%, var(--app-card-bg));
        color: var(--bs-body-color);
        text-decoration: none;
        font-size: .8rem;
        line-height: 1.25;
    }
    .leave-calendar-event:hover {
        background: color-mix(in srgb, var(--bs-primary-bg-subtle) 35%, var(--app-card-bg));
        color: var(--bs-body-color);
    }
    .leave-calendar-event-readonly:hover {
        background: color-mix(in srgb, var(--leave-calendar-accent-bg) 18%, var(--app-card-bg));
    }
    .leave-calendar-event.leave-calendar-code-l100,
    .leave-calendar-legend-dot.leave-calendar-code-l100 {
        --leave-calendar-accent: var(--bs-success);
        --leave-calendar-accent-bg: var(--bs-success-bg-subtle);
    }
    .leave-calendar-event.leave-calendar-code-l110,
    .leave-calendar-legend-dot.leave-calendar-code-l110 {
        --leave-calendar-accent: var(--bs-danger);
        --leave-calendar-accent-bg: var(--bs-danger-bg-subtle);
    }
    .leave-calendar-event.leave-calendar-code-l120,
    .leave-calendar-legend-dot.leave-calendar-code-l120 {
        --leave-calendar-accent: var(--bs-warning);
        --leave-calendar-accent-bg: var(--bs-warning-bg-subtle);
    }
    .leave-calendar-event.leave-calendar-code-l130,
    .leave-calendar-legend-dot.leave-calendar-code-l130 {
        --leave-calendar-accent: var(--bs-secondary);
        --leave-calendar-accent-bg: var(--bs-secondary-bg-subtle);
    }
    .leave-calendar-event.leave-calendar-code-l140,
    .leave-calendar-legend-dot.leave-calendar-code-l140 {
        --leave-calendar-accent: var(--bs-info);
        --leave-calendar-accent-bg: var(--bs-info-bg-subtle);
    }
    .leave-calendar-event.leave-calendar-code-l160,
    .leave-calendar-legend-dot.leave-calendar-code-l160 {
        --leave-calendar-accent: color-mix(in srgb, var(--bs-primary) 58%, var(--bs-danger));
        --leave-calendar-accent-bg: color-mix(in srgb, var(--bs-primary-bg-subtle) 62%, var(--bs-danger-bg-subtle));
    }
    .leave-calendar-event.leave-calendar-code-l170,
    .leave-calendar-legend-dot.leave-calendar-code-l170 {
        --leave-calendar-accent: color-mix(in srgb, var(--bs-primary) 70%, var(--bs-info));
        --leave-calendar-accent-bg: color-mix(in srgb, var(--bs-primary-bg-subtle) 70%, var(--bs-info-bg-subtle));
    }
    .leave-calendar-event.leave-calendar-code-l180,
    .leave-calendar-legend-dot.leave-calendar-code-l180 {
        --leave-calendar-accent: color-mix(in srgb, var(--bs-warning) 48%, var(--bs-secondary));
        --leave-calendar-accent-bg: color-mix(in srgb, var(--bs-warning-bg-subtle) 60%, var(--bs-secondary-bg-subtle));
    }
    .leave-calendar-event.leave-calendar-code-l190,
    .leave-calendar-legend-dot.leave-calendar-code-l190 {
        --leave-calendar-accent: color-mix(in srgb, var(--bs-success) 56%, var(--bs-info));
        --leave-calendar-accent-bg: color-mix(in srgb, var(--bs-success-bg-subtle) 60%, var(--bs-info-bg-subtle));
    }
    .leave-calendar-event.leave-calendar-code-l210,
    .leave-calendar-legend-dot.leave-calendar-code-l210 {
        --leave-calendar-accent: color-mix(in srgb, var(--bs-primary) 54%, var(--bs-info));
        --leave-calendar-accent-bg: color-mix(in srgb, var(--bs-primary-bg-subtle) 54%, var(--bs-info-bg-subtle));
    }
    .leave-calendar-event.leave-calendar-code-l220,
    .leave-calendar-legend-dot.leave-calendar-code-l220 {
        --leave-calendar-accent: color-mix(in srgb, var(--bs-danger) 62%, var(--bs-warning));
        --leave-calendar-accent-bg: color-mix(in srgb, var(--bs-danger-bg-subtle) 64%, var(--bs-warning-bg-subtle));
    }
    .leave-calendar-event.leave-calendar-code-l230,
    .leave-calendar-legend-dot.leave-calendar-code-l230 {
        --leave-calendar-accent: color-mix(in srgb, var(--bs-danger) 54%, var(--bs-primary));
        --leave-calendar-accent-bg: color-mix(in srgb, var(--bs-danger-bg-subtle) 56%, var(--bs-primary-bg-subtle));
    }
    .leave-calendar-event.holiday {
        --leave-calendar-accent: var(--bs-info);
        --leave-calendar-accent-bg: var(--bs-info-bg-subtle);
        background: color-mix(in srgb, var(--bs-info-bg-subtle) 36%, var(--app-card-bg));
    }
    .leave-calendar-event-label {
        font-weight: 700;
        overflow-wrap: anywhere;
    }
    .leave-calendar-event-meta {
        color: var(--bs-secondary-color);
        overflow-wrap: anywhere;
    }
    .leave-calendar-status {
        display: inline-flex;
        align-items: center;
        width: fit-content;
        border: 1px solid var(--app-soft-border);
        border-radius: 999px;
        padding: .12rem .48rem;
        margin-top: .25rem;
        background: color-mix(in srgb, var(--app-card-bg) 88%, var(--app-muted-bg));
        color: var(--bs-secondary-color);
        font-size: .68rem;
        font-weight: 800;
        line-height: 1.15;
        text-transform: uppercase;
    }
    .leave-calendar-status.approved {
        background: color-mix(in srgb, var(--bs-success-bg-subtle) 72%, var(--app-card-bg));
        border-color: color-mix(in srgb, var(--bs-success) 28%, var(--app-soft-border));
        color: var(--bs-success-text-emphasis);
    }
    .leave-calendar-status.submitted {
        background: color-mix(in srgb, var(--bs-primary-bg-subtle) 72%, var(--app-card-bg));
        border-color: color-mix(in srgb, var(--bs-primary) 26%, var(--app-soft-border));
        color: var(--bs-primary-text-emphasis);
    }
    .leave-calendar-status.cancellation_requested,
    .leave-calendar-status.recalled {
        background: color-mix(in srgb, var(--bs-warning-bg-subtle) 76%, var(--app-card-bg));
        border-color: color-mix(in srgb, var(--bs-warning) 34%, var(--app-soft-border));
        color: var(--bs-warning-text-emphasis);
    }
    .leave-calendar-status.holiday {
        background: color-mix(in srgb, var(--bs-info-bg-subtle) 76%, var(--app-card-bg));
        border-color: color-mix(in srgb, var(--bs-info) 30%, var(--app-soft-border));
        color: var(--bs-info-text-emphasis);
    }
    .leave-calendar-availability-summary {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: .75rem;
        margin-bottom: 1rem;
    }
    .leave-calendar-summary-item {
        min-width: 0;
        padding: .8rem .9rem;
        border: 1px solid var(--app-soft-border);
        border-radius: .65rem;
        background: color-mix(in srgb, var(--app-muted-bg) 58%, var(--app-card-bg));
    }
    .leave-calendar-summary-label {
        color: var(--bs-secondary-color);
        font-size: .72rem;
        font-weight: 800;
        letter-spacing: .02em;
        text-transform: uppercase;
    }
    .leave-calendar-summary-value {
        margin-top: .2rem;
        color: var(--bs-body-color);
        font-size: 1.05rem;
        font-weight: 800;
        line-height: 1.15;
        overflow-wrap: anywhere;
    }
    .leave-calendar-legend {
        display: flex;
        align-items: center;
        gap: .55rem .8rem;
        flex-wrap: wrap;
        margin-bottom: 1rem;
        padding: .72rem .85rem;
        border: 1px solid var(--app-soft-border);
        border-radius: .65rem;
        background: color-mix(in srgb, var(--app-card-bg) 82%, var(--app-muted-bg));
    }
    .leave-calendar-legend-label {
        color: var(--bs-secondary-color);
        font-size: .72rem;
        font-weight: 800;
        letter-spacing: .02em;
        text-transform: uppercase;
    }
    .leave-calendar-legend-item {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        color: var(--bs-body-color);
        font-size: .8rem;
        font-weight: 700;
        white-space: nowrap;
    }
    .leave-calendar-legend-dot {
        --leave-calendar-accent: var(--bs-primary);
        --leave-calendar-accent-bg: var(--bs-primary-bg-subtle);
        width: .72rem;
        height: .72rem;
        border-radius: 999px;
        background: var(--leave-calendar-accent);
        box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--bs-body-color) 15%, transparent);
    }
    .leave-calendar-legend-dot.holiday { background: var(--bs-info); }
    .leave-calendar-legend-dot.selected {
        border: 2px solid color-mix(in srgb, var(--bs-primary) 62%, var(--app-card-bg));
        background: color-mix(in srgb, var(--bs-primary-bg-subtle) 52%, var(--app-card-bg));
    }
    .leave-calendar-availability .leave-calendar {
        border-radius: .65rem;
        overflow: hidden;
    }
    .leave-calendar-availability .leave-calendar-day {
        position: relative;
    }
    .leave-calendar-availability .leave-calendar-day-muted {
        background: color-mix(in srgb, var(--app-muted-bg) 82%, var(--app-card-bg));
        color: color-mix(in srgb, var(--bs-secondary-color) 82%, transparent);
    }
    .leave-calendar-availability .leave-calendar-day-selected {
        box-shadow: inset 0 0 0 2px color-mix(in srgb, var(--bs-primary) 50%, transparent);
        background:
            linear-gradient(90deg, color-mix(in srgb, var(--bs-primary) 62%, transparent) 0 .28rem, transparent .28rem),
            color-mix(in srgb, var(--bs-primary-bg-subtle) 38%, var(--app-card-bg));
    }
    .leave-calendar-mobile-date {
        display: none;
    }
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
        .leave-calendar-day-empty.leave-calendar-day-muted {
            display: none;
        }
        .leave-calendar-date {
            display: none;
        }
        .leave-calendar-mobile-date {
            display: block;
            margin-bottom: .5rem;
            color: var(--bs-secondary-color);
            font-size: .78rem;
            font-weight: 800;
            letter-spacing: .02em;
            text-transform: uppercase;
        }
        .leave-calendar-toolbar,
        .leave-calendar-jump,
        .leave-calendar-nav {
            width: 100%;
        }
        .leave-calendar-jump {
            grid-template-columns: auto minmax(0, 1fr) minmax(0, .8fr) auto;
        }
        .leave-calendar-jump.has-current-link {
            grid-template-columns: auto minmax(0, 1fr) minmax(0, .8fr) auto auto;
        }
        .leave-calendar-jump-label {
            grid-column: auto;
        }
        .leave-calendar-jump button {
            min-width: 2.15rem;
        }
        .leave-calendar-nav .leave-calendar-icon-btn {
            flex: 1 1 0;
        }
        .leave-calendar-availability-summary {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .leave-calendar-legend {
            align-items: flex-start;
        }
        .leave-calendar-legend-label {
            width: 100%;
        }
    }
    @media (max-width: 440px) {
        .leave-calendar-availability-summary {
            grid-template-columns: minmax(0, 1fr);
        }
        .leave-calendar-legend-item {
            white-space: normal;
        }
    }
</style>

@php
    $calendarTitle = $calendarTitle ?? $month->format('F Y');
    $calendarDescription = $calendarDescription ?? 'Calendar shows submitted, approved, and cancellation-requested leave by default.';
    $calendarReadonly = $calendarReadonly ?? false;
    $calendarInteractiveRange = $calendarInteractiveRange ?? false;
    $calendarVariant = $calendarVariant ?? null;
    $isAvailabilityCalendar = $calendarVariant === 'availability';
    $isViewingCurrentMonth = $month->isSameMonth(now());
    $calendarDays = collect($weeks)->flatten(1);
    $calendarMonthDays = $calendarDays->filter(fn ($day) => $day['in_month']);
    $calendarMonthLeaveEvents = $calendarMonthDays->flatMap(fn ($day) => $day['events']->filter(fn ($event) => ($event['type'] ?? null) === 'leave'));
    $calendarMonthHolidayEvents = $calendarMonthDays->flatMap(fn ($day) => $day['events']->filter(fn ($event) => ($event['type'] ?? null) === 'holiday'));
    $attendanceCodeOrder = array_flip(array_keys(config('timesheet.attendance_codes', [])));
    $calendarLeaveTypeLegend = $calendarMonthLeaveEvents
        ->filter(fn ($event) => ! empty($event['attendance_code']))
        ->unique('attendance_code')
        ->sortBy(fn ($event) => $attendanceCodeOrder[$event['attendance_code']] ?? 999)
        ->map(fn ($event) => [
            'code' => $event['attendance_code'],
            'label' => $event['leave_type_label'] ?? $event['attendance_code'],
            'class' => 'leave-calendar-code-'.strtolower((string) $event['attendance_code']),
        ])
        ->values();
    $availabilityLeaveEntryCount = $calendarMonthLeaveEvents
        ->unique(fn ($event) => $event['leavePlan']?->getKey() ?? $event['label'].'-'.$event['title'])
        ->count();
    $availabilityBusyDateCount = $calendarMonthDays
        ->filter(fn ($day) => $day['events']->contains(fn ($event) => ($event['type'] ?? null) === 'leave'))
        ->count();
    $availabilityHolidayCount = $calendarMonthHolidayEvents->count();
    $calendarUrl = function (string $calendarMonth) {
        $query = array_merge(request()->except(['month', 'calendar_fragment']), ['month' => $calendarMonth]);

        return request()->url().'?'.http_build_query($query);
    };
    $calendarMonthOptions = collect(range(1, 12))->map(fn ($calendarMonthNumber) => [
        'value' => str_pad((string) $calendarMonthNumber, 2, '0', STR_PAD_LEFT),
        'label' => \Carbon\Carbon::create(null, $calendarMonthNumber, 1)->format('F'),
    ]);
    $calendarYearStart = min($month->copy()->subYears(5)->year, now()->subYears(2)->year);
    $calendarYearEnd = max($month->copy()->addYears(5)->year, now()->addYears(5)->year);
@endphp

<div @class(['content-card overflow-hidden', 'leave-calendar-availability' => $isAvailabilityCalendar])>
    <div class="content-card-header d-flex flex-column flex-lg-row justify-content-between gap-3">
        <div>
            <h2 class="h5 mb-1">{{ $calendarTitle }}</h2>
            <div class="small text-muted">{{ $calendarDescription }}</div>
        </div>
        <div class="leave-calendar-toolbar">
            <form @class(['leave-calendar-jump', 'has-current-link' => ! $isViewingCurrentMonth]) method="get" data-calendar-month-selector @if($isAvailabilityCalendar) data-calendar-auto-submit @endif>
                @foreach(request()->except(['month', 'calendar_month', 'calendar_year', 'calendar_fragment']) as $key => $value)
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
                <a class="btn btn-outline-secondary btn-sm leave-calendar-icon-btn" href="{{ $calendarUrl($previousMonth) }}" title="Previous month" aria-label="Previous month">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="m15 18-6-6 6-6"></path></svg>
                </a>
                <select class="form-select form-select-sm" id="calendar_month" data-calendar-month data-searchable="false" aria-label="Calendar month">
                    @foreach($calendarMonthOptions as $calendarMonthOption)
                        <option value="{{ $calendarMonthOption['value'] }}" @selected($month->format('m') === $calendarMonthOption['value'])>{{ $calendarMonthOption['label'] }}</option>
                    @endforeach
                </select>
                <select class="form-select form-select-sm" id="calendar_year" data-calendar-year data-searchable="false" aria-label="Calendar year">
                    @foreach(range($calendarYearStart, $calendarYearEnd) as $calendarYear)
                        <option value="{{ $calendarYear }}" @selected((int) $month->format('Y') === $calendarYear)>{{ $calendarYear }}</option>
                    @endforeach
                </select>
                @unless($isViewingCurrentMonth)
                    <a class="btn btn-outline-secondary btn-sm leave-calendar-icon-btn leave-calendar-current-link" href="{{ $calendarUrl(now()->format('Y-m')) }}" title="Back to today's month" aria-label="Back to today's month">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="M8 2v4"></path><path d="M16 2v4"></path><path d="M3 10h18"></path><rect x="3" y="4" width="18" height="18" rx="2"></rect></svg>
                    </a>
                @endunless
                <a class="btn btn-outline-secondary btn-sm leave-calendar-icon-btn" href="{{ $calendarUrl($nextMonth) }}" title="Next month" aria-label="Next month">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="m9 18 6-6-6-6"></path></svg>
                </a>
                <noscript>
                    <button class="btn btn-sm btn-outline-primary leave-calendar-icon-btn" title="Go to selected month" aria-label="Go to selected month">
                        <span>Go</span>
                    </button>
                </noscript>
            </form>
        </div>
    </div>
    <div class="content-card-body">
        @if($isAvailabilityCalendar)
            <div class="leave-calendar-availability-summary" aria-label="Leave availability summary">
                <div class="leave-calendar-summary-item">
                    <div class="leave-calendar-summary-label">Viewing</div>
                    <div class="leave-calendar-summary-value">{{ $month->format('F Y') }}</div>
                </div>
                <div class="leave-calendar-summary-item">
                    <div class="leave-calendar-summary-label">Leave entries</div>
                    <div class="leave-calendar-summary-value">{{ $availabilityLeaveEntryCount }}</div>
                </div>
                <div class="leave-calendar-summary-item">
                    <div class="leave-calendar-summary-label">Busy leave dates</div>
                    <div class="leave-calendar-summary-value">{{ $availabilityBusyDateCount }}</div>
                </div>
                <div class="leave-calendar-summary-item">
                    <div class="leave-calendar-summary-label">Holidays</div>
                    <div class="leave-calendar-summary-value">{{ $availabilityHolidayCount }}</div>
                </div>
            </div>
        @endif
        @if($calendarLeaveTypeLegend->isNotEmpty() || $calendarMonthHolidayEvents->isNotEmpty() || $isAvailabilityCalendar)
            <div class="leave-calendar-legend" aria-label="Calendar legend">
                <span class="leave-calendar-legend-label">Leave types</span>
                @foreach($calendarLeaveTypeLegend as $legendItem)
                    <span class="leave-calendar-legend-item">
                        <span class="leave-calendar-legend-dot {{ $legendItem['class'] }}" aria-hidden="true"></span>
                        {{ $legendItem['code'] }} - {{ $legendItem['label'] }}
                    </span>
                @endforeach
                @if($calendarMonthHolidayEvents->isNotEmpty())
                    <span class="leave-calendar-legend-item"><span class="leave-calendar-legend-dot holiday" aria-hidden="true"></span>Holiday</span>
                @endif
                @if($isAvailabilityCalendar)
                    <span class="leave-calendar-legend-item"><span class="leave-calendar-legend-dot selected" aria-hidden="true"></span>Your selected dates</span>
                @endif
            </div>
        @endif
        <div class="leave-calendar" @if($calendarInteractiveRange) data-leave-plan-availability-calendar @endif>
            @foreach(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $weekday)
                <div class="leave-calendar-weekday">{{ $weekday }}</div>
            @endforeach
            @foreach($weeks as $week)
                @foreach($week as $day)
                    <div
                        @class(['leave-calendar-day', 'leave-calendar-day-muted' => ! $day['in_month'], 'leave-calendar-day-empty' => $day['events']->isEmpty()])
                        @if($calendarInteractiveRange) data-calendar-date="{{ $day['date']->toDateString() }}" @endif
                    >
                        <div class="leave-calendar-date">{{ $day['date']->format('j') }}</div>
                        <div class="leave-calendar-mobile-date">{{ $day['date']->format('D, M j') }}</div>
                        @foreach($day['events'] as $event)
                            @php($eventClasses = 'leave-calendar-event '.$event['status'].(! empty($event['attendance_code']) ? ' leave-calendar-code-'.strtolower((string) $event['attendance_code']) : '').($calendarReadonly || empty($event['url']) ? ' leave-calendar-event-readonly' : ''))
                            @if(! $calendarReadonly && ! empty($event['url']))
                                <a class="{{ $eventClasses }}" href="{{ $event['url'] }}" title="{{ $event['title'] }}">
                            @else
                                <div class="{{ $eventClasses }}" title="{{ $event['title'] }}">
                            @endif
                                @if($isAvailabilityCalendar)
                                    <div class="leave-calendar-event-label">{{ $event['label'] }}</div>
                                @else
                                    <div class="fw-semibold text-truncate">{{ $event['label'] }}</div>
                                @endif
                                @if(($event['type'] ?? null) === 'leave' && ! empty($event['leave_type_label']))
                                    <div class="leave-calendar-event-meta">{{ $event['leave_type_label'] }}</div>
                                @endif
                                <div class="leave-calendar-status {{ $event['status'] }}">{{ str_replace('_', ' ', $event['status']) }}</div>
                                @if(! empty($event['duration']))
                                    <div class="leave-calendar-event-meta">{{ $event['duration'] }}</div>
                                @endif
                            @if(! $calendarReadonly && ! empty($event['url']))
                                </a>
                            @else
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endforeach
            @endforeach
        </div>
    </div>
</div>

<script>
(() => {
    const syncCalendarMonthValue = (form) => {
        const monthInput = form.querySelector('[data-calendar-month-value]');
        const monthSelect = form.querySelector('[data-calendar-month]');
        const yearSelect = form.querySelector('[data-calendar-year]');

        if (monthInput && monthSelect && yearSelect) {
            monthInput.value = `${yearSelect.value}-${monthSelect.value}`;
        }
    };

    if (window.leaveCalendarMonthNavigationInitialized) {
        return;
    }

    window.leaveCalendarMonthNavigationInitialized = true;

    const calendarFormUrl = (form) => {
        syncCalendarMonthValue(form);

        const targetUrl = new URL(form.action || window.location.href, window.location.href);
        const targetParams = new URLSearchParams(new FormData(form));
        targetUrl.search = targetParams.toString();

        return targetUrl;
    };

    const fragmentUrl = (url) => {
        const nextUrl = new URL(url, window.location.href);
        nextUrl.searchParams.set('calendar_fragment', 'calendar');

        return nextUrl;
    };

    const browserUrl = (url) => {
        const nextUrl = new URL(url, window.location.href);
        nextUrl.searchParams.delete('calendar_fragment');

        return nextUrl;
    };

    const loadCalendarShell = async (shell, url, pushState = true) => {
        if (!shell || !window.fetch || !window.history?.pushState) {
            window.location.href = browserUrl(url);
            return;
        }

        shell.classList.add('opacity-75');

        try {
            const response = await fetch(fragmentUrl(url), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'text/html',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error(`Calendar request failed with ${response.status}`);
            }

            shell.innerHTML = await response.text();
            window.initializeSearchableSelects?.(shell);

            if (pushState) {
                window.history.pushState({ leaveCalendarMonth: true }, '', browserUrl(url));
            }
        } catch (error) {
            window.location.href = browserUrl(url);
        } finally {
            shell.classList.remove('opacity-75');
        }
    };

    document.addEventListener('submit', (event) => {
        const form = event.target.closest('[data-calendar-month-selector]');

        if (!form) {
            return;
        }

        syncCalendarMonthValue(form);

        const shell = form.closest('[data-calendar-shell]');

        if (!shell) {
            return;
        }

        event.preventDefault();
        loadCalendarShell(shell, calendarFormUrl(form));
    });

    document.addEventListener('click', (event) => {
        const shell = event.target.closest('[data-calendar-shell]');

        if (!shell) {
            return;
        }

        const link = event.target.closest('.leave-calendar-nav a, [data-calendar-month-selector] a');

        if (!link || !shell.contains(link)) {
            return;
        }

        event.preventDefault();
        loadCalendarShell(shell, link.href);
    });

    document.addEventListener('change', (event) => {
        if (!event.target.matches('[data-calendar-month], [data-calendar-year]')) {
            return;
        }

        const form = event.target.closest('[data-calendar-month-selector]');
        const shell = form?.closest('[data-calendar-shell]');

        if (!form || !shell) {
            return;
        }

        window.clearTimeout(shell.leaveCalendarMonthTimer);
        shell.leaveCalendarMonthTimer = window.setTimeout(() => {
            loadCalendarShell(shell, calendarFormUrl(form));
        }, 180);
    });

    window.addEventListener('popstate', () => {
        const shell = document.querySelector('[data-calendar-shell]');

        if (shell) {
            loadCalendarShell(shell, window.location.href, false);
        }
    });
})();
</script>
