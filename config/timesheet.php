<?php

return [
    'attendance_codes' => [
        'O100' => 'Office',
        'L100' => 'Annual Leave',
        'L110' => 'Sick Leave',
        'L120' => 'Emergency Leave',
        'L130' => 'Unpaid Leave',
        'L140' => 'Paid Holiday Leave',
        'L150' => 'Work From Home',
        'L160' => 'Maternity Leave',
        'L170' => 'Parental Leave',
        'L180' => 'Bereavement Leave',
        'L190' => 'Service Incentive Leave',
        'L200' => 'Training Seminar',
        'L210' => 'Paternity Leave',
        'L220' => 'Leave for VAWC',
        'L230' => 'Special Leave for Women',
    ],

    'leave_attendance_codes' => [
        'L100',
        'L110',
        'L120',
        'L130',
        'L140',
        'L160',
        'L170',
        'L180',
        'L190',
        'L210',
        'L220',
        'L230',
    ],

    'project_optional_attendance_codes' => [
        'L100',
        'L110',
        'L120',
        'L130',
        'L140',
        'L160',
        'L170',
        'L180',
        'L190',
        'L200',
        'L210',
        'L220',
        'L230',
    ],

    'manual_reminder_cooldown_hours' => env('MISSING_TIMESHEET_REMINDER_COOLDOWN_HOURS', 24),
];
