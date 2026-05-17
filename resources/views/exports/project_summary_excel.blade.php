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
        .summary-header {
            background: #2e258b;
            color: #fff;
            font-weight: 700;
            text-align: center;
        }
        .summary-total {
            background: #f4f4f4;
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
        <td colspan="6" class="summary-title">Project Hours Summary</td>
    </tr>
    <tr>
        <td colspan="6" class="summary-spacer"></td>
    </tr>
    <tr>
        <th class="summary-header">Project Code</th>
        <th class="summary-header">Project Name</th>
        <th class="summary-header">Client</th>
        <th class="summary-header">Regular Hours</th>
        <th class="summary-header">Overtime Hours</th>
        <th class="summary-header">Total Hours</th>
    </tr>
    @forelse($rows as $row)
        <tr>
            <td class="center">{{ $row['project_code'] }}</td>
            <td>{{ $row['project_name'] }}</td>
            <td>{{ $row['client_name'] }}</td>
            <td class="right">{{ number_format($row['regular_hours'], 2) }}</td>
            <td class="right">{{ number_format($row['overtime_hours'], 2) }}</td>
            <td class="right">{{ number_format($row['total_hours'], 2) }}</td>
        </tr>
    @empty
        <tr>
            <td colspan="6" class="center">No project hours found for the selected filters.</td>
        </tr>
    @endforelse
    <tr>
        <td colspan="3" class="summary-total">Totals</td>
        <td class="summary-total right">{{ number_format($totalRegular, 2) }}</td>
        <td class="summary-total right">{{ number_format($totalOvertime, 2) }}</td>
        <td class="summary-total right">{{ number_format($totalHours, 2) }}</td>
    </tr>
</table>
</body>
</html>
