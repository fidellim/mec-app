<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('leave_plans') || Schema::hasColumn('leave_plans', 'policy_exception_reason')) {
            return;
        }

        Schema::table('leave_plans', function (Blueprint $table) {
            $table->text('policy_exception_reason')->nullable()->after('reason');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('leave_plans') || ! Schema::hasColumn('leave_plans', 'policy_exception_reason')) {
            return;
        }

        Schema::table('leave_plans', function (Blueprint $table) {
            $table->dropColumn('policy_exception_reason');
        });
    }
};
