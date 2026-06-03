<?php

use App\Models\Department;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('department_hod', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['department_id', 'user_id']);
        });

        Department::whereNotNull('hod_id')
            ->get(['id', 'hod_id'])
            ->each(function (Department $department) {
                DB::table('department_hod')->updateOrInsert(
                    [
                        'department_id' => $department->id,
                        'user_id' => $department->hod_id,
                    ],
                    [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('department_hod');
    }
};
