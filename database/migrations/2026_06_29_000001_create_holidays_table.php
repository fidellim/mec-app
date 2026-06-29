<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('holiday_date');
            $table->enum('region', ['global', 'uae', 'ph'])->default('global');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['region', 'holiday_date']);
            $table->index(['is_active', 'region', 'holiday_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
