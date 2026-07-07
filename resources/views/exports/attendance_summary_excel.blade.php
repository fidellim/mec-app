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
        <tr>
            <td colspan="{{ $totalColumns }}" class="attendance-header">
                {{ $group['attendance_code'] }} - {{ $group['attendance_label'] }}
                @if($group['project_code'] !== 'Non-project')
                    <br>Project/Job: {{ $group['project_code'] }}
                @endif
            </td>
        </tr>
        <tr>
            <th class="week-header"></th>
            <th class="week-header"></th>
            <th class="week-header"></th>
            <th class="week-header"></th>
            <th class="week-header"></th>
            <th class="week-header"></th>
            <th class="week-header"></th>
            @foreach($group['weeks'] as $week)
                <th colspan="{{ $periodColumnSpan }}" class="week-header">{{ $week['label'] }}<br>{{ $week['dates'] }}</th>
            @endforeach
            @if($showRangeTotals)
                <th colspan="3" class="period-total-header">Selected Period Total</th>
            @endif
        </tr>
        <tr>
            <th class="table-header">Employee ID</th>
            <th class="table-header">Initials</th>
            <th class="table-header">Employee</th>
            <th class="table-header">Department</th>
            <th class="table-header">Job Title</th>
            <th class="table-header">Project/Job</th>
            <th class="table-header">Status</th>
            @foreach($group['weeks'] as $week)
                <th class="period-total-header">Regular Hours</th>
                <th class="period-total-header">Overtime Hours</th>
                <th class="period-total-header">Total Hours</th>
                @if($showCosting)
                    <th class="period-total-header">Rate/Manhour</th>
                    <th class="period-total-header">Regular Cost</th>
                    <th class="period-total-header">OT Cost</th>
                    <th class="period-total-header">Total Cost</th>
                @endif
            @endforeach
            @if($showRangeTotals)
                <th class="table-header">Regular Hours</th>
                <th class="table-header">Overtime Hours</th>
                <th class="table-header">Total Hours</th>
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
                <td>{{ $group['project_code'] }}</td>
                <td class="center">{{ ucfirst($employee['status']) }}</td>
                @foreach($group['weeks'] as $weekIndex => $week)
                    @php
                        $firstPeriodColumn = 8 + ($weekIndex * $periodColumnSpan);
                        $regularColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($firstPeriodColumn);
                        $overtimeColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($firstPeriodColumn + 1);
                        $totalColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($firstPeriodColumn + 2);
                        $rateColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($firstPeriodColumn + 3);
                        $rateFormula = '=IFERROR(VLOOKUP($A'.$employeeRow.',\'Employee Rates\'!$A:$E,5,FALSE),"")';
                        $regularCostFormula = '=IF('.$rateColumn.$employeeRow.'="","",'.$regularColumn.$employeeRow.'*'.$rateColumn.$employeeRow.')';
                        $overtimeCostFormula = '=IF('.$rateColumn.$employeeRow.'="","",'.$overtimeColumn.$employeeRow.'*'.$rateColumn.$employeeRow.'*1.25)';
                        $totalCostFormula = '=IF('.$rateColumn.$employeeRow.'="","",'.$totalColumn.$employeeRow.'*'.$rateColumn.$employeeRow.')';
                    @endphp
                    @php
                        $hours = $employee['weeks'][$week['key']] ?? ['regular_hours' => 0, 'overtime_hours' => 0, 'total_hours' => 0];
                    @endphp
                    <td class="right">{{ number_format($hours['regular_hours'], 2) }}</td>
                    <td class="right">{{ number_format($hours['overtime_hours'], 2) }}</td>
                    <td class="right">{{ number_format($hours['total_hours'], 2) }}</td>
                    @if($showCosting)
                        <td class="right">{!! $rateFormula !!}</td>
                        <td class="right">{!! $regularCostFormula !!}</td>
                        <td class="right">{!! $overtimeCostFormula !!}</td>
                        <td class="right">{!! $totalCostFormula !!}</td>
                    @endif
                @endforeach
                @if($showRangeTotals)
                    <td class="period-total-cell right">{{ number_format($employee['regular_hours'], 2) }}</td>
                    <td class="period-total-cell right">{{ number_format($employee['overtime_hours'], 2) }}</td>
                    <td class="period-total-cell right">{{ number_format($employee['total_hours'], 2) }}</td>
                @endif
            </tr>
        @endforeach
        @php
            $attendanceTotalRow = $currentRow + 3 + $group['employees']->count();
            $employeeStartRow = $currentRow + 3;
            $employeeEndRow = $attendanceTotalRow - 1;
            $attendanceTotalRows[] = $attendanceTotalRow;
        @endphp
        <tr>
            <td colspan="7" class="summary-total">Attendance Code Total</td>
            @foreach($group['weeks'] as $weekIndex => $week)
                @php
                    $firstPeriodColumn = 8 + ($weekIndex * $periodColumnSpan);
                    $regularCostColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($firstPeriodColumn + 4);
                    $overtimeCostColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($firstPeriodColumn + 5);
                    $totalCostColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($firstPeriodColumn + 6);
                @endphp
                @php
                    $totals = $group['week_totals'][$week['key']] ?? ['regular_hours' => 0, 'overtime_hours' => 0, 'total_hours' => 0];
                @endphp
                <td class="summary-total right">{{ number_format($totals['regular_hours'], 2) }}</td>
                <td class="summary-total right">{{ number_format($totals['overtime_hours'], 2) }}</td>
                <td class="summary-total right">{{ number_format($totals['total_hours'], 2) }}</td>
                @if($showCosting)
                    <td class="summary-total right"></td>
                    <td class="summary-total right">=SUM({{ $regularCostColumn }}{{ $employeeStartRow }}:{{ $regularCostColumn }}{{ $employeeEndRow }})</td>
                    <td class="summary-total right">=SUM({{ $overtimeCostColumn }}{{ $employeeStartRow }}:{{ $overtimeCostColumn }}{{ $employeeEndRow }})</td>
                    <td class="summary-total right">=SUM({{ $totalCostColumn }}{{ $employeeStartRow }}:{{ $totalCostColumn }}{{ $employeeEndRow }})</td>
                @endif
            @endforeach
            @if($showRangeTotals)
                <td class="period-total-summary right">{{ number_format($group['regular_hours'], 2) }}</td>
                <td class="period-total-summary right">{{ number_format($group['overtime_hours'], 2) }}</td>
                <td class="period-total-summary right">{{ number_format($group['total_hours'], 2) }}</td>
            @endif
        </tr>
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
    <tr>
        <td colspan="7" class="summary-total">Grand Total</td>
        @forelse($weeks as $weekIndex => $week)
            @php
                $firstPeriodColumn = 8 + ($weekIndex * $periodColumnSpan);
                $regularCostColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($firstPeriodColumn + 4);
                $overtimeCostColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($firstPeriodColumn + 5);
                $totalCostColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($firstPeriodColumn + 6);
                $regularCostTotalRefs = collect($attendanceTotalRows)->map(fn ($row) => $regularCostColumn.$row)->implode(',');
                $overtimeCostTotalRefs = collect($attendanceTotalRows)->map(fn ($row) => $overtimeCostColumn.$row)->implode(',');
                $totalCostTotalRefs = collect($attendanceTotalRows)->map(fn ($row) => $totalCostColumn.$row)->implode(',');
            @endphp
            @php
                $totals = $grandTotalsByWeek[$week['key']] ?? ['regular_hours' => 0, 'overtime_hours' => 0, 'total_hours' => 0];
            @endphp
            <td class="summary-total right">{{ number_format($totals['regular_hours'], 2) }}</td>
            <td class="summary-total right">{{ number_format($totals['overtime_hours'], 2) }}</td>
            <td class="summary-total right">{{ number_format($totals['total_hours'], 2) }}</td>
            @if($showCosting)
                <td class="summary-total right"></td>
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
            <td class="summary-total right">{{ number_format($totalRegular, 2) }}</td>
            <td class="summary-total right">{{ number_format($totalOvertime, 2) }}</td>
            <td class="summary-total right">{{ number_format($totalHours, 2) }}</td>
        @endif
    </tr>
</table>
</body>
</html>
