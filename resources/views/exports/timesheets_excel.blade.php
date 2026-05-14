<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        table.timesheet-export {
            border-collapse: collapse;
            font-family: Arial, sans-serif;
            font-size: 11px;
            margin-bottom: 36px;
        }
        .timesheet-export td,
        .timesheet-export th {
            border: 1px solid #000;
            padding: 3px 4px;
            vertical-align: middle;
        }
        .title {
            font-size: 16px;
            font-weight: 700;
            text-align: center;
            border: 0 !important;
            padding: 12px 0;
        }
        .meta-no-border {
            border: 0 !important;
            font-weight: 700;
        }
        .meta-band {
            background: #d9eafc;
            border: 0 !important;
            font-weight: 700;
        }
        .meta-input {
            border: 2px solid #000 !important;
            background: #fff;
            font-weight: 400;
        }
        .note {
            color: #ff0000;
            font-style: italic;
            border: 0 !important;
            background: #d9eafc;
        }
        .blue-band {
            background: #2e258b;
            color: #fff;
            font-weight: 700;
            text-align: center;
        }
        .header {
            font-weight: 700;
            text-align: center;
            background: #f8f8f8;
        }
        .subheader {
            font-weight: 700;
            text-align: center;
            background: #eeeeee;
        }
        .weekend {
            background: #c9c9c9;
        }
        .total-col {
            background: #e5e1ea;
        }
        .center {
            text-align: center;
        }
        .right {
            text-align: right;
        }
        .remarks {
            min-width: 230px;
        }
        .spacer td {
            border: 0 !important;
            height: 18px;
        }
    </style>
