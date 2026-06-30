<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hod_visibility_exclusions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hod_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('employee_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['hod_user_id', 'employee_user_id'], 'hod_visibility_exclusions_unique');
            $table->index('employee_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hod_visibility_exclusions');
    }
};
