<div class="content-card">
    <div class="content-card-header">
        <h2 class="h5 mb-1">Leave details</h2>
        <div class="small text-muted">{{ $leavePlan->leaveLabel() }}</div>
    </div>
    <div class="content-card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="meta-label">Employee</div>
                <div class="meta-value">{{ $leavePlan->user?->name ?? auth()->user()->name }}</div>
            </div>
            <div class="col-md-4">
                <div class="meta-label">Department</div>
                <div class="meta-value">{{ $leavePlan->department?->name ?: '-' }}</div>
            </div>
            <div class="col-md-4">
                <div class="meta-label">Status</div>
                <div>@include('partials.status', ['status' => $leavePlan->status])</div>
            </div>
            <div class="col-md-4">
                <div class="meta-label">Date range</div>
                <div class="meta-value">{{ $leavePlan->start_date->toFormattedDateString() }} to {{ $leavePlan->end_date->toFormattedDateString() }}</div>
            </div>
            <div class="col-md-4">
                <div class="meta-label">Duration</div>
                <div class="meta-value">{{ $leavePlan->leaveLengthLabel() }}</div>
            </div>
            @php($leavePayBreakdowns = in_array(auth()->user()?->role, ['admin', 'super_admin'], true) ? app(\App\Services\LeaveEntitlementService::class)->payBreakdownForPlan($leavePlan) : [])
            @if(! empty($leavePayBreakdowns))
                <div class="col-12">
                    @include('shared.leave_pay_breakdown', ['leavePayBreakdowns' => $leavePayBreakdowns])
                </div>
            @endif
            <div class="col-md-4">
                <div class="meta-label">Submitted</div>
                <div class="meta-value">{{ $leavePlan->submitted_at?->format('M j, Y g:i A') ?: '-' }}</div>
            </div>
            <div class="col-md-4">
                <div class="meta-label">Approval progress</div>
                <div class="meta-value">{{ $leavePlan->approvalProgressLabel() }}</div>
            </div>
            <div class="col-12">
                <div class="meta-label">Reason</div>
                <div>{{ $leavePlan->reason ?: '-' }}</div>
            </div>
            @if($leavePlan->hod_approved_at || $leavePlan->director_approved_at || $leavePlan->hr_approved_at)
                <div class="col-12">
                    <div class="meta-label">Approval chain</div>
                    <div class="row g-2 mt-1">
                        <div class="col-md-4">
                            <div class="border rounded p-2 h-100">
                                <div class="fw-semibold">Head of Department</div>
                                <div class="small text-muted">{{ $leavePlan->hodApprover?->name ?: 'Pending' }}</div>
                                <div class="small text-muted">{{ $leavePlan->hod_approved_at?->format('M j, Y g:i A') ?: '-' }}</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-2 h-100">
                                <div class="fw-semibold">Director</div>
                                <div class="small text-muted">{{ $leavePlan->directorApprover?->name ?: 'Pending' }}</div>
                                <div class="small text-muted">{{ $leavePlan->director_approved_at?->format('M j, Y g:i A') ?: '-' }}</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-2 h-100">
                                <div class="fw-semibold">HR Department</div>
                                <div class="small text-muted">{{ $leavePlan->hrApprover?->name ?: 'Pending' }}</div>
                                <div class="small text-muted">{{ $leavePlan->hr_approved_at?->format('M j, Y g:i A') ?: '-' }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
            @if($leavePlan->approved_at)
                <div class="col-md-6">
                    <div class="meta-label">Approved by</div>
                    <div class="meta-value">{{ $leavePlan->approver?->name ?: '-' }}</div>
                </div>
                <div class="col-md-6">
                    <div class="meta-label">Approved at</div>
                    <div class="meta-value">{{ $leavePlan->approved_at->format('M j, Y g:i A') }}</div>
                </div>
            @endif
            @if($leavePlan->cancellation_reason)
                <div class="col-12">
                    <div class="meta-label">Cancellation reason</div>
                    <div>{{ $leavePlan->cancellation_reason }}</div>
                </div>
            @endif
            @if($leavePlan->recall_reason)
                <div class="col-12">
                    <div class="meta-label">Recall reason</div>
                    <div>{{ $leavePlan->recall_reason }}</div>
                    @if($leavePlan->recaller || $leavePlan->recalled_at)
                        <div class="small text-muted mt-1">
                            Recalled
                            @if($leavePlan->recaller)
                                by {{ $leavePlan->recaller->name }}
                            @endif
                            @if($leavePlan->recalled_at)
                                on {{ $leavePlan->recalled_at->format('M j, Y g:i A') }}
                            @endif
                        </div>
                    @endif
                </div>
            @endif
            @if($leavePlan->void_reason)
                <div class="col-12">
                    <div class="meta-label">Void reason</div>
                    <div>{{ $leavePlan->void_reason }}</div>
                    @if($leavePlan->voider || $leavePlan->voided_at)
                        <div class="small text-muted mt-1">
                            Voided
                            @if($leavePlan->voider)
                                by {{ $leavePlan->voider->name }}
                            @endif
                            @if($leavePlan->voided_at)
                                on {{ $leavePlan->voided_at->format('M j, Y g:i A') }}
                            @endif
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
