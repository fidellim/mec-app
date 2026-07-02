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
            if (! Schema::hasColumn('users', 'eligible_for_bereavement_spouse_leave')) {
                $table->boolean('eligible_for_bereavement_spouse_leave')->default(false)->after('eligible_for_parental_leave');
            }

            if (! Schema::hasColumn('users', 'eligible_for_bereavement_immediate_family_leave')) {
                $table->boolean('eligible_for_bereavement_immediate_family_leave')->default(false)->after('eligible_for_bereavement_spouse_leave');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        foreach ([
            'eligible_for_bereavement_immediate_family_leave',
            'eligible_for_bereavement_spouse_leave',
        ] as $column) {
            if (Schema::hasColumn('users', $column)) {
                Schema::table('users', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
