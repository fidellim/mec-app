<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'eligible_for_parental_leave')) {
                $table->boolean('eligible_for_parental_leave')->default(false)->after('marital_status');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'eligible_for_parental_leave')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('eligible_for_parental_leave');
        });
    }
};
