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
            if (! Schema::hasColumn('users', 'eligible_for_maternity_leave')) {
                $table->boolean('eligible_for_maternity_leave')->default(false)->after('eligible_for_parental_leave');
            }

            if (! Schema::hasColumn('users', 'eligible_for_paternity_leave')) {
                $table->boolean('eligible_for_paternity_leave')->default(false)->after('eligible_for_maternity_leave');
            }

            if (! Schema::hasColumn('users', 'eligible_for_vawc_leave')) {
                $table->boolean('eligible_for_vawc_leave')->default(false)->after('eligible_for_paternity_leave');
            }

            if (! Schema::hasColumn('users', 'eligible_for_special_women_leave')) {
                $table->boolean('eligible_for_special_women_leave')->default(false)->after('eligible_for_vawc_leave');
            }

            if (! Schema::hasColumn('users', 'is_solo_parent')) {
                $table->boolean('is_solo_parent')->default(false)->after('eligible_for_special_women_leave');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        foreach ([
            'is_solo_parent',
            'eligible_for_special_women_leave',
            'eligible_for_vawc_leave',
            'eligible_for_paternity_leave',
            'eligible_for_maternity_leave',
        ] as $column) {
            if (Schema::hasColumn('users', $column)) {
                Schema::table('users', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
