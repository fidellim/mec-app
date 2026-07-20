<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timesheet_correction_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('timesheet_id');
            $table->foreign('timesheet_id', 'ts_corr_timesheet_fk')->references('id')->on('timesheets')->cascadeOnDelete();
            $table->foreignId('requested_by');
            $table->foreign('requested_by', 'ts_corr_requester_fk')->references('id')->on('users')->restrictOnDelete();
            $table->foreignId('department_id');
            $table->foreign('department_id', 'ts_corr_department_fk')->references('id')->on('departments')->restrictOnDelete();
            $table->string('status', 24)->default('open');
            $table->text('comment');
            $table->foreignId('resolved_by')->nullable();
            $table->foreign('resolved_by', 'ts_corr_resolver_fk')->references('id')->on('users')->nullOnDelete();
            $table->text('resolution_comment')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('superseded_by_request_id')->nullable();
            $table->foreign('superseded_by_request_id', 'ts_corr_superseded_fk')->references('id')->on('timesheet_correction_requests')->nullOnDelete();
            $table->string('authority_role', 30)->nullable();
            $table->timestamps();
            $table->index(['department_id', 'status', 'created_at'], 'correction_department_status_idx');
            $table->index(['timesheet_id', 'status'], 'correction_timesheet_status_idx');
            $table->index(['requested_by', 'status'], 'correction_requester_status_idx');
        });

        Schema::create('timesheet_correction_request_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('timesheet_correction_request_id');
            $table->foreign('timesheet_correction_request_id', 'ts_corr_item_request_fk')->references('id')->on('timesheet_correction_requests')->cascadeOnDelete();
            $table->foreignId('timesheet_entry_id')->nullable();
            $table->foreign('timesheet_entry_id', 'ts_corr_item_entry_fk')->references('id')->on('timesheet_entries')->nullOnDelete();
            $table->foreignId('project_id')->nullable();
            $table->foreign('project_id', 'ts_corr_item_project_fk')->references('id')->on('projects')->nullOnDelete();
            $table->date('work_date');
            $table->string('project_code')->nullable();
            $table->decimal('regular_hours', 8, 2)->default(0);
            $table->decimal('overtime_hours', 8, 2)->default(0);
            $table->text('description')->nullable();
            $table->text('entry_comment')->nullable();
            $table->decimal('suggested_regular_hours', 8, 2)->nullable();
            $table->decimal('suggested_overtime_hours', 8, 2)->nullable();
            $table->timestamps();
            $table->index(['timesheet_entry_id', 'timesheet_correction_request_id'], 'correction_entry_request_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timesheet_correction_request_entries');
        Schema::dropIfExists('timesheet_correction_requests');
    }
};
