@php
    $months = $analytics['months'];
    $monthly = $chartType === 'monthly';
    $width = max(280, $months->count() * 80 + 110);
    $maximum = max(1, $monthly ? $months->max(fn ($m) => $m['regular'] + $m['overtime']) : max($analytics['allocated'], $analytics['approved'] + $analytics['pending']));
    $y = fn ($value) => 210 - 170 * $value / $maximum;
    $x = fn ($index) => $months->count() === 1 ? ($width + 45) / 2 : 90 + $index * ($width - 140) / max(1, $months->count() - 1);
@endphp
<div class="d-flex flex-wrap gap-3 small mb-3">
    <span><span class="analytics-key" aria-hidden="true"></span> {{ $monthly ? 'Regular hours' : 'Approved hours' }}</span>
    <span><span class="analytics-key analytics-key-overtime" aria-hidden="true"></span> {{ $monthly ? 'Overtime' : 'Approved + pending (dashed)' }}</span>
    @if(!$monthly && $analytics['hasAllocation'])<span><span class="analytics-key analytics-key-allocation" aria-hidden="true"></span>Allocated hours</span>@endif
</div>
<div class="analytics-chart-scroll" data-chart-months="{{ $months->count() }}" tabindex="0" role="region" aria-label="{{ $monthly ? 'Monthly hours chart' : 'Cumulative consumption chart' }}; scroll horizontally to see all months">
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {{ $width }} 260" width="{{ $width }}" height="260" style="width:{{ $width }}px" preserveAspectRatio="xMidYMid meet" role="img" aria-labelledby="{{ $chartType }}-title {{ $chartType }}-description">
    <title id="{{ $chartType }}-title">{{ $monthly ? 'Monthly regular and overtime hours' : 'Cumulative approved and pending hours against allocation' }}</title>
    <desc id="{{ $chartType }}-description">Hours from {{ $analytics['from'] }} to {{ $analytics['to'] }}. Exact values are available in the data table below.</desc>
    @foreach([0, 0.25, 0.5, 0.75, 1] as $fraction)
        <line data-chart-rule x1="65" x2="{{ $width - 20 }}" y1="{{ $y($maximum * $fraction) }}" y2="{{ $y($maximum * $fraction) }}" stroke="var(--bs-border-color)" />
        <text x="58" y="{{ $y($maximum * $fraction) + 4 }}" text-anchor="end" fill="currentColor" font-size="11">{{ number_format($maximum * $fraction, $maximum < 10 ? 2 : 0) }}</text>
    @endforeach
    <text x="15" y="20" fill="currentColor" font-size="11">Hours</text>
    @if(!$monthly)
        @if($analytics['hasAllocation'])<line data-chart-rule x1="65" x2="{{ $width - 20 }}" y1="{{ $y($analytics['allocated']) }}" y2="{{ $y($analytics['allocated']) }}" stroke="currentColor" stroke-width="1.5" />@endif
        <polyline points="{{ $months->map(fn ($m, $i) => $x($i).','.$y($m['approved'] + $m['pending']))->implode(' ') }}" fill="none" stroke="var(--bs-warning-text-emphasis)" stroke-width="3" stroke-dasharray="7 5" />
        <polyline points="{{ $months->map(fn ($m, $i) => $x($i).','.$y($m['approved']))->implode(' ') }}" fill="none" stroke="var(--bs-primary)" stroke-width="3" />
    @endif
    @foreach($months as $month)
        @php($position = $x($loop->index))
        @if($monthly)
            <rect data-chart-index="{{ $loop->index }}" x="{{ $position - 18 }}" y="{{ $y($month['regular']) }}" width="36" height="{{ 210 - $y($month['regular']) }}" fill="var(--bs-primary)"><title>{{ $month['label'] }} regular: {{ number_format($month['regular'], 2) }} hours</title></rect>
            <rect data-chart-index="{{ $loop->index }}" x="{{ $position - 18 }}" y="{{ $y($month['regular'] + $month['overtime']) }}" width="36" height="{{ 210 - $y($month['overtime']) }}" fill="var(--bs-warning-text-emphasis)"><title>{{ $month['label'] }} overtime: {{ number_format($month['overtime'], 2) }} hours</title></rect>
        @else
            <circle data-chart-index="{{ $loop->index }}" cx="{{ $position }}" cy="{{ $y($month['approved'] + $month['pending']) }}" r="4" fill="var(--bs-warning-text-emphasis)"><title>{{ $month['label'] }} approved + pending: {{ number_format($month['approved'] + $month['pending'], 2) }} hours</title></circle>
            <circle data-chart-index="{{ $loop->index }}" cx="{{ $position }}" cy="{{ $y($month['approved']) }}" r="3" fill="var(--bs-primary)"><title>{{ $month['label'] }} approved: {{ number_format($month['approved'], 2) }} hours</title></circle>
        @endif
        <text data-chart-index="{{ $loop->index }}" x="{{ $position }}" y="235" text-anchor="middle" fill="currentColor" font-size="11">{{ $month['label'] }}</text>
    @endforeach
</svg>
</div>
<p class="small text-muted d-sm-none mt-2 mb-0">Swipe horizontally across the chart to see all months.</p>
@if($months->count() === 1)<p class="small text-muted mt-2 mb-0">One month in this range; a trend requires multiple months.</p>@endif
<details class="mt-3">
    <summary class="small">View {{ $monthly ? 'monthly' : 'cumulative' }} data table</summary>
    <div class="table-responsive mt-2"><table class="table table-sm align-middle"><caption class="visually-hidden">{{ $monthly ? 'Monthly' : 'Cumulative' }} hours, including pending submissions</caption><thead><tr><th>Month</th><th class="text-end">{{ $monthly ? 'Regular' : 'Approved' }}</th><th class="text-end">{{ $monthly ? 'Overtime' : 'Pending' }}</th><th class="text-end">Total hours</th></tr></thead><tbody>
    @foreach($months as $month)
        @php($first = $month[$monthly ? 'regular' : 'approved'])
        @php($second = $month[$monthly ? 'overtime' : 'pending'])
        <tr><th scope="row">{{ $month['label'] }}</th><td class="text-end">{{ number_format($first, 2) }}</td><td class="text-end">{{ number_format($second, 2) }}</td><td class="text-end">{{ number_format($first + $second, 2) }}</td></tr>
    @endforeach
    </tbody></table></div>
</details>
