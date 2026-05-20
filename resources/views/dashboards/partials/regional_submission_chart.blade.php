@php
    $regions = $regionalSubmissionSummary['regions'];
    $total = $regionalSubmissionSummary['total'];
    $segments = [
        ['label' => 'UAE submitted', 'class' => 'chart-uae-submitted', 'count' => $regions['uae']['submitted']],
        ['label' => 'UAE not submitted', 'class' => 'chart-uae-missing', 'count' => $regions['uae']['not_submitted']],
        ['label' => 'PH submitted', 'class' => 'chart-ph-submitted', 'count' => $regions['ph']['submitted']],
        ['label' => 'PH not submitted', 'class' => 'chart-ph-missing', 'count' => $regions['ph']['not_submitted']],
        ['label' => 'Unknown submitted', 'class' => 'chart-unknown-submitted', 'count' => $regions['unknown']['submitted']],
        ['label' => 'Unknown not submitted', 'class' => 'chart-unknown-missing', 'count' => $regions['unknown']['not_submitted']],
    ];
    $cursor = 0;
    $gradientStops = [];

    foreach ($segments as $segment) {
        if ($total === 0 || $segment['count'] === 0) {
            continue;
        }

        $end = $cursor + (($segment['count'] / $total) * 360);
        $gradientStops[] = "var(--{$segment['class']}) {$cursor}deg {$end}deg";
        $cursor = $end;
    }

    $chartBackground = $gradientStops
        ? 'conic-gradient('.implode(', ', $gradientStops).')'
        : 'var(--app-muted-bg)';
@endphp

<div class="content-card submission-chart-card">
    <div class="content-card-header">
        <h2 class="h5 mb-1">Regional submission status</h2>
        <div class="small text-muted">
            @if($period)
                Week {{ $period->week_number }}, {{ $period->year }}: {{ $period->start_date->format('M d, Y') }} - {{ $period->end_date->format('M d, Y') }}
            @else
                No weekly period available
            @endif
        </div>
    </div>
    <div class="content-card-body">
        <div class="regional-chart-layout">
            <div class="submission-donut-wrap">
                <div class="submission-donut" style="background: {{ $chartBackground }};" role="img" aria-label="Regional submission status chart">
                    <div class="submission-donut-center">
                        <div class="stat-value">{{ $regionalSubmissionSummary['submitted'] }}</div>
                        <div class="small text-muted">Submitted</div>
                        <div class="small text-muted">{{ $total }} total</div>
                    </div>
                </div>
            </div>
            <div class="regional-chart-details">
                <div class="row g-2">
                    @foreach(['uae', 'ph', 'unknown'] as $regionKey)
                        @php($region = $regions[$regionKey])
                        <div class="col-md-4">
                            <div class="regional-stat">
                                <div class="meta-label regional-label">
                                    @if(in_array($regionKey, ['uae', 'ph'], true))
                                        <img class="country-flag" src="{{ asset('images/flag/'.($regionKey === 'uae' ? 'ae' : 'ph').'.svg') }}" alt="">
                                    @endif
                                    <span>{{ $region['label'] }}</span>
                                </div>
                                <div class="regional-stat-row">
                                    <span class="chart-key chart-key-{{ $regionKey }}-submitted"></span>
                                    <span>Submitted</span>
                                    <strong>{{ $region['submitted'] }}</strong>
                                </div>
                                <div class="regional-stat-row">
                                    <span class="chart-key chart-key-{{ $regionKey }}-missing"></span>
                                    <span>Not submitted</span>
                                    <strong>{{ $region['not_submitted'] }}</strong>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                @if(($regions['unknown']['submitted'] + $regions['unknown']['not_submitted']) > 0)
                    <div class="small text-muted mt-3">Unknown includes active employees without a recognized employee number prefix.</div>
                @endif
            </div>
        </div>
    </div>
</div>