</head>
<body>
@forelse($worksheets as $worksheet)
    @php($timesheet = $worksheet['timesheet'])
    <table class="timesheet-export">
        <colgroup>
            <col style="width: 72px;">
            <col style="width: 82px;">
            @foreach($worksheet['weekday_dates'] as $date)
                <col style="width: 34px;">
                <col style="width: 34px;">
            @endforeach
            <col style="width: 54px;">
            <col style="width: 54px;">
            <col style="width: 58px;">
            <col style="width: 58px;">
            <col style="width: 58px;">
            <col style="width: 58px;">
            <col style="width: 58px;">
            <col style="width: 250px;">
        </colgroup>
        <tr>
            <td colspan="21" class="title">Employee Weekly Timesheet</td>
        </tr>
        <tr>
            <td class="meta-no-border">Start Date</td>
            <td colspan="3" class="meta-no-border">{{ $timesheet->period->start_date->format('d-M-y') }}</td>
            <td colspan="12" class="meta-no-border"></td>
            <td colspan="2" class="meta-no-border center">Week #</td>
            <td colspan="3" class="meta-no-border right">{{ $timesheet->period->week_number }}</td>
        </tr>
        <tr>
            <td class="meta-band">Name:</td>
            <td colspan="4" class="meta-input">{{ $timesheet->user->name }}</td>
            <td colspan="3" class="meta-band"></td>
            <td colspan="2" class="meta-band">Emp. # :</td>
            <td colspan="3" class="meta-input">{{ $timesheet->user->employee_code }}</td>
            <td colspan="8" class="note">Note: OT is subject to prior Approval</td>
        </tr>
        <tr>
            <td class="meta-band">Initials:</td>
            <td colspan="2" class="meta-input center">{{ $worksheet['initials'] }}</td>
            <td colspan="5" class="meta-band"></td>
            <td colspan="2" class="meta-band">Department :</td>
            <td colspan="4" class="meta-input">{{ $timesheet->department->name }}</td>
            <td colspan="7" class="meta-band"></td>
        </tr>
        <tr>
            <td class="meta-band">Employment Type :</td>
            <td colspan="2" class="meta-input center">Full Time</td>
            <td colspan="18" class="meta-band"></td>
        </tr>
        <tr class="spacer"><td colspan="21"></td></tr>
        <tr>
            <th rowspan="3" class="header">Project<br>No.</th>
            <th rowspan="3" class="header">Code</th>
            <th colspan="10" class="blue-band">Hours Worked</th>
            <th rowspan="2" class="header weekend">Sat</th>
            <th rowspan="2" class="header weekend">Sun</th>
            <th rowspan="3" class="header">Holiday</th>
            <th rowspan="3" class="header">Regular<br>Hours</th>
            <th rowspan="3" class="header total-col">Overtime</th>
            <th rowspan="3" class="header">Leave<br>Hours</th>
            <th rowspan="3" class="header">Absent<br>Hours</th>
            <th rowspan="3" class="header">Total<br>Hours</th>
            <th rowspan="3" class="header remarks">Remarks</th>
        </tr>
        <tr>
            @foreach($worksheet['weekday_dates'] as $date)
                <th colspan="2" class="subheader">{{ $date->format('d') }}</th>
            @endforeach
        </tr>
        <tr>
            @foreach($worksheet['weekday_dates'] as $date)
                <th class="subheader">{{ $date->format('D') }}</th>
                <th class="subheader"></th>
            @endforeach
            <th class="subheader weekend">{{ $worksheet['saturday']?->format('d') }}</th>
            <th class="subheader weekend">{{ $worksheet['sunday']?->format('d') }}</th>
        </tr>
        <tr>
            <th class="subheader"></th>
            <th class="subheader"></th>
            @foreach($worksheet['weekday_dates'] as $date)
                <th class="subheader">RT</th>
                <th class="subheader">OT</th>
            @endforeach
            <th class="subheader weekend"></th>
            <th class="subheader weekend"></th>
            <th class="subheader"></th>
            <th class="subheader"></th>
            <th class="subheader total-col"></th>
            <th class="subheader"></th>
            <th class="subheader"></th>
            <th class="subheader"></th>
            <th class="subheader"></th>
        </tr>
        @foreach($worksheet['rows'] as $row)
            <tr>
                <td class="center">{{ $row['project_code'] }}</td>
                <td class="center">{{ $row['attendance_code'] }}</td>
                @foreach($worksheet['weekday_dates'] as $date)
                    @php($dayValues = $row['weekdays'][$date->toDateString()] ?? ['regular' => 0, 'overtime' => 0])
                    <td class="right">{{ number_format($dayValues['regular'], 1) }}</td>
                    <td class="right total-col">{{ number_format($dayValues['overtime'], 1) }}</td>
                @endforeach
                <td class="right weekend">{{ $row['saturday'] ? number_format($row['saturday'], 1) : '-' }}</td>
                <td class="right weekend">{{ $row['sunday'] ? number_format($row['sunday'], 1) : '-' }}</td>
                <td class="right">{{ $row['holiday'] ? number_format($row['holiday'], 1) : '-' }}</td>
                <td class="right">{{ $row['regular'] ? number_format($row['regular'], 1) : '-' }}</td>
                <td class="right total-col">{{ $row['overtime'] ? number_format($row['overtime'], 1) : '-' }}</td>
                <td class="right">{{ $row['leave'] ? number_format($row['leave'], 1) : '-' }}</td>
                <td class="right" style="color: #ff0000;">{{ $row['absent'] ? number_format($row['absent'], 1) : '-' }}</td>
                <td class="right">{{ $row['total'] ? number_format($row['total'], 1) : '-' }}</td>
                <td>{{ $row['remarks'] }}</td>
            </tr>
        @endforeach
        <tr>
            <td colspan="14" class="right header">Totals</td>
            <td class="right header">{{ number_format($worksheet['rows']->sum('holiday'), 1) }}</td>
            <td class="right header">{{ number_format($timesheet->total_regular_hours, 1) }}</td>
            <td class="right header total-col">{{ number_format($timesheet->total_overtime_hours, 1) }}</td>
            <td class="right header">{{ number_format($worksheet['rows']->sum('leave'), 1) }}</td>
            <td class="right header">{{ number_format($worksheet['rows']->sum('absent'), 1) }}</td>
            <td class="right header">{{ number_format($timesheet->total_hours, 1) }}</td>
            <td class="header"></td>
        </tr>
    </table>
@empty
    <table class="timesheet-export">
        <tr><td>No timesheets found for the selected filters.</td></tr>
    </table>
@endforelse
</body>
</html>
