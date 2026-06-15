@php
    $project = $summaryPreview['project'];
    $attendance = $summaryPreview['attendance'];
@endphp

<style>
    .summary-preview-tabs .nav-link {
        border-radius: .55rem;
    }
    .summary-preview-total {
        border: 1px solid var(--app-soft-border);
        border-radius: .65rem;
        padding: .9rem 1rem;
        background: color-mix(in srgb, var(--app-muted-bg) 58%, transparent);
    }
    .summary-preview-group {
        border-color: var(--app-soft-border);
        background: transparent;
    }
    .summary-preview-group + .summary-preview-group {
        margin-top: .75rem;
    }
    .summary-preview-group .accordion-button {
        background: color-mix(in srgb, var(--app-muted-bg) 58%, transparent);
        color: var(--bs-body-color);
        box-shadow: none;
        gap: 1rem;
    }
    .summary-preview-group .accordion-button:not(.collapsed) {
        background: color-mix(in srgb, var(--app-muted-bg) 78%, transparent);
    }
    .summary-preview-group .accordion-button::after {
        flex: 0 0 auto;
        margin-left: .75rem;
    }
    .summary-preview-group .accordion-body {
        background: var(--app-card-bg);
        padding: 1rem 1.1rem 1.15rem;
    }
    .summary-preview-title {
        min-width: 0;
        text-align: left;
    }
    .summary-preview-title .fw-semibold,
    .summary-preview-title .small {
        overflow-wrap: anywhere;
    }
    .summary-preview-group-total {
        display: grid;
        grid-template-columns: repeat(3, minmax(5.5rem, auto));
        gap: .5rem;
        text-align: right;
    }
    .summary-preview-group-total span {
        display: block;
        color: var(--bs-secondary-color);
        font-size: .72rem;
        font-weight: 700;
        text-transform: uppercase;
    }
    .summary-preview-group-total strong {
        display: block;
        font-size: .95rem;
    }
    .summary-preview-table {
        min-width: 64rem;
        border-collapse: separate;
        border-spacing: 0;
        table-layout: fixed;
        width: max(100%, 64rem);
    }
    .summary-preview-scroll {
        max-width: 100%;
        overflow-x: auto;
        border: 1px solid var(--app-soft-border);
        border-radius: .65rem;
        padding: .45rem;
        background: color-mix(in srgb, var(--app-muted-bg) 34%, transparent);
    }
    .summary-preview-table th,
    .summary-preview-table td {
        border-color: var(--app-soft-border);
        padding: .72rem .85rem;
        white-space: nowrap;
    }
    .summary-preview-table thead tr:first-child th {
        text-align: center;
        border-bottom: 0;
    }
    .summary-preview-table thead tr:nth-child(2) th {
        border-top: 0;
        text-align: center;
    }
    .summary-preview-table .week-group-start {
        border-left: 2px solid color-mix(in srgb, var(--bs-secondary-color) 28%, var(--app-border));
    }
    .summary-preview-table .week-group-end {
        border-right: 2px solid color-mix(in srgb, var(--bs-secondary-color) 28%, var(--app-border));
    }
    .summary-preview-table .week-group-heading {
        border-top: 1px solid var(--app-soft-border);
        border-left: 2px solid color-mix(in srgb, var(--bs-secondary-color) 28%, var(--app-border));
        border-right: 2px solid color-mix(in srgb, var(--bs-secondary-color) 28%, var(--app-border));
        background: color-mix(in srgb, var(--app-muted-bg) 82%, transparent);
        font-weight: 800;
    }
    .summary-preview-table .week-group-heading:last-child,
    .summary-preview-table .week-group-end:last-child {
        border-right-color: transparent;
    }
    .summary-preview-table .metric-heading {
        width: 5.8rem;
        min-width: 5.8rem;
        text-align: center;
    }
    .summary-preview-table .summary-preview-metric {
        width: 5.8rem;
        min-width: 5.8rem;
    }
    .summary-preview-table .summary-preview-descriptor {
        width: 11rem;
        min-width: 11rem;
        text-align: center;
        white-space: normal;
    }
    .summary-preview-table .summary-preview-status {
        width: 8rem;
        min-width: 8rem;
        text-align: center;
    }
    .summary-preview-table .summary-preview-metric,
    .summary-preview-table tfoot .summary-preview-metric {
        text-align: center !important;
    }
    .summary-preview-table .summary-preview-sticky {
        position: sticky;
        left: 0;
        z-index: 3;
        width: 15rem;
        min-width: 15rem;
        max-width: 15rem;
        background: var(--app-card-bg);
        box-shadow: .35rem 0 .75rem rgba(15, 23, 42, .04);
        white-space: normal;
    }
    .summary-preview-table thead .summary-preview-sticky,
    .summary-preview-table tfoot .summary-preview-sticky {
        z-index: 4;
        background: var(--app-muted-bg);
    }
    [data-bs-theme="dark"] .summary-preview-table .summary-preview-sticky {
        box-shadow: .35rem 0 .75rem rgba(0, 0, 0, .18);
    }
    @media (max-width: 767.98px) {
        .summary-preview-group-total {
            width: 100%;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            text-align: left;
        }
        .summary-preview-group .accordion-button {
            align-items: flex-start;
            flex-direction: column;
        }
    }
