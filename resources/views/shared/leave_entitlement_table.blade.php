<div class="content-card overflow-hidden">
    <div class="content-card-header">
        <h2 class="h5 mb-1">{{ $title ?? 'Employee balances' }}</h2>
        <div class="small text-muted">{{ $description ?? 'Used or reserved includes submitted, approved, and cancellation-requested leave plans.' }}</div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Department</th>
                    <th>Leave type</th>
                    <th>Allowance</th>
                    <th>Used or reserved</th>
                    <th>Remaining</th>
                    <th>Basis</th>
                </tr>
            </thead>
            <tbody>
                @forelse($employees as $employee)
                    @forelse($employee->leaveBalances as $balance)
                        <tr>
                            @if($loop->first)
                                <td rowspan="{{ count($employee->leaveBalances) }}">
                                    <div class="fw-semibold">{{ $employee->name }}</div>
                                    <div class="small text-muted">{{ $employee->employee_code ?: $employee->email }}</div>
                                </td>
                                <td rowspan="{{ count($employee->leaveBalances) }}">{{ $employee->department?->name ?: '-' }}</td>
                            @endif
                            <td>
                                <div class="fw-semibold">{{ $balance['label'] }}</div>
                                <div class="small text-muted">{{ $balance['attendance_code'] }} for {{ $balance['year'] }} - {{ $balance['region_label'] }}</div>
                            </td>
                            <td>
                                <div>{{ $balance['formatted']['allowance'] }} days</div>
                                <div class="small text-muted">{{ $balance['allowance_label'] ?? 'Allowance' }}</div>
                            </td>
                            <td>{{ $balance['formatted']['used'] }} days</td>
                            <td>
                                <div>{{ $balance['formatted']['remaining'] }} days</div>
                                <div class="small text-muted">{{ $balance['remaining_label'] ?? 'Remaining' }}</div>
                            </td>
                            <td>
                                <span class="badge {{ $balance['uses_override'] ? 'text-bg-info' : 'bg-body-secondary border text-body' }}">
                                    {{ $balance['source_label'] ?? ($balance['uses_override'] ? 'Current-year override' : 'Regional default') }}
                                </span>
                                @if(! empty($balance['description']))
                                    <div class="small text-muted mt-1">{{ $balance['description'] }}</div>
                                @endif
                                @if(! empty($balance['pay_bands']))
                                    <div class="d-flex flex-wrap gap-1 mt-2">
                                        @foreach($balance['pay_bands'] as $band)
                                            <span class="badge bg-body-secondary border text-body">
                                                {{ $band['label'] }}: {{ $band['formatted_days'] }} days
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $employee->name }}</div>
                                <div class="small text-muted">{{ $employee->employee_code ?: $employee->email }}</div>
                            </td>
                            <td>{{ $employee->department?->name ?: '-' }}</td>
                            <td colspan="5" class="text-muted">No eligible leave entitlements for this profile.</td>
                        </tr>
                    @endforelse
                @empty
                    <tr>
                        <td colspan="7" class="empty-state">{{ $emptyMessage ?? 'No visible active employees found for the selected view.' }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
