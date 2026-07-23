<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $userJobLevelIndex = $this->indexName('users', ['job_level', 'is_active']);

        if ($userJobLevelIndex) {
            Schema::table('users', function (Blueprint $table) use ($userJobLevelIndex) {
                $table->dropIndex($userJobLevelIndex);
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('job_level');
        });

        $timesheetUsageIndex = $this->indexName('timesheet_entries', [
            'project_id', 'department_id', 'job_level_snapshot', 'allocation_bucket_snapshot',
        ]);
        Schema::table('timesheet_entries', function (Blueprint $table) {
            $table->renameColumn('job_level_snapshot', 'manpower_category_snapshot');
        });
        $this->renameIndex(
            'timesheet_entries',
            $timesheetUsageIndex,
            'entries_manpower_usage_idx',
            ['project_id', 'department_id', 'manpower_category_snapshot', 'allocation_bucket_snapshot'],
        );

        $allocationUniqueIndex = $this->indexName(
            'project_department_job_level_allocations',
            ['project_department_allocation_id', 'job_level'],
            unique: true,
        );
        Schema::table('project_department_job_level_allocations', function (Blueprint $table) {
            $table->renameColumn('job_level', 'manpower_category');
        });

        Schema::rename('project_department_job_level_allocations', 'project_department_manpower_category_allocations');
        $this->renameIndex(
            'project_department_manpower_category_allocations',
            $allocationUniqueIndex,
            'project_dept_category_unique',
            ['project_department_allocation_id', 'manpower_category'],
            unique: true,
        );
    }

    public function down(): void
    {
        Schema::rename('project_department_manpower_category_allocations', 'project_department_job_level_allocations');

        Schema::table('project_department_job_level_allocations', function (Blueprint $table) {
            $table->renameColumn('manpower_category', 'job_level');
        });
        $this->renameIndex(
            'project_department_job_level_allocations',
            $this->indexName('project_department_job_level_allocations', ['project_department_allocation_id', 'job_level'], unique: true),
            'project_dept_level_unique',
            ['project_department_allocation_id', 'job_level'],
            unique: true,
        );

        Schema::table('timesheet_entries', function (Blueprint $table) {
            $table->renameColumn('manpower_category_snapshot', 'job_level_snapshot');
        });
        $this->renameIndex(
            'timesheet_entries',
            $this->indexName('timesheet_entries', ['project_id', 'department_id', 'job_level_snapshot', 'allocation_bucket_snapshot']),
            'entries_allocation_usage_idx',
            ['project_id', 'department_id', 'job_level_snapshot', 'allocation_bucket_snapshot'],
        );

        Schema::table('users', function (Blueprint $table) {
            $table->string('job_level', 32)->nullable()->after('job_title');
            $table->index(['job_level', 'is_active'], 'users_job_level_active_idx');
        });
    }

    private function indexName(string $table, array $columns, bool $unique = false): ?string
    {
        $index = collect(Schema::getIndexes($table))->first(function (array $index) use ($columns, $unique) {
            return $index['columns'] === $columns && (! $unique || $index['unique']);
        });

        return $index['name'] ?? null;
    }

    private function renameIndex(string $table, ?string $currentName, string $newName, array $columns, bool $unique = false): void
    {
        if (! $currentName || $currentName === $newName) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            $quote = fn (string $value) => '`'.str_replace('`', '``', $value).'`';
            DB::statement('ALTER TABLE '.$quote($table).' RENAME INDEX '.$quote($currentName).' TO '.$quote($newName));

            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($currentName, $newName, $columns, $unique) {
            $unique ? $blueprint->dropUnique($currentName) : $blueprint->dropIndex($currentName);
            $unique ? $blueprint->unique($columns, $newName) : $blueprint->index($columns, $newName);
        });
    }
};