</style>

<div class="content-card overflow-hidden mb-3" id="summary-report-preview">
    <div class="content-card-header d-flex flex-column flex-lg-row justify-content-between gap-3">
        <div>
            <div class="fw-semibold">Summary Report Preview</div>
            <div class="text-muted small">
                Previewing {{ $summaryPreview['timesheet_count'] }} matching timesheet(s).
            </div>
        </div>
        <ul class="nav nav-pills gap-2 summary-preview-tabs" id="summaryPreviewTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="project-summary-tab" data-bs-toggle="tab" data-bs-target="#project-summary-pane" type="button" role="tab" aria-controls="project-summary-pane" aria-selected="true">
                    Project Summary
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="attendance-summary-tab" data-bs-toggle="tab" data-bs-target="#attendance-summary-pane" type="button" role="tab" aria-controls="attendance-summary-pane" aria-selected="false">
                    Attendance Summary
                </button>
            </li>
        </ul>
    </div>
    <div class="content-card-body">
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="summary-preview-total h-100">
                    <div class="stat-label">Regular Hours</div>
                    <div class="meta-value">{{ number_format($project['totalRegular'], 2) }}</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="summary-preview-total h-100">
                    <div class="stat-label">Overtime Hours</div>
                    <div class="meta-value">{{ number_format($project['totalOvertime'], 2) }}</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="summary-preview-total h-100">
                    <div class="stat-label">Total Hours</div>
                    <div class="meta-value">{{ number_format($project['totalHours'], 2) }}</div>
                </div>
            </div>
        </div>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="project-summary-pane" role="tabpanel" aria-labelledby="project-summary-tab" tabindex="0">
                <div class="accordion" id="projectSummaryAccordion">
                    @forelse($project['groups'] as $groupIndex => $group)
                        <div class="accordion-item summary-preview-group">
                            <h3 class="accordion-header" id="project-summary-heading-{{ $groupIndex }}">
                                <button class="accordion-button @if($groupIndex > 0) collapsed @endif" type="button" data-bs-toggle="collapse" data-bs-target="#project-summary-collapse-{{ $groupIndex }}" aria-expanded="{{ $groupIndex === 0 ? 'true' : 'false' }}" aria-controls="project-summary-collapse-{{ $groupIndex }}">
                                    <span class="summary-preview-title flex-grow-1">
                                        <span class="fw-semibold d-block">{{ $group['project_code'] }}</span>
                                        <span class="small text-muted d-block">{{ str_replace("\n", ' ', $group['project_name']) }}</span>
                                        @if($group['client_name'])
                                            <span class="small text-muted d-block">Client: {{ $group['client_name'] }}</span>
                                        @endif
                                    </span>
                                    <span class="summary-preview-group-total">
                                        <span><span>Regular</span><strong>{{ number_format($group['regular_hours'], 2) }}</strong></span>
                                        <span><span>Overtime</span><strong>{{ number_format($group['overtime_hours'], 2) }}</strong></span>
                                        <span><span>Total</span><strong>{{ number_format($group['total_hours'], 2) }}</strong></span>
                                    </span>
                                </button>
                            </h3>
                            <div id="project-summary-collapse-{{ $groupIndex }}" class="accordion-collapse collapse @if($groupIndex === 0) show @endif" aria-labelledby="project-summary-heading-{{ $groupIndex }}" data-bs-parent="#projectSummaryAccordion">
                                <div class="accordion-body">
                                    <div class="summary-preview-scroll">
                                        <table class="table table-sm table-hover summary-preview-table">
                                            <thead>
                                                <tr>
                                                    <th class="summary-preview-sticky" rowspan="2">Employee</th>
                                                    <th class="summary-preview-descriptor" rowspan="2">Job Title</th>
                                                    @foreach($group['weeks'] as $week)
                                                        <th class="week-group-heading" colspan="3">{{ $week['label'] }}</th>
                                                    @endforeach
                                                    @if($project['showRangeTotals'])
                                                        <th class="week-group-heading" colspan="3">Selected Total</th>
                                                    @endif
                                                </tr>
                                                <tr>
                                                    @foreach($group['weeks'] as $week)
                                                        <th class="metric-heading week-group-start">RT</th>
                                                        <th class="metric-heading">OT</th>
                                                        <th class="metric-heading week-group-end">Total</th>
                                                    @endforeach
                                                    @if($project['showRangeTotals'])
                                                        <th class="metric-heading week-group-start">RT</th>
                                                        <th class="metric-heading">OT</th>
                                                        <th class="metric-heading week-group-end">Total</th>
                                                    @endif
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($group['employees'] as $employee)
                                                    <tr>
                                                        <td class="summary-preview-sticky">
                                                            <div class="fw-semibold">{{ $employee['employee_name'] }}</div>
                                                            <div class="small text-muted">{{ $employee['employee_id'] ?: '-' }}</div>
                                                        </td>
                                                        <td class="summary-preview-descriptor">{{ $employee['job_title'] }}</td>
                                                        @foreach($group['weeks'] as $week)
                                                            @php($hours = $employee['weeks'][$week['key']] ?? ['regular_hours' => 0, 'overtime_hours' => 0, 'total_hours' => 0])
                                                            <td class="text-end summary-preview-metric week-group-start">{{ number_format($hours['regular_hours'], 2) }}</td>
                                                            <td class="text-end summary-preview-metric">{{ number_format($hours['overtime_hours'], 2) }}</td>
                                                            <td class="text-end summary-preview-metric fw-semibold week-group-end">{{ number_format($hours['total_hours'], 2) }}</td>
                                                        @endforeach
                                                        @if($project['showRangeTotals'])
                                                            <td class="text-end summary-preview-metric week-group-start">{{ number_format($employee['regular_hours'], 2) }}</td>
                                                            <td class="text-end summary-preview-metric">{{ number_format($employee['overtime_hours'], 2) }}</td>
                                                            <td class="text-end summary-preview-metric fw-semibold week-group-end">{{ number_format($employee['total_hours'], 2) }}</td>
                                                        @endif
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                            <tfoot>
                                                <tr>
                                                    <th class="summary-preview-sticky">Project Total</th>
                                                    <th></th>
                                                    @foreach($group['weeks'] as $week)
                                                        @php($totals = $group['week_totals'][$week['key']] ?? ['regular_hours' => 0, 'overtime_hours' => 0, 'total_hours' => 0])
                                                        <th class="text-end summary-preview-metric week-group-start">{{ number_format($totals['regular_hours'], 2) }}</th>
                                                        <th class="text-end summary-preview-metric">{{ number_format($totals['overtime_hours'], 2) }}</th>
                                                        <th class="text-end summary-preview-metric week-group-end">{{ number_format($totals['total_hours'], 2) }}</th>
                                                    @endforeach
                                                    @if($project['showRangeTotals'])
                                                        <th class="text-end summary-preview-metric week-group-start">{{ number_format($group['regular_hours'], 2) }}</th>
                                                        <th class="text-end summary-preview-metric">{{ number_format($group['overtime_hours'], 2) }}</th>
                                                        <th class="text-end summary-preview-metric week-group-end">{{ number_format($group['total_hours'], 2) }}</th>
                                                    @endif
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="empty-state">No project hours found for the selected filters.</div>
                    @endforelse
                </div>
            </div>

            <div class="tab-pane fade" id="attendance-summary-pane" role="tabpanel" aria-labelledby="attendance-summary-tab" tabindex="0">
                <div class="accordion" id="attendanceSummaryAccordion">
                    @forelse($attendance['groups'] as $groupIndex => $group)
                        <div class="accordion-item summary-preview-group">
                            <h3 class="accordion-header" id="attendance-summary-heading-{{ $groupIndex }}">
                                <button class="accordion-button @if($groupIndex > 0) collapsed @endif" type="button" data-bs-toggle="collapse" data-bs-target="#attendance-summary-collapse-{{ $groupIndex }}" aria-expanded="{{ $groupIndex === 0 ? 'true' : 'false' }}" aria-controls="attendance-summary-collapse-{{ $groupIndex }}">
                                    <span class="summary-preview-title flex-grow-1">
                                        <span class="fw-semibold d-block">{{ $group['attendance_code'] }} - {{ $group['attendance_label'] }}</span>
                                        <span class="small text-muted d-block">Project/Job: {{ $group['project_code'] }}</span>
                                    </span>
                                    <span class="summary-preview-group-total">
                                        <span><span>Regular</span><strong>{{ number_format($group['regular_hours'], 2) }}</strong></span>
                                        <span><span>Overtime</span><strong>{{ number_format($group['overtime_hours'], 2) }}</strong></span>
                                        <span><span>Total</span><strong>{{ number_format($group['total_hours'], 2) }}</strong></span>
                                    </span>
                                </button>
                            </h3>
                            <div id="attendance-summary-collapse-{{ $groupIndex }}" class="accordion-collapse collapse @if($groupIndex === 0) show @endif" aria-labelledby="attendance-summary-heading-{{ $groupIndex }}" data-bs-parent="#attendanceSummaryAccordion">
                                <div class="accordion-body">
                                    <div class="summary-preview-scroll">
                                        <table class="table table-sm table-hover summary-preview-table">
                                            <thead>
                                                <tr>
                                                    <th class="summary-preview-sticky" rowspan="2">Employee</th>
                                                    <th class="summary-preview-descriptor" rowspan="2">Department</th>
                                                    <th class="summary-preview-descriptor" rowspan="2">Job Title</th>
                                                    <th class="summary-preview-status" rowspan="2">Status</th>
                                                    @foreach($group['weeks'] as $week)
                                                        <th class="week-group-heading" colspan="3">{{ $week['label'] }}</th>
                                                    @endforeach
                                                    @if($attendance['showRangeTotals'])
                                                        <th class="week-group-heading" colspan="3">Selected Total</th>
                                                    @endif
                                                </tr>
                                                <tr>
                                                    @foreach($group['weeks'] as $week)
                                                        <th class="metric-heading week-group-start">RT</th>
                                                        <th class="metric-heading">OT</th>
                                                        <th class="metric-heading week-group-end">Total</th>
                                                    @endforeach
                                                    @if($attendance['showRangeTotals'])
                                                        <th class="metric-heading week-group-start">RT</th>
                                                        <th class="metric-heading">OT</th>
                                                        <th class="metric-heading week-group-end">Total</th>
                                                    @endif
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($group['employees'] as $employee)
                                                    <tr>
                                                        <td class="summary-preview-sticky">
                                                            <div class="fw-semibold">{{ $employee['employee_name'] }}</div>
                                                            <div class="small text-muted">{{ $employee['employee_id'] ?: '-' }}</div>
                                                        </td>
                                                        <td class="summary-preview-descriptor">{{ $employee['department_name'] }}</td>
                                                        <td class="summary-preview-descriptor">{{ $employee['job_title'] }}</td>
                                                        <td class="summary-preview-status">@include('partials.status', ['status' => $employee['status']])</td>
                                                        @foreach($group['weeks'] as $week)
                                                            @php($hours = $employee['weeks'][$week['key']] ?? ['regular_hours' => 0, 'overtime_hours' => 0, 'total_hours' => 0])
                                                            <td class="text-end summary-preview-metric week-group-start">{{ number_format($hours['regular_hours'], 2) }}</td>
                                                            <td class="text-end summary-preview-metric">{{ number_format($hours['overtime_hours'], 2) }}</td>
                                                            <td class="text-end summary-preview-metric fw-semibold week-group-end">{{ number_format($hours['total_hours'], 2) }}</td>
                                                        @endforeach
                                                        @if($attendance['showRangeTotals'])
                                                            <td class="text-end summary-preview-metric week-group-start">{{ number_format($employee['regular_hours'], 2) }}</td>
                                                            <td class="text-end summary-preview-metric">{{ number_format($employee['overtime_hours'], 2) }}</td>
                                                            <td class="text-end summary-preview-metric fw-semibold week-group-end">{{ number_format($employee['total_hours'], 2) }}</td>
                                                        @endif
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                            <tfoot>
                                                <tr>
                                                    <th class="summary-preview-sticky">Attendance Code Total</th>
                                                    <th colspan="3"></th>
                                                    @foreach($group['weeks'] as $week)
                                                        @php($totals = $group['week_totals'][$week['key']] ?? ['regular_hours' => 0, 'overtime_hours' => 0, 'total_hours' => 0])
                                                        <th class="text-end summary-preview-metric week-group-start">{{ number_format($totals['regular_hours'], 2) }}</th>
                                                        <th class="text-end summary-preview-metric">{{ number_format($totals['overtime_hours'], 2) }}</th>
                                                        <th class="text-end summary-preview-metric week-group-end">{{ number_format($totals['total_hours'], 2) }}</th>
                                                    @endforeach
                                                    @if($attendance['showRangeTotals'])
                                                        <th class="text-end summary-preview-metric week-group-start">{{ number_format($group['regular_hours'], 2) }}</th>
                                                        <th class="text-end summary-preview-metric">{{ number_format($group['overtime_hours'], 2) }}</th>
                                                        <th class="text-end summary-preview-metric week-group-end">{{ number_format($group['total_hours'], 2) }}</th>
                                                    @endif
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="empty-state">No leave or non-project hours found for the selected filters.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
