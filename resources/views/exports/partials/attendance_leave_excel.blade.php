@php
    $leaveSpan = $showCosting ? 2 : 1;
    $fixedColumns = $showCosting ? 8 : 7;
    $periods = $group['weeks']->map(fn ($week) => $week + ['range_total' => false]);
    if ($showRangeTotals) {
        $periods = $periods->push(['label' => 'Selected Period Total', 'dates' => '', 'range_total' => true]);
    }
    $attendanceTotalRow = $currentRow + 3 + $group['employees']->count();
@endphp
<tr>
    @for($column = 0; $column < $fixedColumns; $column++)
        <th class="week-header"></th>
    @endfor
    @foreach($periods as $period)
        <th colspan="{{ $leaveSpan }}" class="{{ $period['range_total'] ? 'period-total-header' : 'week-header' }}">{{ $period['label'] }}@if($period['dates'])<br>{{ $period['dates'] }}@endif</th>
    @endforeach
</tr>
<tr>
    <th class="table-header">Employee ID</th>
    <th class="table-header">Initials</th>
    <th class="table-header">Employee</th>
    <th class="table-header">Department</th>
    <th class="table-header">Job Title</th>
    @if($showCosting)<th class="table-header">Rate/Manhour</th>@endif
    <th class="table-header">Project/Job</th>
    <th class="table-header">Status</th>
    @foreach($periods as $period)
        <th class="{{ $period['range_total'] ? 'table-header' : 'period-total-header' }}">Leave Hours</th>
        @if($showCosting)<th class="{{ $period['range_total'] ? 'table-header' : 'period-total-header' }}">Leave Cost</th>@endif
    @endforeach
</tr>
@foreach($group['employees'] as $employee)
    @php $employeeRow = $currentRow + 3 + $loop->index; @endphp
    <tr>
        <td class="center">{{ $employee['employee_id'] }}</td>
        <td class="center">{{ $employee['initials'] }}</td>
        <td>{{ $employee['employee_name'] }}</td>
        <td>{{ $employee['department_name'] }}</td>
        <td>{{ $employee['job_title'] }}</td>
        @if($showCosting)
            <td class="right">=IFERROR(VLOOKUP($A{{ $employeeRow }},'Employee Rates'!$A:$E,5,FALSE),"")</td>
        @endif
        <td>{{ $group['project_code'] }}</td>
        <td class="center">{{ ucfirst($employee['status']) }}</td>
        @foreach($periods as $periodIndex => $period)
            @php
                $hours = $period['range_total'] ? $employee['regular_hours'] : ($employee['weeks'][$period['key']]['regular_hours'] ?? 0);
                $hoursColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($fixedColumns + 1 + $periodIndex * $leaveSpan);
                $cellClass = $period['range_total'] ? 'period-total-cell right' : 'right';
            @endphp
            <td class="{{ $cellClass }}">{{ number_format($hours, 2) }}</td>
            @if($showCosting)
                <td class="{{ $cellClass }}">=IF(F{{ $employeeRow }}="","",{{ $hoursColumn }}{{ $employeeRow }}*F{{ $employeeRow }})</td>
            @endif
        @endforeach
    </tr>
@endforeach
<tr>
    <td colspan="{{ $fixedColumns }}" class="summary-total">Attendance Code Total</td>
    @foreach($periods as $periodIndex => $period)
        @php
            $hours = $period['range_total'] ? $group['regular_hours'] : ($group['week_totals'][$period['key']]['regular_hours'] ?? 0);
            $costColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($fixedColumns + 2 + $periodIndex * $leaveSpan);
            $cellClass = $period['range_total'] ? 'period-total-summary right' : 'summary-total right';
        @endphp
        <td class="{{ $cellClass }}">{{ number_format($hours, 2) }}</td>
        @if($showCosting)
            <td class="{{ $cellClass }}">=SUM({{ $costColumn }}{{ $currentRow + 3 }}:{{ $costColumn }}{{ $attendanceTotalRow - 1 }})</td>
        @endif
    @endforeach
</tr>
