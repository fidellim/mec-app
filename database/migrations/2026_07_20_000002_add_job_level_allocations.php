<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'job_level')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('job_level', 32)->nullable()->after('job_title');
            });
        }

        if (! $this->hasIndexColumns('users', ['job_level', 'is_active'])) {
            Schema::table('users', function (Blueprint $table) {
                $table->index(['job_level', 'is_active'], 'users_job_level_active_idx');
            });
        }

        if (! Schema::hasTable('project_department_job_level_allocations')) {
            Schema::create('project_department_job_level_allocations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('project_department_allocation_id');
                $table->string('job_level', 32);
                $table->decimal('allocated_hours', 12, 2)->nullable();
                $table->timestamps();
                $table->unique(['project_department_allocation_id', 'job_level'], 'project_dept_level_unique');
                $table->foreign('project_department_allocation_id', 'project_dept_level_allocation_fk')
                    ->references('id')->on('project_department_allocations')->cascadeOnDelete();
            });
        } else {
            // MySQL commits DDL statements independently. A previous run may have
            // created this table before failing on Laravel's default FK name.
            if (! $this->hasIndexColumns('project_department_job_level_allocations', ['project_department_allocation_id', 'job_level'], unique: true)) {
                Schema::table('project_department_job_level_allocations', function (Blueprint $table) {
                    $table->unique(['project_department_allocation_id', 'job_level'], 'project_dept_level_unique');
                });
            }

            if (! $this->hasForeignKeyColumn('project_department_job_level_allocations', 'project_department_allocation_id')) {
                Schema::table('project_department_job_level_allocations', function (Blueprint $table) {
                    $table->foreign('project_department_allocation_id', 'project_dept_level_allocation_fk')
                        ->references('id')->on('project_department_allocations')->cascadeOnDelete();
                });
            }
        }

        if (! Schema::hasColumn('timesheet_entries', 'job_level_snapshot')) {
            Schema::table('timesheet_entries', function (Blueprint $table) {
                $table->string('job_level_snapshot', 32)->nullable()->after('department_id');
            });
        }

        if (! Schema::hasColumn('timesheet_entries', 'allocation_bucket_snapshot')) {
            Schema::table('timesheet_entries', function (Blueprint $table) {
                $table->string('allocation_bucket_snapshot', 16)->nullable()->after('job_level_snapshot');
            });
        }

        if (! $this->hasIndexColumns('timesheet_entries', ['project_id', 'department_id', 'job_level_snapshot', 'allocation_bucket_snapshot'])) {
            Schema::table('timesheet_entries', function (Blueprint $table) {
                $table->index(['project_id', 'department_id', 'job_level_snapshot', 'allocation_bucket_snapshot'], 'entries_allocation_usage_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::table('timesheet_entries', function (Blueprint $table) {
            $table->dropIndex('entries_allocation_usage_idx');
            $table->dropColumn(['job_level_snapshot', 'allocation_bucket_snapshot']);
        });
        Schema::dropIfExists('project_department_job_level_allocations');
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['job_level', 'is_active']);
            $table->dropColumn('job_level');
        });
    }

    private function hasIndexColumns(string $table, array $columns, bool $unique = false): bool
    {
        return collect(Schema::getIndexes($table))->contains(function (array $index) use ($columns, $unique) {
            return $index['columns'] === $columns && (! $unique || $index['unique']);
        });
    }

    private function hasForeignKeyColumn(string $table, string $column): bool
    {
        return collect(Schema::getForeignKeys($table))->contains(function (array $foreignKey) use ($column) {
            return in_array($column, $foreignKey['columns'], true);
        });
    }
};
