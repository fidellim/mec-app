<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('annual_leave_carry_overs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('from_year');
            $table->unsignedSmallInteger('to_year');
            $table->string('attendance_code', 20)->default('L100');
            $table->decimal('suggested_days', 6, 2)->default(0);
            $table->decimal('approved_days', 6, 2)->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('source', 40)->default('manual_opening_balance');
            $table->text('notes')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'from_year', 'to_year', 'attendance_code'], 'annual_leave_carry_overs_unique');
            $table->index(['to_year', 'status']);
            $table->index(['from_year', 'to_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('annual_leave_carry_overs');
    }
};
