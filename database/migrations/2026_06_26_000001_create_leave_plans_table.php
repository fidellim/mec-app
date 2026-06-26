<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->string('attendance_code', 20);
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('duration_type', ['full_day', 'half_day'])->default('full_day');
            $table->enum('half_day_period', ['morning', 'afternoon'])->nullable();
            $table->text('reason')->nullable();
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected', 'cancellation_requested', 'cancelled'])->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_comment')->nullable();
            $table->timestamp('cancellation_requested_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_rejection_comment')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'start_date', 'end_date']);
            $table->index(['department_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_plans');
    }
};
