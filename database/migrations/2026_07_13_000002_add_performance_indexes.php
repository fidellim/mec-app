<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<string, array<string, array<int, string>>>
     */
    private const INDEXES = [
        'users' => [
            'users_role_active_dept_idx' => ['role', 'is_active', 'department_id'],
        ],
        'timesheets' => [
            'timesheets_period_status_dept_idx' => ['timesheet_period_id', 'status', 'department_id'],
        ],
        'timesheet_entries' => [
            'ts_entries_sheet_project_date_idx' => ['timesheet_id', 'project_id', 'work_date'],
        ],
        'leave_plans' => [
            'leave_plans_status_dates_idx' => ['status', 'start_date', 'end_date'],
            'leave_plans_user_code_status_date_idx' => ['user_id', 'attendance_code', 'status', 'start_date'],
        ],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            foreach ($indexes as $name => $columns) {
                if (! $this->tableHasColumns($table, $columns) || $this->hasEquivalentIndex($table, $columns)) {
                    continue;
                }

                Schema::table($table, function (Blueprint $blueprint) use ($columns, $name) {
                    $blueprint->index($columns, $name);
                });
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach (array_keys($indexes) as $name) {
                if (! $this->hasNamedIndex($table, $name)) {
                    continue;
                }

                if ($table === 'timesheet_entries' && $name === 'ts_entries_sheet_project_date_idx') {
                    $this->ensureTimesheetEntriesForeignKeySupportIndex();
                }

                Schema::table($table, function (Blueprint $blueprint) use ($name) {
                    $blueprint->dropIndex($name);
                });
            }
        }
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function tableHasColumns(string $table, array $columns): bool
    {
        return Schema::hasTable($table) && Schema::hasColumns($table, $columns);
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function hasEquivalentIndex(string $table, array $columns): bool
    {
        $expected = array_map('strtolower', $columns);

        foreach (Schema::getIndexes($table) as $index) {
            $actual = array_map('strtolower', $index['columns'] ?? []);

            if ($actual === $expected) {
                return true;
            }
        }

        return false;
    }

    private function hasNamedIndex(string $table, string $name): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (strcasecmp((string) ($index['name'] ?? ''), $name) === 0) {
                return true;
            }
        }

        return false;
    }

    private function ensureTimesheetEntriesForeignKeySupportIndex(): void
    {
        foreach (Schema::getIndexes('timesheet_entries') as $index) {
            $columns = array_map('strtolower', $index['columns'] ?? []);

            if (($columns[0] ?? null) === 'timesheet_id'
                && strcasecmp((string) ($index['name'] ?? ''), 'ts_entries_sheet_project_date_idx') !== 0) {
                return;
            }
        }

        Schema::table('timesheet_entries', function (Blueprint $blueprint) {
            $blueprint->index('timesheet_id', 'timesheet_entries_timesheet_id_fk_idx');
        });
    }
};
