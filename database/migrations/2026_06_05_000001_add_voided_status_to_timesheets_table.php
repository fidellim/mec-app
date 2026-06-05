<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addForeignKeySupportIndexes();

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE timesheets DROP INDEX timesheets_user_id_timesheet_period_id_unique');
            DB::statement("ALTER TABLE timesheets MODIFY status ENUM('draft', 'submitted', 'approved', 'rejected', 'voided') NOT NULL DEFAULT 'draft'");
        } else {
            Schema::table('timesheets', function (Blueprint $table) {
                $table->dropUnique(['user_id', 'timesheet_period_id']);
            });
        }

        Schema::table('timesheets', function (Blueprint $table) {
            $table->timestamp('voided_at')->nullable()->after('rejection_comment');
            $table->foreignId('voided_by')->nullable()->after('voided_at')->constrained('users')->nullOnDelete();
            $table->text('void_reason')->nullable()->after('voided_by');
        });
    }

    public function down(): void
    {
        Schema::table('timesheets', function (Blueprint $table) {
            $table->dropForeign(['voided_by']);
            $table->dropColumn(['voided_at', 'voided_by', 'void_reason']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE timesheets MODIFY status ENUM('draft', 'submitted', 'approved', 'rejected') NOT NULL DEFAULT 'draft'");
        }

        Schema::table('timesheets', function (Blueprint $table) {
            $table->unique(['user_id', 'timesheet_period_id']);
        });
    }

    private function addForeignKeySupportIndexes(): void
    {
        if (DB::getDriverName() === 'mysql') {
            $this->addMysqlIndexIfMissing('timesheets_user_id_foreign_support_index', ['user_id']);
            $this->addMysqlIndexIfMissing('timesheets_period_id_foreign_support_index', ['timesheet_period_id']);

            return;
        }

        Schema::table('timesheets', function (Blueprint $table) {
            $table->index('user_id', 'timesheets_user_id_foreign_support_index');
            $table->index('timesheet_period_id', 'timesheets_period_id_foreign_support_index');
        });
    }

    private function addMysqlIndexIfMissing(string $indexName, array $columns): void
    {
        $exists = DB::table('information_schema.statistics')
            ->whereRaw('table_schema = DATABASE()')
            ->where('table_name', 'timesheets')
            ->where('index_name', $indexName)
            ->exists();

        if ($exists) {
            return;
        }

        $columnSql = collect($columns)
            ->map(fn (string $column) => "`{$column}`")
            ->implode(', ');

        DB::statement("ALTER TABLE timesheets ADD INDEX {$indexName} ({$columnSql})");
    }
};
