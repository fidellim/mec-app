@if(! empty($leaveBalances))
    <div class="content-card {{ $class ?? 'mb-4' }}">
        <div class="content-card-header">
            <h2 class="h5 mb-1">{{ $title ?? 'Leave balances' }}</h2>
            <div class="small text-muted">{{ $description ?? 'Eligible leave entitlements for the current calendar year.' }}</div>
        </div>
        <div class="content-card-body">
            <div class="row g-3">
                @foreach($leaveBalances as $balance)
                    @php
                        $allowance = (float) ($balance['allowance'] ?? 0);
                        $used = (float) ($balance['used'] ?? 0);
                        $remaining = (float) ($balance['remaining'] ?? 0);
                        $usedPercent = $allowance > 0 ? min(100, max(0, ($used / $allowance) * 100)) : 0;
                        $remainingIsDepleted = $remaining <= 0;
                    @endphp
                    <div class="col-md-6">
                        <div class="leave-balance-card h-100">
                            <div class="leave-balance-card-header">
                                <div class="leave-balance-heading">
                                    <h3 class="leave-balance-title">{{ $balance['label'] }}</h3>
                                    <div class="leave-balance-meta">{{ $balance['attendance_code'] }} for {{ $balance['year'] }} - {{ $balance['region_label'] }}</div>
                                </div>
                                <span class="badge leave-balance-source {{ $balance['uses_override'] ? 'text-bg-info' : 'bg-body-secondary border text-body' }}">
                                    {{ $balance['source_label'] ?? ($balance['uses_override'] ? 'Current-year override' : 'Regional default') }}
                                </span>
                            </div>

                            <div class="leave-balance-metrics">
                                <div class="leave-balance-metric">
                                    <div class="leave-balance-metric-label">{{ $balance['allowance_label'] ?? 'Allowance' }}</div>
                                    <div class="leave-balance-metric-value">{{ $balance['formatted']['allowance'] }} days</div>
                                </div>
                                <div class="leave-balance-metric leave-balance-metric-muted">
                                    <div class="leave-balance-metric-label">Used</div>
                                    <div class="leave-balance-metric-value">{{ $balance['formatted']['used'] }} days</div>
                                </div>
                                <div class="leave-balance-metric leave-balance-metric-remaining {{ $remainingIsDepleted ? 'is-depleted' : '' }}">
                                    <div class="leave-balance-metric-label">{{ $balance['remaining_label'] ?? 'Remaining' }}</div>
                                    <div class="leave-balance-metric-value">{{ $balance['formatted']['remaining'] }} days</div>
                                </div>
                            </div>

                            <div class="leave-balance-progress" role="img" aria-label="{{ $balance['formatted']['used'] }} of {{ $balance['formatted']['allowance'] }} days used">
                                <span style="width: {{ $usedPercent }}%;"></span>
                            </div>

                            @if(! empty($balance['description']))
                                <div class="leave-balance-note">{{ $balance['description'] }}</div>
                            @endif
                            @if(! empty($balance['pay_bands']))
                                <div class="leave-balance-pay-bands">
                                    <div class="d-flex flex-wrap gap-2">
                                        @foreach($balance['pay_bands'] as $band)
                                            <span class="badge bg-body-secondary border text-body">
                                                {{ $band['label'] }}: {{ $band['formatted_used_days'] }} of {{ $band['formatted_days'] }} days used
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endif
