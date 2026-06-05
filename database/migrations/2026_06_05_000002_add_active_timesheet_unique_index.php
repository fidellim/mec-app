<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if (! $this->columnExists('active_timesheet_period_id')) {
            DB::statement("
                ALTER TABLE timesheets
                ADD active_timesheet_period_id BIGINT UNSIGNED
                GENERATED ALWAYS AS (
                    CASE
                        WHEN status = 'voided' THEN NULL
                        ELSE timesheet_period_id
                    END
                ) STORED
            ");
        }

        if (! $this->indexExists('timesheets_user_active_period_unique')) {
            DB::statement('ALTER TABLE timesheets ADD UNIQUE INDEX timesheets_user_active_period_unique (user_id, active_timesheet_period_id)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if ($this->indexExists('timesheets_user_active_period_unique')) {
            DB::statement('ALTER TABLE timesheets DROP INDEX timesheets_user_active_period_unique');
        }

        if ($this->columnExists('active_timesheet_period_id')) {
            DB::statement('ALTER TABLE timesheets DROP COLUMN active_timesheet_period_id');
        }
    }

    private function columnExists(string $columnName): bool
    {
        return DB::table('information_schema.columns')
            ->whereRaw('table_schema = DATABASE()')
            ->where('table_name', 'timesheets')
            ->where('column_name', $columnName)
            ->exists();
    }

    private function indexExists(string $indexName): bool
    {
        return DB::table('information_schema.statistics')
            ->whereRaw('table_schema = DATABASE()')
            ->where('table_name', 'timesheets')
            ->where('index_name', $indexName)
            ->exists();
    }
};
