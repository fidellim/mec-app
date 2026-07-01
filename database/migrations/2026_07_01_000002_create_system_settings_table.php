<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('boolean_value')->default(false);
            $table->timestamps();
        });

        DB::table('system_settings')->insert([
            'key' => 'setup_mode_enabled',
            'name' => 'Setup Mode',
            'description' => 'Temporarily pauses employee and HOD access while administrators finish production setup.',
            'boolean_value' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
