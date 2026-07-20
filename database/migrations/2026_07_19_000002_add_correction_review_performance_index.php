<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $exists = collect(Schema::getIndexes('timesheet_entries'))
            ->contains(fn (array $index) => array_map('strtolower', $index['columns'] ?? []) === ['project_id', 'work_date', 'timesheet_id']);

        if (! $exists) {
            Schema::table('timesheet_entries', function (Blueprint $table) {
                $table->index(['project_id', 'work_date', 'timesheet_id'], 'ts_entries_project_date_sheet_idx');
            });
        }
    }

    public function down(): void
    {
        $exists = collect(Schema::getIndexes('timesheet_entries'))
            ->contains(fn (array $index) => strcasecmp((string) ($index['name'] ?? ''), 'ts_entries_project_date_sheet_idx') === 0);

        if ($exists) {
            Schema::table('timesheet_entries', function (Blueprint $table) {
                $table->dropIndex('ts_entries_project_date_sheet_idx');
            });
        }
    }
};
