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
        --review-leave-calendar-accent: var(--bs-primary);
        --review-leave-calendar-accent-bg: var(--bs-primary-bg-subtle);
        border: 1px solid var(--app-soft-border);
        border-left: .22rem solid var(--review-leave-calendar-accent);
        border-radius: .4rem;
        padding: .35rem .4rem;
        margin-bottom: .3rem;
        background: color-mix(in srgb, var(--review-leave-calendar-accent-bg) 18%, var(--app-card-bg));
        font-size: .76rem;
        line-height: 1.2;
    }
    .review-leave-calendar-event.is-current {
        border-color: color-mix(in srgb, var(--bs-primary) 52%, var(--app-soft-border));
        background: color-mix(in srgb, var(--bs-primary-bg-subtle) 42%, var(--app-card-bg));
    }
    .review-leave-calendar-event.leave-calendar-code-l100,
    .review-leave-calendar-legend-dot.leave-calendar-code-l100 {
        --review-leave-calendar-accent: var(--bs-success);
        --review-leave-calendar-accent-bg: var(--bs-success-bg-subtle);
    }
    .review-leave-calendar-event.leave-calendar-code-l110,
    .review-leave-calendar-legend-dot.leave-calendar-code-l110 {
        --review-leave-calendar-accent: var(--bs-danger);
        --review-leave-calendar-accent-bg: var(--bs-danger-bg-subtle);
    }
    .review-leave-calendar-event.leave-calendar-code-l120,
    .review-leave-calendar-legend-dot.leave-calendar-code-l120 {
        --review-leave-calendar-accent: var(--bs-warning);
        --review-leave-calendar-accent-bg: var(--bs-warning-bg-subtle);
    }
    .review-leave-calendar-event.leave-calendar-code-l130,
    .review-leave-calendar-legend-dot.leave-calendar-code-l130 {
        --review-leave-calendar-accent: var(--bs-secondary);
        --review-leave-calendar-accent-bg: var(--bs-secondary-bg-subtle);
    }
    .review-leave-calendar-event.leave-calendar-code-l140,
    .review-leave-calendar-legend-dot.leave-calendar-code-l140 {
        --review-leave-calendar-accent: var(--bs-info);
        --review-leave-calendar-accent-bg: var(--bs-info-bg-subtle);
    }
    .review-leave-calendar-event.leave-calendar-code-l160,
    .review-leave-calendar-legend-dot.leave-calendar-code-l160 {
        --review-leave-calendar-accent: color-mix(in srgb, var(--bs-primary) 58%, var(--bs-danger));
        --review-leave-calendar-accent-bg: color-mix(in srgb, var(--bs-primary-bg-subtle) 62%, var(--bs-danger-bg-subtle));
    }
    .review-leave-calendar-event.leave-calendar-code-l170,
    .review-leave-calendar-legend-dot.leave-calendar-code-l170 {
        --review-leave-calendar-accent: color-mix(in srgb, var(--bs-primary) 70%, var(--bs-info));
        --review-leave-calendar-accent-bg: color-mix(in srgb, var(--bs-primary-bg-subtle) 70%, var(--bs-info-bg-subtle));
    }
    .review-leave-calendar-event.leave-calendar-code-l180,
    .review-leave-calendar-legend-dot.leave-calendar-code-l180 {
        --review-leave-calendar-accent: color-mix(in srgb, var(--bs-warning) 48%, var(--bs-secondary));
        --review-leave-calendar-accent-bg: color-mix(in srgb, var(--bs-warning-bg-subtle) 60%, var(--bs-secondary-bg-subtle));
    }
    .review-leave-calendar-event.leave-calendar-code-l190,
    .review-leave-calendar-legend-dot.leave-calendar-code-l190 {
        --review-leave-calendar-accent: color-mix(in srgb, var(--bs-success) 56%, var(--bs-info));
        --review-leave-calendar-accent-bg: color-mix(in srgb, var(--bs-success-bg-subtle) 60%, var(--bs-info-bg-subtle));
    }
    .review-leave-calendar-event.leave-calendar-code-l210,
    .review-leave-calendar-legend-dot.leave-calendar-code-l210 {
        --review-leave-calendar-accent: color-mix(in srgb, var(--bs-primary) 54%, var(--bs-info));
        --review-leave-calendar-accent-bg: color-mix(in srgb, var(--bs-primary-bg-subtle) 54%, var(--bs-info-bg-subtle));
    }
    .review-leave-calendar-event.leave-calendar-code-l220,
    .review-leave-calendar-legend-dot.leave-calendar-code-l220 {
        --review-leave-calendar-accent: color-mix(in srgb, var(--bs-danger) 62%, var(--bs-warning));
        --review-leave-calendar-accent-bg: color-mix(in srgb, var(--bs-danger-bg-subtle) 64%, var(--bs-warning-bg-subtle));
    }
    .review-leave-calendar-event.leave-calendar-code-l230,
    .review-leave-calendar-legend-dot.leave-calendar-code-l230 {
        --review-leave-calendar-accent: color-mix(in srgb, var(--bs-danger) 54%, var(--bs-primary));
        --review-leave-calendar-accent-bg: color-mix(in srgb, var(--bs-danger-bg-subtle) 56%, var(--bs-primary-bg-subtle));
    }
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
    .review-leave-calendar-legend {
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
    .review-leave-calendar-legend-label {
        color: var(--bs-secondary-color);
        font-size: .72rem;
        font-weight: 800;
        letter-spacing: .02em;
        text-transform: uppercase;
    }
    .review-leave-calendar-legend-item {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        color: var(--bs-body-color);
        font-size: .8rem;
        font-weight: 700;
        white-space: nowrap;
    }
    .review-leave-calendar-legend-dot {
        --review-leave-calendar-accent: var(--bs-primary);
        width: .72rem;
        height: .72rem;
        border-radius: 999px;
        background: var(--review-leave-calendar-accent);
        box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--bs-body-color) 15%, transparent);
    }
    .review-leave-calendar-legend-dot.holiday {
        background: var(--bs-info);
    }
    .review-leave-calendar-legend-dot.current {
        border: 2px solid color-mix(in srgb, var(--bs-primary) 62%, var(--app-card-bg));
        background: color-mix(in srgb, var(--bs-primary-bg-subtle) 52%, var(--app-card-bg));
    }
    .review-leave-calendar-legend-dot.clash {
        background: var(--bs-danger);
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
        .review-leave-calendar-legend {
            align-items: flex-start;
        }
        .review-leave-calendar-legend-label {
            width: 100%;
        }
    }
    @media (max-width: 440px) {
        .review-leave-calendar-legend-item {
            white-space: normal;
        }
    }
