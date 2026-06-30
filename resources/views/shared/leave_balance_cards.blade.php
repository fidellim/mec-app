@if(! empty($leaveBalances))
    <div class="content-card mb-4">
        <div class="content-card-header">
            <h2 class="h5 mb-1">{{ $title ?? 'Leave balances' }}</h2>
            <div class="small text-muted">{{ $description ?? 'Annual and sick leave balances for the current calendar year.' }}</div>
        </div>
        <div class="content-card-body">
            <div class="row g-3">
                @foreach($leaveBalances as $balance)
                    <div class="col-md-6">
                        <div class="border rounded p-3 h-100">
                            <div class="d-flex flex-column flex-sm-row justify-content-between gap-2 mb-3">
                                <div>
                                    <h3 class="h6 mb-1">{{ $balance['label'] }}</h3>
                                    <div class="small text-muted">{{ $balance['attendance_code'] }} for {{ $balance['year'] }} - {{ $balance['region_label'] }}</div>
                                </div>
                                <div>
                                    <span class="badge {{ $balance['uses_override'] ? 'text-bg-info' : 'text-bg-light border text-dark' }}">
                                        {{ $balance['uses_override'] ? 'User override' : 'Regional default' }}
                                    </span>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-sm-4">
                                    <div class="small text-muted">Allowance</div>
                                    <div class="h4 mb-0">{{ $balance['formatted']['allowance'] }} days</div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="small text-muted">Used or reserved</div>
                                    <div class="h4 mb-0">{{ $balance['formatted']['used'] }} days</div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="small text-muted">Remaining</div>
                                    <div class="h4 mb-0">{{ $balance['formatted']['remaining'] }} days</div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endif
