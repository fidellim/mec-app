<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE leave_plans MODIFY status ENUM('draft','submitted','approved','rejected','cancellation_requested','cancelled','recalled','voided') NOT NULL DEFAULT 'draft'");
        }

        Schema::table('leave_plans', function (Blueprint $table) {
            $table->timestamp('recalled_at')->nullable()->after('cancellation_rejection_comment');
            $table->foreignId('recalled_by')->nullable()->after('recalled_at')->constrained('users')->nullOnDelete();
            $table->text('recall_reason')->nullable()->after('recalled_by');
            $table->timestamp('voided_at')->nullable()->after('recall_reason');
            $table->foreignId('voided_by')->nullable()->after('voided_at')->constrained('users')->nullOnDelete();
            $table->text('void_reason')->nullable()->after('voided_by');
        });
    }

    public function down(): void
    {
        Schema::table('leave_plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recalled_by');
            $table->dropConstrainedForeignId('voided_by');
            $table->dropColumn([
                'recalled_at',
                'recall_reason',
                'voided_at',
                'void_reason',
            ]);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE leave_plans MODIFY status ENUM('draft','submitted','approved','rejected','cancellation_requested','cancelled') NOT NULL DEFAULT 'draft'");
        }
    }
};
