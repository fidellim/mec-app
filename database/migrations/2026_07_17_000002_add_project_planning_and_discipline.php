<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->date('start_date')->nullable()->after('client_name');
            $table->foreignId('project_manager_id')->nullable()->after('start_date')->constrained('users')->nullOnDelete();
            $table->index(['project_manager_id', 'is_active']);
        });

        Schema::create('project_department_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->decimal('allocated_hours', 12, 2);
            $table->timestamps();
            $table->unique(['project_id', 'department_id']);
        });

        Schema::table('timesheet_entries', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('project_id')->constrained()->restrictOnDelete();
            $table->index(['project_id', 'department_id', 'timesheet_id'], 'entries_project_dept_timesheet_idx');
        });

    }

    public function down(): void
    {
        Schema::table('timesheet_entries', function (Blueprint $table) {
            $table->dropIndex('entries_project_dept_timesheet_idx');
            $table->dropConstrainedForeignId('department_id');
        });
        Schema::dropIfExists('project_department_allocations');
        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['project_manager_id', 'is_active']);
            $table->dropConstrainedForeignId('project_manager_id');
            $table->dropColumn('start_date');
        });
    }
};
