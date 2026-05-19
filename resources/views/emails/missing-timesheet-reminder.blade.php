<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Timesheet reminder</title>
</head>
<body style="margin:0;background:#f4f6f9;font-family:Arial,Helvetica,sans-serif;color:#172033;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f6f9;padding:28px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border:1px solid #e5e7eb;border-radius:14px;overflow:hidden;">
                    <tr>
                        <td style="background:#182230;padding:22px 28px;">
                            <div style="color:#ffffff;font-size:18px;font-weight:700;">{{ config('app.name', 'Timesheet Management System') }}</div>
                            <div style="color:#cbd5e1;font-size:13px;margin-top:4px;">Weekly timesheet reminder</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:30px 28px 16px;">
                            <div style="display:inline-block;background:#fff7ed;color:#c2410c;border-radius:999px;padding:6px 12px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.03em;">Action required</div>
                            <h1 style="font-size:24px;line-height:1.25;margin:16px 0 10px;color:#111827;">Please submit your weekly timesheet</h1>
                            <p style="font-size:15px;line-height:1.6;margin:0;color:#475569;">
                                Hello {{ $employee->name }}, this is a reminder from {{ $sourceLabel }} to submit your timesheet for the period below.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:10px 28px 8px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;background:#f8fafc;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;">
                                <tr>
                                    <td style="padding:14px 16px;border-bottom:1px solid #e5e7eb;color:#64748b;font-size:12px;font-weight:700;text-transform:uppercase;">Employee</td>
                                    <td style="padding:14px 16px;border-bottom:1px solid #e5e7eb;text-align:right;font-size:14px;font-weight:700;">{{ $employee->name }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 16px;border-bottom:1px solid #e5e7eb;color:#64748b;font-size:12px;font-weight:700;text-transform:uppercase;">Department</td>
                                    <td style="padding:14px 16px;border-bottom:1px solid #e5e7eb;text-align:right;font-size:14px;font-weight:700;">{{ $employee->department?->name ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 16px;color:#64748b;font-size:12px;font-weight:700;text-transform:uppercase;">Period</td>
                                    <td style="padding:14px 16px;text-align:right;font-size:14px;font-weight:700;">Week {{ $period->week_number }} ({{ $period->start_date->format('M d, Y') }} - {{ $period->end_date->format('M d, Y') }})</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:26px 28px 34px;">
                            <a href="{{ $actionUrl }}" style="display:inline-block;background:#0d6efd;color:#ffffff;text-decoration:none;border-radius:9px;padding:13px 20px;font-size:14px;font-weight:700;">Submit Timesheet</a>
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#f8fafc;border-top:1px solid #e5e7eb;padding:16px 28px;color:#64748b;font-size:12px;line-height:1.5;">
                            This is an automated message from {{ config('app.name', 'Timesheet Management System') }}. Please do not reply to this email.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
