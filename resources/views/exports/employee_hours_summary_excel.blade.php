<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        table.employee-hours-summary {
            border-collapse: collapse;
            font-family: Arial, sans-serif;
            font-size: 11px;
        }
        .employee-hours-summary td,
        .employee-hours-summary th {
            border: 1px solid #cbd5e1;
            padding: 5px 6px;
            vertical-align: middle;
        }
        .report-title {
            border: 0 !important;
            font-size: 16px;
            font-weight: 700;
            text-align: center;
        }
        .report-period {
            border: 0 !important;
            color: #475569;
            font-style: italic;
            text-align: center;
        }
        .group-header,
        .column-header {
            background: #d8e4bc;
            color: #111827;
            font-weight: 700;
            text-align: center;
        }
        .selected-total-header,
        .selected-total-cell {
            background: #f8dfd0;
        }
        .right {
            text-align: right;
        }
    </style>
</head>
<body>
<table class="employee-hours-summary">
    <tr>
        <td colspan="{{ $totalColumns }}" class="report-title">{{ $title }}</td>
    </tr>
    <tr>
        <td colspan="{{ $totalColumns }}" class="report-period">{{ $period_label }}</td>
    </tr>
    <tr>
        <th class="group-header"></th>
        <th class="group-header"></th>
        <th class="group-header"></th>
        <th class="group-header"></th>
        <th class="group-header"></th>
        @foreach($periods as $period)
            <th colspan="3" class="group-header">{{ $period['label'] }}<br>{{ $period['dates'] }}</th>
        @endforeach
        @if($mode === 'weekly')
            <th colspan="3" class="selected-total-header">Selected Weeks Total</th>
        @endif
    </tr>
    <tr>
        <th class="column-header">Employee Number</th>
        <th class="column-header">Employee Type</th>
        <th class="column-header">Employee Name</th>
        <th class="column-header">Department</th>
        <th class="column-header">Job Title</th>
        @foreach($periods as $period)
            <th class="column-header">Regular Hours</th>
            <th class="column-header">Overtime Hours</th>
            <th class="column-header">Total Hours</th>
        @endforeach
        @if($mode === 'weekly')
            <th class="selected-total-header">Regular Hours</th>
            <th class="selected-total-header">Overtime Hours</th>
            <th class="selected-total-header">Total Hours</th>
        @endif
    </tr>
    @forelse($employees as $employee)
        <tr>
            <td>{{ $employee['employee_id'] }}</td>
            <td>{{ $employee['employee_type'] }}</td>
            <td>{{ $employee['employee_name'] }}</td>
            <td>{{ $employee['department_name'] }}</td>
            <td>{{ $employee['job_title'] }}</td>
            @foreach($periods as $period)
                @php($hours = $employee['periods'][$period['key']] ?? ['regular_hours' => 0, 'overtime_hours' => 0, 'total_hours' => 0])
                <td class="right">{{ number_format($hours['regular_hours'], 2, '.', '') }}</td>
                <td class="right">{{ number_format($hours['overtime_hours'], 2, '.', '') }}</td>
                <td class="right">{{ number_format($hours['total_hours'], 2, '.', '') }}</td>
            @endforeach
            @if($mode === 'weekly')
                <td class="selected-total-cell right">{{ number_format($employee['regular_hours'], 2, '.', '') }}</td>
                <td class="selected-total-cell right">{{ number_format($employee['overtime_hours'], 2, '.', '') }}</td>
                <td class="selected-total-cell right">{{ number_format($employee['total_hours'], 2, '.', '') }}</td>
            @endif
        </tr>
    @empty
        <tr>
            <td colspan="{{ $totalColumns }}">No matching timesheets found.</td>
        </tr>
    @endforelse
</table>
</body>
</html>
