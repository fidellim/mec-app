<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PerformanceIndexMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const EXPECTED_INDEXES = [
        'users' => 'users_role_active_dept_idx',
        'timesheets' => 'timesheets_period_status_dept_idx',
        'timesheet_entries' => 'ts_entries_sheet_project_date_idx',
        'leave_plans' => 'leave_plans_status_dates_idx',
    ];

    public function test_performance_index_migration_is_rerunnable_and_reversible(): void
    {
        $migration = require database_path('migrations/2026_07_13_000002_add_performance_indexes.php');

        $migration->up();
        $migration->up();

        foreach (self::EXPECTED_INDEXES as $table => $index) {
            $this->assertSame(1, $this->namedIndexCount($table, $index));
        }
        $this->assertSame(1, $this->namedIndexCount('leave_plans', 'leave_plans_user_code_status_date_idx'));

        $migration->down();

        foreach (self::EXPECTED_INDEXES as $table => $index) {
            $this->assertSame(0, $this->namedIndexCount($table, $index));
        }
        $this->assertSame(0, $this->namedIndexCount('leave_plans', 'leave_plans_user_code_status_date_idx'));
        $this->assertTrue($this->hasIndexStartingWith('timesheet_entries', 'timesheet_id'));

        $migration->up();

        foreach (self::EXPECTED_INDEXES as $table => $index) {
            $this->assertSame(1, $this->namedIndexCount($table, $index));
        }
        $this->assertSame(1, $this->namedIndexCount('leave_plans', 'leave_plans_user_code_status_date_idx'));
    }

    private function namedIndexCount(string $table, string $name): int
    {
        return collect(Schema::getIndexes($table))
            ->filter(fn (array $index) => strcasecmp((string) ($index['name'] ?? ''), $name) === 0)
            ->count();
    }

    private function hasIndexStartingWith(string $table, string $column): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn (array $index) => strtolower((string) ($index['columns'][0] ?? '')) === strtolower($column));
    }
}
