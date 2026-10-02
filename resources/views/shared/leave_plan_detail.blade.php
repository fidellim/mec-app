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
            @if($leavePlan->attendance_code === \App\Services\LeaveEntitlementService::ANNUAL_LEAVE_CODE)
                @php($coverage = app(\App\Services\LeaveCoverageService::class)->preview($leavePlan->user, $leavePlan->getAttributes(), $leavePlan->id))
                <div class="col-12">
                    <div class="border rounded p-3 bg-body-tertiary">
                        @php($overlapDays = $coverage['approved'] + $coverage['pending'])
                        <div class="fw-semibold mb-1">Annual leave summary</div>
                        <div class="fw-semibold">
                            {{ $coverage['selected'] }} {{ $coverage['selected'] == 1 ? 'day' : 'days' }} requested
                            @if($overlapDays > 0)
                                · {{ $overlapDays }} {{ $overlapDays == 1 ? 'day overlaps' : 'days overlap' }} other annual leave requests
                            @elseif(empty($coverage['overlaps']) && $leavePlan->start_date->isSameDay($leavePlan->end_date))
                                · {{ $leavePlan->start_date->format('j M Y') }} ({{ $leavePlan->duration_type === 'half_day' ? $leavePlan->half_day_period : 'full day' }})
                            @endif
                        </div>
                        @if(empty($coverage['overlaps']))
                            <div class="small text-muted mt-2">No overlap with other leave requests.</div>
                        @else
                            @if($overlapDays > 0)
                                <div class="small mt-2">Already covered: {{ collect([
                                    $coverage['approved'] > 0 ? $coverage['approved'].' '.($coverage['approved'] == 1 ? 'day' : 'days').' approved' : null,
                                    $coverage['pending'] > 0 ? $coverage['pending'].' '.($coverage['pending'] == 1 ? 'day' : 'days').' pending' : null,
                                ])->filter()->implode(', ') }}.</div>
                                <div class="small mt-1">{{ in_array($leavePlan->status, [\App\Models\LeavePlan::STATUS_APPROVED, \App\Models\LeavePlan::STATUS_CANCELLATION_REQUESTED]) ? 'Additional days counted toward the balance' : 'Additional days if approved' }}: <strong>{{ $coverage['additional'] }}</strong>.</div>
                            @else
                                <div class="small mt-2">Overlaps another leave type.</div>
                            @endif
                            <details class="small mt-2">
                                <summary>View overlap details</summary>
                                @foreach($coverage['overlaps'] as $overlap)
                                    <div class="mt-2">Request #{{ $overlap['id'] }} ({{ str_replace('_', ' ', $overlap['status']) }}): {{ $overlap['dates_label'] }}</div>
                                @endforeach
                                @if($coverage['additional_dates_label'])
                                    <div class="mt-2">Additional dates: {{ $coverage['additional_dates_label'] }}</div>
                                @endif
                                <div class="text-muted mt-2">Shared dates count once. Based on current requests.</div>
                            </details>
                        @endif
                        @if(in_array($leavePlan->status, [\App\Models\LeavePlan::STATUS_APPROVED, \App\Models\LeavePlan::STATUS_CANCELLATION_REQUESTED]))
                            <div class="small mt-2">If cancellation is approved: {{ $coverage['additional'] }} {{ $coverage['additional'] == 1 ? 'day' : 'days' }} would return to the balance, based on current overlaps.</div>
                        @endif
                    </div>
                </div>
            @endif
            @if($leavePlan->attendance_code === \App\Services\LeaveEntitlementService::BEREAVEMENT_COMPASSIONATE_LEAVE_CODE)
                <div class="col-md-4">
                    <div class="meta-label">Bereavement relationship</div>
                    <div class="meta-value">{{ $leavePlan->bereavementRelationshipLabel() ?: 'Unspecified' }}</div>
                </div>
            @endif
            <?php $leavePayBreakdowns = in_array(auth()->user()?->role, ['admin', 'super_admin'], true) ? app(\App\Services\LeaveEntitlementService::class)->payBreakdownForPlan($leavePlan) : []; ?>
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
            @if($leavePlan->policy_exception_reason)
                <div class="col-12">
                    <div class="border rounded p-3">
                        <div class="meta-label">Policy exception</div>
                        <div>{{ $leavePlan->policy_exception_reason }}</div>
                    </div>
                </div>
            @endif
            @if($leavePlan->hod_approved_at || $leavePlan->director_approved_at || $leavePlan->hr_approved_at)
                <?php
                    $approvalStages = [
                        [
                            'key' => \App\Models\LeavePlan::APPROVAL_STAGE_HOD,
                            'label' => 'Head of Department',
                            'approvedAt' => $leavePlan->hod_approved_at,
                            'approver' => $leavePlan->hodApprover,
                            'notRequired' => $leavePlan->user?->role === 'hod',
                        ],
                        [
                            'key' => \App\Models\LeavePlan::APPROVAL_STAGE_DIRECTOR,
                            'label' => 'Director',
                            'approvedAt' => $leavePlan->director_approved_at,
                            'approver' => $leavePlan->directorApprover,
                            'notRequired' => false,
                        ],
                        [
                            'key' => \App\Models\LeavePlan::APPROVAL_STAGE_HR,
                            'label' => 'HR Department',
                            'approvedAt' => $leavePlan->hr_approved_at,
                            'approver' => $leavePlan->hrApprover,
                            'notRequired' => false,
                        ],
                    ];
                    $stageOrder = array_column($approvalStages, 'key');
                    $currentStageIndex = array_search($leavePlan->approval_stage, $stageOrder, true);
                ?>
                <div class="col-12">
                    <div class="meta-label">Approval chain</div>
                    <div class="row g-2 mt-1">
                        @foreach($approvalStages as $stageIndex => $stage)
                            <?php
                                $isPending = $leavePlan->approval_stage === $stage['key'];
                                $isAwaiting = $currentStageIndex !== false && $stageIndex > $currentStageIndex;
                            ?>
                            <div class="col-md-4">
                                <div class="border rounded p-2 h-100">
                                    <div class="fw-semibold">{{ $stage['label'] }}</div>
                                    @if($stage['approvedAt'])
                                        <div class="small fw-semibold text-success">Approved</div>
                                        <div class="small text-muted">{{ $stage['approver']?->name ?: 'Approver unavailable' }}</div>
                                        <div class="small text-muted">{{ $stage['approvedAt']->format('M j, Y g:i A') }}</div>
                                    @elseif($stage['notRequired'])
                                        <div class="small fw-semibold text-muted">Not required</div>
                                        <div class="small text-muted">Employee is Head of Department</div>
                                    @elseif($isPending)
                                        <div class="small fw-semibold text-warning-emphasis">Pending approval</div>
                                        <div class="small text-muted">This is the current approval stage</div>
                                    @elseif($isAwaiting)
                                        <div class="small fw-semibold text-muted">Awaiting previous approval</div>
                                        <div class="small text-muted">This stage has not started</div>
                                    @else
                                        <div class="small fw-semibold text-muted">Not completed</div>
                                        <div class="small text-muted">No approval was recorded</div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
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
