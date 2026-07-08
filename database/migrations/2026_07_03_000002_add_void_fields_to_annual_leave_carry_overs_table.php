<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('annual_leave_carry_overs')) {
            return;
        }

        Schema::table('annual_leave_carry_overs', function (Blueprint $table) {
            if (! Schema::hasColumn('annual_leave_carry_overs', 'voided_by')) {
                $table->foreignId('voided_by')->nullable()->after('rejected_at')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('annual_leave_carry_overs', 'voided_at')) {
                $table->timestamp('voided_at')->nullable()->after('voided_by');
            }

            if (! Schema::hasColumn('annual_leave_carry_overs', 'void_reason')) {
                $table->text('void_reason')->nullable()->after('voided_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('annual_leave_carry_overs')) {
            return;
        }

        Schema::table('annual_leave_carry_overs', function (Blueprint $table) {
            if (Schema::hasColumn('annual_leave_carry_overs', 'voided_by')) {
                $table->dropConstrainedForeignId('voided_by');
            }

            if (Schema::hasColumn('annual_leave_carry_overs', 'voided_at')) {
                $table->dropColumn('voided_at');
            }

            if (Schema::hasColumn('annual_leave_carry_overs', 'void_reason')) {
                $table->dropColumn('void_reason');
            }
        });
    }
};
