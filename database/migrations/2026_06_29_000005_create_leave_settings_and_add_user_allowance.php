<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('leave_settings')) {
            Schema::create('leave_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->string('name');
                $table->text('description')->nullable();
                $table->decimal('decimal_value', 6, 2)->default(0);
                $table->timestamps();
            });
        }

        DB::table('leave_settings')->updateOrInsert(
            ['key' => 'annual_leave_default_days'],
            [
                'name' => 'Annual Leave Default Days',
                'description' => 'Default yearly L100 annual leave allowance. Unused days expire on December 31 and do not carry over.',
                'decimal_value' => 30,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        if (! Schema::hasColumn('users', 'annual_leave_allowance_days')) {
            Schema::table('users', function (Blueprint $table) {
                $table->decimal('annual_leave_allowance_days', 6, 2)->nullable()->after('receives_hod_timesheet_submission_emails');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'annual_leave_allowance_days')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('annual_leave_allowance_days');
            });
        }

        Schema::dropIfExists('leave_settings');
    }
};