</style>

@php
    $reviewCalendarDays = collect($reviewCalendarMonths)
        ->flatMap(fn ($calendarMonth) => collect($calendarMonth['weeks'])->flatten(1))
        ->filter(fn ($day) => $day['in_month']);
    $reviewCalendarLeaveEvents = $reviewCalendarDays
        ->flatMap(fn ($day) => $day['events'])
        ->filter(fn ($event) => ($event['type'] ?? null) === 'leave');
    $reviewCalendarHolidayEvents = $reviewCalendarDays
        ->flatMap(fn ($day) => $day['events'])
        ->filter(fn ($event) => ($event['type'] ?? null) === 'holiday');
    $reviewAttendanceCodeOrder = array_flip(array_keys(config('timesheet.attendance_codes', [])));
    $reviewCalendarLeaveTypeLegend = $reviewCalendarLeaveEvents
        ->filter(fn ($event) => ! empty($event['attendance_code']))
        ->unique('attendance_code')
        ->sortBy(fn ($event) => $reviewAttendanceCodeOrder[$event['attendance_code']] ?? 999)
        ->map(fn ($event) => [
            'code' => $event['attendance_code'],
            'label' => $event['leave_type_label'] ?? $event['attendance_code'],
            'class' => 'leave-calendar-code-'.strtolower((string) $event['attendance_code']),
        ])
        ->values();
    $reviewCalendarHasClashes = $reviewCalendarLeaveEvents->contains(fn ($event) => $event['is_clashing'] ?? false);
@endphp

<div class="content-card mt-3">
    <div class="content-card-header">
        <h2 class="h5 mb-1">Leave calendar view</h2>
        <div class="small text-muted">Shows this request, visible active leave, applicable holidays, and date clashes in the same calendar month.</div>
    </div>
    <div class="content-card-body">
        @if($reviewCalendarLeaveTypeLegend->isNotEmpty() || $reviewCalendarHolidayEvents->isNotEmpty())
            <div class="review-leave-calendar-legend" aria-label="Calendar legend">
                <span class="review-leave-calendar-legend-label">Leave types</span>
                @foreach($reviewCalendarLeaveTypeLegend as $legendItem)
                    <span class="review-leave-calendar-legend-item">
                        <span class="review-leave-calendar-legend-dot {{ $legendItem['class'] }}" aria-hidden="true"></span>
                        {{ $legendItem['code'] }} - {{ $legendItem['label'] }}
                    </span>
                @endforeach
                <span class="review-leave-calendar-legend-item"><span class="review-leave-calendar-legend-dot current" aria-hidden="true"></span>This request</span>
                @if($reviewCalendarHolidayEvents->isNotEmpty())
                    <span class="review-leave-calendar-legend-item"><span class="review-leave-calendar-legend-dot holiday" aria-hidden="true"></span>Holiday</span>
                @endif
                @if($reviewCalendarHasClashes)
                    <span class="review-leave-calendar-legend-item"><span class="review-leave-calendar-legend-dot clash" aria-hidden="true"></span>Clash</span>
                @endif
            </div>
        @endif
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
                                                ! empty($event['attendance_code']) ? 'leave-calendar-code-'.strtolower((string) $event['attendance_code']) : '',
                                                'is-current' => $event['is_current'],
                                                'is-clashing' => $event['is_clashing'] ?? false,
                                            ]) title="{{ $event['leave_type'] }} - {{ $event['duration'] }}">
                                                <div class="review-leave-calendar-event-label">{{ $event['label'] }}</div>
                                                @if(($event['type'] ?? null) === 'leave')
                                                    <div class="review-leave-calendar-event-meta">{{ $event['leave_type'] }}</div>
                                                @endif
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
