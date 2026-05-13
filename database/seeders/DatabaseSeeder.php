<?php

namespace Database\Seeders;

use App\Models\Department;
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
        $operations = Department::create(['name' => 'Operations', 'code' => 'OPS']);
        $engineering = Department::create(['name' => 'Engineering', 'code' => 'ENG']);

        $super = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@example.com',
            'password' => Hash::make('password123'),
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $opsHod = User::create([
            'name' => 'Olivia HOD',
            'email' => 'ops.hod@example.com',
            'password' => Hash::make('password123'),
            'employee_code' => 'HOD001',
            'department_id' => $operations->id,
            'role' => 'hod',
            'is_active' => true,
        ]);

        $engHod = User::create([
            'name' => 'Ethan HOD',
            'email' => 'eng.hod@example.com',
            'password' => Hash::make('password123'),
            'employee_code' => 'HOD002',
            'department_id' => $engineering->id,
            'role' => 'hod',
            'is_active' => true,
        ]);

        $operations->update(['hod_id' => $opsHod->id]);
        $engineering->update(['hod_id' => $engHod->id]);

        foreach ([
            ['Aisha Khan', 'aisha@example.com', 'EMP001', $operations->id],
            ['Ben Carter', 'ben@example.com', 'EMP002', $operations->id],
            ['Carla Mendes', 'carla@example.com', 'EMP003', $engineering->id],
            ['Daniel Lim', 'daniel@example.com', 'EMP004', $engineering->id],
            ['Fatima Noor', 'fatima@example.com', 'EMP005', $engineering->id],
        ] as [$name, $email, $code, $departmentId]) {
            User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make('password123'),
                'employee_code' => $code,
                'department_id' => $departmentId,
                'role' => 'employee',
                'is_active' => true,
            ]);
        }

        foreach ([
            ['JOB-1001', 'Corporate Website Support', 'Acme Holdings'],
            ['JOB-1002', 'ERP Implementation', 'Northstar Trading'],
            ['JOB-1003', 'Facilities Upgrade', 'Internal'],
            ['JOB-1004', 'Client Reporting Automation', 'Blue Peak'],
            ['JOB-1005', 'General Administration', null],
        ] as [$code, $name, $client]) {
            Project::create(['project_code' => $code, 'project_name' => $name, 'client_name' => $client, 'is_active' => true]);
        }

        $start = Carbon::now()->startOfWeek();
        TimesheetPeriod::create([
            'week_number' => (int) $start->isoWeek(),
            'year' => (int) $start->isoWeekYear(),
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->endOfWeek()->toDateString(),
            'status' => 'open',
        ]);
    }
}
