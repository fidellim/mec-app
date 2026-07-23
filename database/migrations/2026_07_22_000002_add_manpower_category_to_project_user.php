<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_user', function (Blueprint $table) {
            $table->string('manpower_category', 32)->nullable()->after('user_id');
            $table->index(['project_id', 'manpower_category'], 'project_user_manpower_category_idx');
        });
    }

    public function down(): void
    {
        Schema::table('project_user', function (Blueprint $table) {
            $table->dropIndex('project_user_manpower_category_idx');
            $table->dropColumn('manpower_category');
        });
    }
};
