<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        table.attendance-summary-export {
            border-collapse: collapse;
            font-family: Arial, sans-serif;
            font-size: 11px;
        }
        .attendance-summary-export td,
        .attendance-summary-export th {
            border: 1px solid #000;
            padding: 5px 6px;
            vertical-align: middle;
        }
        .summary-title {
            border: 0 !important;
            font-size: 16px;
            font-weight: 700;
            text-align: center;
        }
        .attendance-summary-export td.summary-spacer {
            border: none !important;
            height: 14px;
        }
        .attendance-header {
            background: #4f6228;
            color: #fff;
            font-weight: 700;
            white-space: normal;
            word-wrap: break-word;
        }
        .week-header {
            background: #d8e4bc;
            color: #111827;
            font-weight: 700;
            text-align: center;
        }
        .table-header {
            background: #ebf1de;
            color: #111827;
            font-weight: 700;
            text-align: center;
        }
        .summary-total {
            background: #f1f5f9;
            font-weight: 700;
        }
        .period-total-header {
            background: #f8cbad;
            color: #111827;
            font-weight: 700;
            text-align: center;
        }
        .period-total-cell {
            background: #fce4d6;
        }
        .period-total-summary {
            background: #f8dfd0;
            font-weight: 700;
        }
        .right {
            text-align: right;
        }
        .center {
            text-align: center;
        }
    </style>
</head>
<body>
@php
    $currentRow = 3;
    $attendanceTotalRows = [];
