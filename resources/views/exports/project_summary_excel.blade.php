<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        table.project-summary-export {
            border-collapse: collapse;
            font-family: Arial, sans-serif;
            font-size: 11px;
        }
        .project-summary-export td,
        .project-summary-export th {
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
        .summary-spacer {
            border: 0 !important;
            height: 14px;
        }
        .project-header {
            background: #eef6ff;
            font-weight: 700;
            white-space: normal;
            word-wrap: break-word;
        }
        .week-header {
            background: #dbeafe;
            color: #111827;
            font-weight: 700;
            text-align: center;
        }
        .table-header {
            background: #e5e7eb;
            color: #111827;
            font-weight: 700;
            text-align: center;
        }
        .summary-total {
            background: #f1f5f9;
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
<table class="project-summary-export">
    <tr>
        <td colspan="{{ $totalColumns }}" class="summary-title">Project Weekly Summary</td>
    </tr>
    <tr>
        <td colspan="{{ $totalColumns }}" class="summary-spacer"></td>
    </tr>
    @forelse($groups as $group)
        <tr>
            <td colspan="{{ $totalColumns }}" class="project-header">
                {{ $group['project_code'] }} - {{ $group['project_name'] }}
                @if($group['client_name'])
                    <br>Client: {{ $group['client_name'] }}
                @endif
            </td>
        </tr>
        <tr>
            <th class="week-header"></th>
            <th class="week-header"></th>
            <th class="week-header"></th>
            <th class="week-header"></th>
            @foreach($group['weeks'] as $week)
                <th colspan="3" class="week-header">{{ $week['label'] }}<br>{{ $week['dates'] }}</th>
            @endforeach
        </tr>
        <tr>
            <th class="table-header">Employee ID</th>
            <th class="table-header">Initials</th>
            <th class="table-header">Employee</th>
            <th class="table-header">Job Title</th>
            @foreach($group['weeks'] as $week)
                <th class="table-header">Regular Hours</th>
                <th class="table-header">Overtime Hours</th>
                <th class="table-header">Total Hours</th>
            @endforeach
        </tr>
        @foreach($group['employees'] as $employee)
            <tr>
                <td class="center">{{ $employee['employee_id'] }}</td>
                <td class="center">{{ $employee['initials'] }}</td>
                <td>{{ $employee['employee_name'] }}</td>
                <td>{{ $employee['job_title'] }}</td>
                @foreach($group['weeks'] as $week)
                    @php($hours = $employee['weeks'][$week['key']] ?? ['regular_hours' => 0, 'overtime_hours' => 0, 'total_hours' => 0])
                    <td class="right">{{ number_format($hours['regular_hours'], 2) }}</td>
                    <td class="right">{{ number_format($hours['overtime_hours'], 2) }}</td>
                    <td class="right">{{ number_format($hours['total_hours'], 2) }}</td>
                @endforeach
            </tr>
        @endforeach
        <tr>
            <td colspan="4" class="summary-total">Project Total</td>
            @foreach($group['weeks'] as $week)
                @php($totals = $group['week_totals'][$week['key']] ?? ['regular_hours' => 0, 'overtime_hours' => 0, 'total_hours' => 0])
                <td class="summary-total right">{{ number_format($totals['regular_hours'], 2) }}</td>
                <td class="summary-total right">{{ number_format($totals['overtime_hours'], 2) }}</td>
                <td class="summary-total right">{{ number_format($totals['total_hours'], 2) }}</td>
            @endforeach
        </tr>
        <tr>
            <td colspan="{{ $totalColumns }}" class="summary-spacer"></td>
        </tr>
    @empty
        <tr>
            <td colspan="{{ $totalColumns }}" class="center">No project hours found for the selected filters.</td>
        </tr>
    @endforelse
    <tr>
        <td colspan="4" class="summary-total">Grand Total</td>
        @forelse($weeks as $week)
            @php($totals = $grandTotalsByWeek[$week['key']] ?? ['regular_hours' => 0, 'overtime_hours' => 0, 'total_hours' => 0])
            <td class="summary-total right">{{ number_format($totals['regular_hours'], 2) }}</td>
            <td class="summary-total right">{{ number_format($totals['overtime_hours'], 2) }}</td>
            <td class="summary-total right">{{ number_format($totals['total_hours'], 2) }}</td>
        @empty
            <td class="summary-total right">{{ number_format($totalRegular, 2) }}</td>
            <td class="summary-total right">{{ number_format($totalOvertime, 2) }}</td>
            <td class="summary-total right">{{ number_format($totalHours, 2) }}</td>
        @endforelse
    </tr>
</table>
</body>
</html>
