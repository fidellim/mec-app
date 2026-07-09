@php
    $regions = $regionalSubmissionSummary['regions'];
    $total = $regionalSubmissionSummary['total'];
    $submitted = $regionalSubmissionSummary['submitted'];
    $notSubmitted = $regionalSubmissionSummary['not_submitted'];
    $submittedPercent = $total > 0 ? round(($submitted / $total) * 100) : 0;
    $actionUrl = $actionUrl ?? null;
    $actionLabel = $actionLabel ?? 'Review missing submissions';
@endphp

<div class="content-card submission-chart-card">
    <div class="content-card-header regional-status-header">
        <div>
            <h2 class="h5 mb-1">Regional submission status</h2>
            <div class="small text-muted">
                @if($period)
                    Week {{ $period->week_number }}, {{ $period->year }}: {{ $period->start_date->format('M d, Y') }} - {{ $period->end_date->format('M d, Y') }}
                @else
                    No weekly period available
                @endif
            </div>
        </div>
        @if($actionUrl && $total > 0)
            <a class="btn btn-sm btn-outline-primary" href="{{ $actionUrl }}">{{ $actionLabel }}</a>
        @endif
    </div>
    <div class="content-card-body">
        @if($total > 0)
            <div class="regional-status-layout">
                <div class="regional-status-summary">
                    <div class="dashboard-kicker">Overall completion</div>
                    <div class="regional-status-percent">{{ $submittedPercent }}%</div>
                    <div class="regional-status-caption">{{ $submitted }} of {{ $total }} active employees submitted.</div>
                    <div class="regional-total-progress" role="img" aria-label="{{ $submittedPercent }}% submitted from {{ $total }} active employees">
                        <span style="width: {{ $submittedPercent }}%;"></span>
                    </div>
                    <div class="regional-status-metrics">
                        <div class="regional-status-metric">
                            <span class="meta-label">Submitted</span>
                            <strong>{{ $submitted }}</strong>
                        </div>
                        <div class="regional-status-metric is-attention">
                            <span class="meta-label">Not submitted</span>
                            <strong>{{ $notSubmitted }}</strong>
                        </div>
                    </div>
                </div>

                <div class="regional-progress-list">
                    @foreach(['uae', 'ph', 'unknown'] as $regionKey)
                        @php
                            $region = $regions[$regionKey];
                            $regionSubmitted = $region['submitted'];
                            $regionMissing = $region['not_submitted'];
                            $regionTotal = $regionSubmitted + $regionMissing;
                            $regionPercent = $regionTotal > 0 ? round(($regionSubmitted / $regionTotal) * 100) : 0;
                        @endphp
                        @continue($regionKey === 'unknown' && $regionTotal === 0)
                        <div class="regional-progress-row">
                            <div class="regional-progress-row-header">
                                <div class="regional-label">
                                    @if(in_array($regionKey, ['uae', 'ph'], true))
                                        <img class="country-flag" src="{{ asset('images/flag/'.($regionKey === 'uae' ? 'ae' : 'ph').'.svg') }}" alt="">
                                    @endif
                                    <div>
                                        <div class="regional-progress-title">{{ $region['label'] }}</div>
                                        <div class="regional-progress-meta">{{ $regionSubmitted }} submitted / {{ $regionMissing }} not submitted</div>
                                    </div>
                                </div>
                                <div class="regional-progress-percent">{{ $regionPercent }}%</div>
                            </div>
                            <div class="regional-progress-track" role="img" aria-label="{{ $region['label'] }} is {{ $regionPercent }}% submitted">
                                <span style="width: {{ $regionPercent }}%;"></span>
                            </div>
                            <div class="regional-progress-counts">
                                <span>{{ $regionTotal }} active employees</span>
                                @if($regionMissing > 0)
                                    <strong>{{ $regionMissing }} need follow-up</strong>
                                @else
                                    <strong>All submitted</strong>
                                @endif
                            </div>
                        </div>
                    @endforeach
                    @if(($regions['unknown']['submitted'] + $regions['unknown']['not_submitted']) > 0)
                        <div class="small text-muted">Unknown includes active employees without a recognized employee number prefix.</div>
                    @endif
                </div>
            </div>
        @else
            <div class="regional-status-empty">
                <div class="dashboard-kicker">No employees to track</div>
                <div class="mt-1">There are no active employees in this reporting scope yet.</div>
            </div>
        @endif
    </div>
</div>
