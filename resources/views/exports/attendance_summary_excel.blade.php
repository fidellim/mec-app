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
        .summary-note {
            border: 0 !important;
            color: #374151;
            font-style: italic;
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
<table class="attendance-summary-export">
    <tr>
        <td colspan="14" class="summary-title">Attendance Code Summary</td>
    </tr>
    <tr>
        <td colspan="14" class="summary-note">Leave and other non-project hours are shown here so payroll/manhour totals can be reconciled with project-chargeable hours.</td>
    </tr>
    <tr>
        <th class="table-header">Week</th>
        <th class="table-header">Date Range</th>
        <th class="table-header">Employee ID</th>
        <th class="table-header">Initials</th>
        <th class="table-header">Employee</th>
        <th class="table-header">Department</th>
        <th class="table-header">Job Title</th>
        <th class="table-header">Attendance Code</th>
        <th class="table-header">Attendance Type</th>
        <th class="table-header">Project/Job</th>
        <th class="table-header">Regular Hours</th>
        <th class="table-header">Overtime Hours</th>
        <th class="table-header">Total Hours</th>
        <th class="table-header">Status</th>
    </tr>
    @forelse($rows as $row)
        <tr>
            <td class="center">Week {{ $row['week_number'] }}, {{ $row['year'] }}</td>
            <td class="center">{{ $row['week_start']->format('d-M-y') }} to {{ $row['week_end']->format('d-M-y') }}</td>
            <td class="center">{{ $row['employee_id'] }}</td>
            <td class="center">{{ $row['initials'] }}</td>
            <td>{{ $row['employee_name'] }}</td>
            <td>{{ $row['department_name'] }}</td>
            <td>{{ $row['job_title'] }}</td>
            <td class="center">{{ $row['attendance_code'] }}</td>
            <td>{{ $row['attendance_label'] }}</td>
            <td>{{ $row['project_code'] }}</td>
            <td class="right">{{ number_format($row['regular_hours'], 2) }}</td>
            <td class="right">{{ number_format($row['overtime_hours'], 2) }}</td>
            <td class="right">{{ number_format($row['total_hours'], 2) }}</td>
            <td class="center">{{ ucfirst($row['status']) }}</td>
        </tr>
    @empty
        <tr>
            <td colspan="14" class="center">No leave or non-project hours found for the selected filters.</td>
        </tr>
    @endforelse
    <tr>
        <td colspan="10" class="summary-total">Grand Total</td>
        <td class="summary-total right">{{ number_format($totalRegular, 2) }}</td>
        <td class="summary-total right">{{ number_format($totalOvertime, 2) }}</td>
        <td class="summary-total right">{{ number_format($totalHours, 2) }}</td>
        <td class="summary-total"></td>
    </tr>
</table>
</body>
</html>