@endphp
<table class="attendance-summary-export">
    <tr>
        <td colspan="{{ $totalColumns }}" class="summary-title">{{ $reportTitle }}</td>
    </tr>
    <tr>
        <td colspan="{{ $totalColumns }}" class="summary-spacer"></td>
    </tr>
    @forelse($groups as $group)
        @php
            $groupColumnSpan = $group['compact_leave'] ? ($showCosting ? 2 : 1) : $periodColumnSpan;
            $attendanceTotalRows[] = ['row' => $currentRow + 3 + $group['employees']->count(), 'compact_leave' => $group['compact_leave']];
            $groupTotalColumns = ($showCosting ? 8 : 7) + ($group['weeks']->count() + ($showRangeTotals ? 1 : 0)) * $groupColumnSpan;
        @endphp
        <tr>
            <td colspan="{{ $groupTotalColumns }}" class="attendance-header">
                {{ $group['attendance_code'] }} - {{ $group['attendance_label'] }}
                @if($group['project_code'] !== 'Non-project')
                    <br>Project/Job: {{ $group['project_code'] }}
                @endif
            </td>
        </tr>
        @if($group['compact_leave'])
            @include('exports.partials.attendance_leave_excel')
        @else
        <tr>
            <th class="week-header"></th>
            <th class="week-header"></th>
            <th class="week-header"></th>
            <th class="week-header"></th>
            <th class="week-header"></th>
            <th class="week-header"></th>
            <th class="week-header"></th>
            @if($showCosting)
                <th class="week-header"></th>
            @endif
            @foreach($group['weeks'] as $week)
                <th colspan="{{ $periodColumnSpan }}" class="week-header">{{ $week['label'] }}<br>{{ $week['dates'] }}</th>
            @endforeach
            @if($showRangeTotals)
                <th colspan="{{ $showCosting ? 6 : 3 }}" class="period-total-header">Selected Period Total</th>
            @endif
        </tr>
        <tr>
            <th class="table-header">Employee ID</th>
            <th class="table-header">Initials</th>
            <th class="table-header">Employee</th>
            <th class="table-header">Department</th>
            <th class="table-header">Job Title</th>
            @if($showCosting)
                <th class="table-header">Rate/Manhour</th>
            @endif
            <th class="table-header">Project/Job</th>
            <th class="table-header">Status</th>
            @foreach($group['weeks'] as $week)
                <th class="period-total-header">Regular Hours</th>
                <th class="period-total-header">Overtime Hours</th>
                <th class="period-total-header">Total Hours</th>
                @if($showCosting)
                    <th class="period-total-header">Regular Cost</th>
                    <th class="period-total-header">OT Cost</th>
                    <th class="period-total-header">Total Cost</th>
                @endif
            @endforeach
            @if($showRangeTotals)
                <th class="table-header">Regular Hours</th>
                <th class="table-header">Overtime Hours</th>
                <th class="table-header">Total Hours</th>
                @if($showCosting)
                    <th class="table-header">Regular Cost</th>
                    <th class="table-header">OT Cost</th>
                    <th class="table-header">Total Cost</th>
                @endif
            @endif
        </tr>
        @foreach($group['employees'] as $employee)
            @php
                $employeeRow = $currentRow + 3 + $loop->index;
            @endphp
            <tr>
                <td class="center">{{ $employee['employee_id'] }}</td>
                <td class="center">{{ $employee['initials'] }}</td>
                <td>{{ $employee['employee_name'] }}</td>
                <td>{{ $employee['department_name'] }}</td>
                <td>{{ $employee['job_title'] }}</td>
                @if($showCosting)
                    @php
                        $rateFormula = '=IFERROR(VLOOKUP($A'.$employeeRow.',\'Employee Rates\'!$A:$E,5,FALSE),"")';
                    @endphp
                    <td class="right">{!! $rateFormula !!}</td>
                @endif
                <td>{{ $group['project_code'] }}</td>
                <td class="center">{{ ucfirst($employee['status']) }}</td>
                @foreach($group['weeks'] as $weekIndex => $week)
                    @php
                        $firstPeriodColumn = ($showCosting ? 9 : 8) + ($weekIndex * $periodColumnSpan);
                        $regularColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($firstPeriodColumn);
                        $overtimeColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($firstPeriodColumn + 1);
                        $totalColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($firstPeriodColumn + 2);
                        $rateColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($showCosting ? 6 : $firstPeriodColumn + 3);
                        $regularCostFormula = '=IF('.$rateColumn.$employeeRow.'="","",'.$regularColumn.$employeeRow.'*'.$rateColumn.$employeeRow.')';
                        $overtimeCostFormula = '=IF('.$rateColumn.$employeeRow.'="","",'.$overtimeColumn.$employeeRow.'*'.$rateColumn.$employeeRow.'*1.25)';
                        $totalCostFormula = '=IF('.$rateColumn.$employeeRow.'="","",('.$regularColumn.$employeeRow.'+'.$overtimeColumn.$employeeRow.'*1.25)*'.$rateColumn.$employeeRow.')';
                    @endphp
                    @php
                        $hours = $employee['weeks'][$week['key']] ?? ['regular_hours' => 0, 'overtime_hours' => 0, 'total_hours' => 0];
                    @endphp
                    <td class="right">{{ number_format($hours['regular_hours'], 2) }}</td>
                    <td class="right">{{ number_format($hours['overtime_hours'], 2) }}</td>
                    <td class="right">{{ number_format($hours['total_hours'], 2) }}</td>
                    @if($showCosting)
                        <td class="right">{!! $regularCostFormula !!}</td>
                        <td class="right">{!! $overtimeCostFormula !!}</td>
                        <td class="right">{!! $totalCostFormula !!}</td>
                    @endif
                @endforeach
                @if($showRangeTotals)
                    @if($showCosting)
                        @php
                            $rateColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(6);
                            $selectedRegularCostFormula = '=IF('.$rateColumn.$employeeRow.'="","",'.$employee['regular_hours'].'*'.$rateColumn.$employeeRow.')';
                            $selectedOvertimeCostFormula = '=IF('.$rateColumn.$employeeRow.'="","",'.$employee['overtime_hours'].'*'.$rateColumn.$employeeRow.'*1.25)';
                            $selectedTotalCostFormula = '=IF('.$rateColumn.$employeeRow.'="","",('.$employee['regular_hours'].'+'.$employee['overtime_hours'].'*1.25)*'.$rateColumn.$employeeRow.')';
                        @endphp
                    @endif
                    <td class="period-total-cell right">{{ number_format($employee['regular_hours'], 2) }}</td>
                    <td class="period-total-cell right">{{ number_format($employee['overtime_hours'], 2) }}</td>
                    <td class="period-total-cell right">{{ number_format($employee['total_hours'], 2) }}</td>
                    @if($showCosting)
                        <td class="period-total-cell right">{!! $selectedRegularCostFormula !!}</td>
                        <td class="period-total-cell right">{!! $selectedOvertimeCostFormula !!}</td>
                        <td class="period-total-cell right">{!! $selectedTotalCostFormula !!}</td>
                    @endif
                @endif
            </tr>
        @endforeach
        @php
            $attendanceTotalRow = $currentRow + 3 + $group['employees']->count();
            $employeeStartRow = $currentRow + 3;
            $employeeEndRow = $attendanceTotalRow - 1;
        @endphp
        <tr>
            <td colspan="{{ $showCosting ? 8 : 7 }}" class="summary-total">Attendance Code Total</td>
            @foreach($group['weeks'] as $weekIndex => $week)
                @php
                    $firstPeriodColumn = ($showCosting ? 9 : 8) + ($weekIndex * $periodColumnSpan);
                    $regularCostColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($firstPeriodColumn + 3);
                    $overtimeCostColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($firstPeriodColumn + 4);
                    $totalCostColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($firstPeriodColumn + 5);
                @endphp
                @php
                    $totals = $group['week_totals'][$week['key']] ?? ['regular_hours' => 0, 'overtime_hours' => 0, 'total_hours' => 0];
                @endphp
                <td class="summary-total right">{{ number_format($totals['regular_hours'], 2) }}</td>
                <td class="summary-total right">{{ number_format($totals['overtime_hours'], 2) }}</td>
                <td class="summary-total right">{{ number_format($totals['total_hours'], 2) }}</td>
                @if($showCosting)
                    <td class="summary-total right">=SUM({{ $regularCostColumn }}{{ $employeeStartRow }}:{{ $regularCostColumn }}{{ $employeeEndRow }})</td>
                    <td class="summary-total right">=SUM({{ $overtimeCostColumn }}{{ $employeeStartRow }}:{{ $overtimeCostColumn }}{{ $employeeEndRow }})</td>
                    <td class="summary-total right">=SUM({{ $totalCostColumn }}{{ $employeeStartRow }}:{{ $totalCostColumn }}{{ $employeeEndRow }})</td>
                @endif
            @endforeach
            @if($showRangeTotals)
                @if($showCosting)
                    @php
                        $selectedRegularCostColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(9 + ($group['weeks']->count() * $periodColumnSpan) + 3);
                        $selectedOvertimeCostColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(9 + ($group['weeks']->count() * $periodColumnSpan) + 4);
                        $selectedTotalCostColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(9 + ($group['weeks']->count() * $periodColumnSpan) + 5);
                    @endphp
                @endif
                <td class="period-total-summary right">{{ number_format($group['regular_hours'], 2) }}</td>
                <td class="period-total-summary right">{{ number_format($group['overtime_hours'], 2) }}</td>
                <td class="period-total-summary right">{{ number_format($group['total_hours'], 2) }}</td>
                @if($showCosting)
                    <td class="period-total-summary right">=SUM({{ $selectedRegularCostColumn }}{{ $employeeStartRow }}:{{ $selectedRegularCostColumn }}{{ $employeeEndRow }})</td>
                    <td class="period-total-summary right">=SUM({{ $selectedOvertimeCostColumn }}{{ $employeeStartRow }}:{{ $selectedOvertimeCostColumn }}{{ $employeeEndRow }})</td>
                    <td class="period-total-summary right">=SUM({{ $selectedTotalCostColumn }}{{ $employeeStartRow }}:{{ $selectedTotalCostColumn }}{{ $employeeEndRow }})</td>
                @endif
            @endif
        </tr>
        @endif
        <tr>
            <td colspan="{{ $totalColumns }}" class="summary-spacer"></td>
        </tr>
        @php
            $currentRow += $group['employees']->count() + 5;
        @endphp
    @empty
        <tr>
            <td colspan="{{ $totalColumns }}" class="center">No leave or non-project hours found for the selected filters.</td>
        </tr>
    @endforelse
    @php
        // Each section has its own period width; collect costs from the matching subtotal cells.
        $costTotalRefs = function (int $periodIndex, string $metric) use ($attendanceTotalRows) {
            return collect($attendanceTotalRows)->map(function ($item) use ($periodIndex, $metric) {
                if ($item['compact_leave'] && $metric === 'overtime') {
                    return null;
                }
                $span = $item['compact_leave'] ? 2 : 6;
                $offset = $item['compact_leave'] ? 1 : ['regular' => 3, 'overtime' => 4, 'total' => 5][$metric];
                return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(9 + $periodIndex * $span + $offset).$item['row'];
            })->filter()->implode(',');
        };
    @endphp
    @if($groups->isNotEmpty())
        <tr>
            <th colspan="{{ $showCosting ? 8 : 7 }}" class="table-header">Overall Totals</th>
            @foreach($weeks as $week)
                <th class="table-header">{{ $week['label'] }}<br>Regular Hours</th>
                <th class="table-header">{{ $week['label'] }}<br>Overtime Hours</th>
                <th class="table-header">{{ $week['label'] }}<br>Total Hours</th>
                @if($showCosting)
                    <th class="table-header">{{ $week['label'] }}<br>Regular Cost</th>
                    <th class="table-header">{{ $week['label'] }}<br>OT Cost</th>
                    <th class="table-header">{{ $week['label'] }}<br>Total Cost</th>
                @endif
            @endforeach
            @if($showRangeTotals)
                <th class="table-header">Selected Period<br>Regular Hours</th>
                <th class="table-header">Selected Period<br>Overtime Hours</th>
                <th class="table-header">Selected Period<br>Total Hours</th>
                @if($showCosting)
                    <th class="table-header">Selected Period<br>Regular Cost</th>
                    <th class="table-header">Selected Period<br>OT Cost</th>
                    <th class="table-header">Selected Period<br>Total Cost</th>
                @endif
            @endif
        </tr>
    @endif
    <tr>
        <td colspan="{{ $showCosting ? 8 : 7 }}" class="summary-total">Grand Total</td>
        @forelse($weeks as $weekIndex => $week)
            @php
                $regularCostTotalRefs = $costTotalRefs($weekIndex, 'regular');
                $overtimeCostTotalRefs = $costTotalRefs($weekIndex, 'overtime');
                $totalCostTotalRefs = $costTotalRefs($weekIndex, 'total');
            @endphp
            @php
                $totals = $grandTotalsByWeek[$week['key']] ?? ['regular_hours' => 0, 'overtime_hours' => 0, 'total_hours' => 0];
            @endphp
            <td class="summary-total right">{{ number_format($totals['regular_hours'], 2) }}</td>
            <td class="summary-total right">{{ number_format($totals['overtime_hours'], 2) }}</td>
            <td class="summary-total right">{{ number_format($totals['total_hours'], 2) }}</td>
            @if($showCosting)
                <td class="summary-total right">={{ $regularCostTotalRefs ? 'SUM('.$regularCostTotalRefs.')' : '0' }}</td>
                <td class="summary-total right">={{ $overtimeCostTotalRefs ? 'SUM('.$overtimeCostTotalRefs.')' : '0' }}</td>
                <td class="summary-total right">={{ $totalCostTotalRefs ? 'SUM('.$totalCostTotalRefs.')' : '0' }}</td>
            @endif
        @empty
            <td class="period-total-summary right">{{ number_format($totalRegular, 2) }}</td>
            <td class="period-total-summary right">{{ number_format($totalOvertime, 2) }}</td>
            <td class="period-total-summary right">{{ number_format($totalHours, 2) }}</td>
        @endforelse
        @if($showRangeTotals)
            @if($showCosting)
                @php
                    $selectedRegularCostTotalRefs = $costTotalRefs($weeks->count(), 'regular');
                    $selectedOvertimeCostTotalRefs = $costTotalRefs($weeks->count(), 'overtime');
                    $selectedTotalCostTotalRefs = $costTotalRefs($weeks->count(), 'total');
                @endphp
            @endif
            <td class="summary-total right">{{ number_format($totalRegular, 2) }}</td>
            <td class="summary-total right">{{ number_format($totalOvertime, 2) }}</td>
            <td class="summary-total right">{{ number_format($totalHours, 2) }}</td>
            @if($showCosting)
                <td class="summary-total right">={{ $selectedRegularCostTotalRefs ? 'SUM('.$selectedRegularCostTotalRefs.')' : '0' }}</td>
                <td class="summary-total right">={{ $selectedOvertimeCostTotalRefs ? 'SUM('.$selectedOvertimeCostTotalRefs.')' : '0' }}</td>
                <td class="summary-total right">={{ $selectedTotalCostTotalRefs ? 'SUM('.$selectedTotalCostTotalRefs.')' : '0' }}</td>
            @endif
        @endif
    </tr>
</table>
</body>
</html>
