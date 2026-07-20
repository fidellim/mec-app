<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $exists = collect(Schema::getIndexes('timesheet_correction_requests'))
            ->contains(fn (array $index) => array_map('strtolower', $index['columns'] ?? []) === ['status', 'timesheet_id']);

        if (! $exists) {
            Schema::table('timesheet_correction_requests', function (Blueprint $table) {
                $table->index(['status', 'timesheet_id'], 'ts_corr_status_timesheet_idx');
            });
        }
    }

    public function down(): void
    {
        $exists = collect(Schema::getIndexes('timesheet_correction_requests'))
            ->contains(fn (array $index) => strcasecmp((string) ($index['name'] ?? ''), 'ts_corr_status_timesheet_idx') === 0);

        if ($exists) {
            Schema::table('timesheet_correction_requests', function (Blueprint $table) {
                $table->dropIndex('ts_corr_status_timesheet_idx');
            });
        }
    }
};
