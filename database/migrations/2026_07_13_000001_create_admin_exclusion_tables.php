<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_notification_exclusions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('hod_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['admin_user_id', 'hod_user_id'], 'admin_notification_exclusions_unique');
            $table->index('hod_user_id');
        });

        Schema::create('admin_approval_exclusions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('hod_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['admin_user_id', 'hod_user_id'], 'admin_approval_exclusions_unique');
            $table->index('hod_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_approval_exclusions');
        Schema::dropIfExists('admin_notification_exclusions');
    }
};
