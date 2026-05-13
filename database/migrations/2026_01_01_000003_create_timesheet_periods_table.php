<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timesheet_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('week_number');
            $table->unsignedSmallInteger('year');
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->timestamps();
            $table->unique(['week_number', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timesheet_periods');
    }
};
