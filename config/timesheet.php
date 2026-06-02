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
        'L170' => 'Paternity Leave',
        'L180' => 'Compassionate Leave',
        'L200' => 'Training Seminar',
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
        'L200',
    ],

    'manual_reminder_cooldown_hours' => env('MISSING_TIMESHEET_REMINDER_COOLDOWN_HOURS', 24),
];
