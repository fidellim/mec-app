<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('leave_plans', 'approval_stage')) {
            return;
        }

        Schema::table('leave_plans', function (Blueprint $table) {
            $table->string('approval_stage', 30)->nullable()->after('status');
            $table->timestamp('hod_approved_at')->nullable()->after('approved_by');
            $table->foreignId('hod_approved_by')->nullable()->after('hod_approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('director_approved_at')->nullable()->after('hod_approved_by');
            $table->foreignId('director_approved_by')->nullable()->after('director_approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('hr_approved_at')->nullable()->after('director_approved_by');
            $table->foreignId('hr_approved_by')->nullable()->after('hr_approved_at')->constrained('users')->nullOnDelete();
            $table->string('rejected_approval_stage', 30)->nullable()->after('rejection_comment');

            $table->index(['approval_stage', 'status']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('leave_plans', 'approval_stage')) {
            return;
        }

        Schema::table('leave_plans', function (Blueprint $table) {
            $table->dropForeign(['hod_approved_by']);
            $table->dropForeign(['director_approved_by']);
            $table->dropForeign(['hr_approved_by']);
            $table->dropIndex(['approval_stage', 'status']);
            $table->dropColumn([
                'approval_stage',
                'hod_approved_at',
                'hod_approved_by',
                'director_approved_at',
                'director_approved_by',
                'hr_approved_at',
                'hr_approved_by',
                'rejected_approval_stage',
            ]);
        });
    }
};
