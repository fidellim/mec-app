<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        table.employee-rates-export {
            border-collapse: collapse;
            font-family: Arial, sans-serif;
            font-size: 11px;
        }
        .employee-rates-export td,
        .employee-rates-export th {
            border: 1px solid #000;
            padding: 5px 6px;
            vertical-align: middle;
        }
        .table-header {
            background: #ebf1de;
            color: #111827;
            font-weight: 700;
            text-align: center;
        }
        .center {
            text-align: center;
        }
        .right {
            text-align: right;
        }
    </style>
</head>
<body>
<table class="employee-rates-export">
    <tr>
        <th class="table-header">Employee ID</th>
        <th class="table-header">Initials</th>
        <th class="table-header">Employee Name</th>
        <th class="table-header">Job Title</th>
        <th class="table-header">Rate/Manhour</th>
    </tr>
    @forelse($rows as $row)
        <tr>
            <td class="center">{{ $row['employee_id'] }}</td>
            <td class="center">{{ $row['initials'] }}</td>
            <td>{{ $row['employee_name'] }}</td>
            <td>{{ $row['job_title'] }}</td>
            <td class="right"></td>
        </tr>
    @empty
        <tr>
            <td colspan="5" class="center">No employees found for the selected monthly report.</td>
        </tr>
    @endforelse
</table>
</body>
</html>
