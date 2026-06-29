<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\AutomationSetting;
use App\Models\LeaveSetting;
use App\Models\Project;
use App\Models\TimesheetPeriod;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $operations = Department::firstOrCreate(
            ['name' => 'Operations'],
            ['code' => 'OPS'],
        );
        $engineering = Department::firstOrCreate(
            ['name' => 'Engineering'],
            ['code' => 'ENG'],
        );

        User::firstOrCreate(
            ['email' => 'superadmin@example.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password123'),
                'role' => 'super_admin',
                'is_active' => true,
            ],
        );

        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password123'),
                'role' => 'admin',
                'is_active' => true,
            ],
        );

        $opsHod = User::firstOrCreate(
            ['email' => 'ops.hod@example.com'],
            [
                'name' => 'Olivia HOD',
                'password' => Hash::make('password123'),
                'employee_code' => 'MEC-HR-2024-017',
                'initials' => 'OH',
                'department_id' => $operations->id,
                'role' => 'hod',
                'is_active' => true,
            ],
        );

        $engHod = User::firstOrCreate(
            ['email' => 'eng.hod@example.com'],
            [
                'name' => 'Ethan HOD',
                'password' => Hash::make('password123'),
                'employee_code' => 'MCE-HR-2024-018',
                'initials' => 'EH',
                'department_id' => $engineering->id,
                'role' => 'hod',
                'is_active' => true,
            ],
        );

        $operations->update(['hod_id' => $opsHod->id]);
        $engineering->update(['hod_id' => $engHod->id]);
        $operations->hods()->syncWithoutDetaching([$opsHod->id]);
        $engineering->hods()->syncWithoutDetaching([$engHod->id]);

        foreach ([
            ['Aisha Khan', 'aisha@example.com', 'MEC-HR-2025-086', 'AK', $operations->id],
            ['Ben Carter', 'ben@example.com', 'MCE-HR-2025-087', 'BC', $operations->id],
            ['Carla Mendes', 'carla@example.com', 'MEC-HR-2025-088', 'CM', $engineering->id],
            ['Daniel Lim', 'daniel@example.com', 'MCE-HR-2025-089', 'DL', $engineering->id],
            ['Fatima Noor', 'fatima@example.com', 'MEC-HR-2025-090', 'FN', $engineering->id],
        ] as [$name, $email, $code, $initials, $departmentId]) {
            User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make('password123'),
                    'employee_code' => $code,
                    'initials' => $initials,
                    'department_id' => $departmentId,
                    'role' => 'employee',
                    'is_active' => true,
                ],
            );
        }

        $this->call(ProjectSeeder::class);

        foreach ([
            AutomationSetting::TIMESHEET_PERIOD_AUTO_CREATION => [
                'name' => 'Weekly Period Auto Creation',
                'description' => 'Automatically creates the current Monday-to-Sunday weekly period if it does not exist yet.',
            ],
            AutomationSetting::TIMESHEET_MISSING_REMINDERS => [
                'name' => 'Missing Timesheet Reminders',
                'description' => 'Automatically emails employees who have not submitted or approved their timesheet for the latest past open weekly period.',
            ],
        ] as $key => $attributes) {
            AutomationSetting::updateOrCreate(['key' => $key], $attributes);
        }

        LeaveSetting::updateOrCreate(
            ['key' => LeaveSetting::ANNUAL_LEAVE_DEFAULT_DAYS],
            [
                'name' => 'Annual Leave Default Days',
                'description' => 'Default yearly L100 annual leave allowance. Unused days expire on December 31 and do not carry over.',
                'decimal_value' => 30,
            ],
        );

        $start = Carbon::now()->startOfWeek();
        TimesheetPeriod::firstOrCreate(
            [
                'week_number' => (int) $start->isoWeek(),
                'year' => (int) $start->isoWeekYear(),
            ],
            [
                'start_date' => $start->toDateString(),
                'end_date' => $start->copy()->endOfWeek()->toDateString(),
                'status' => 'open',
            ],
        );
    }
}
