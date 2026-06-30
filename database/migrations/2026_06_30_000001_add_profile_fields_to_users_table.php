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
            if (! Schema::hasColumn('users', 'gender')) {
                $gender = $table->string('gender', 20)->nullable();

                if (Schema::hasColumn('users', 'job_title')) {
                    $gender->after('job_title');
                }
            }

            if (! Schema::hasColumn('users', 'joining_date')) {
                $table->date('joining_date')->nullable()->after('gender');
            }

            if (! Schema::hasColumn('users', 'marital_status')) {
                $table->string('marital_status', 20)->nullable()->after('joining_date');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        foreach (['marital_status', 'joining_date', 'gender'] as $column) {
            if (Schema::hasColumn('users', $column)) {
                Schema::table('users', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
