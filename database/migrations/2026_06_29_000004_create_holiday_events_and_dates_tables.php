<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holiday_events', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('region', ['global', 'uae', 'ph'])->default('global');
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'region', 'start_date', 'end_date']);
        });

        Schema::create('holiday_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('holiday_event_id')->constrained()->cascadeOnDelete();
            $table->enum('region', ['global', 'uae', 'ph'])->default('global');
            $table->date('holiday_date');
            $table->timestamps();

            $table->unique(['region', 'holiday_date']);
            $table->index(['holiday_date', 'region']);
        });

        $this->migrateExistingHolidays();
    }

    public function down(): void
    {
        Schema::dropIfExists('holiday_dates');
        Schema::dropIfExists('holiday_events');
    }

    private function migrateExistingHolidays(): void
    {
        if (! Schema::hasTable('holidays')) {
            return;
        }

        $rows = collect(DB::table('holidays')
            ->orderBy('name')
            ->orderBy('region')
            ->orderBy('is_active')
            ->orderBy('holiday_date')
            ->get());

        $rows
            ->groupBy(fn ($row) => implode('|', [$row->name, $row->region, (int) $row->is_active]))
            ->each(function ($group) {
                $currentRange = [];
                $previousDate = null;

                foreach ($group as $row) {
                    $date = CarbonImmutable::parse($row->holiday_date);

                    if ($previousDate && ! $date->isSameDay($previousDate->addDay())) {
                        $this->insertRange($currentRange);
                        $currentRange = [];
                    }

                    $currentRange[] = $row;
                    $previousDate = $date;
                }

                if ($currentRange !== []) {
                    $this->insertRange($currentRange);
                }
            });
    }

    private function insertRange(array $rows): void
    {
        $first = $rows[0];
        $last = $rows[array_key_last($rows)];
        $now = now();

        $eventId = DB::table('holiday_events')->insertGetId([
            'name' => $first->name,
            'region' => $first->region,
            'start_date' => $first->holiday_date,
            'end_date' => $last->holiday_date,
            'is_active' => (bool) $first->is_active,
            'created_at' => $first->created_at ?? $now,
            'updated_at' => $last->updated_at ?? $now,
        ]);

        foreach ($rows as $row) {
            DB::table('holiday_dates')->insert([
                'holiday_event_id' => $eventId,
                'region' => $row->region,
                'holiday_date' => $row->holiday_date,
                'created_at' => $row->created_at ?? $now,
                'updated_at' => $row->updated_at ?? $now,
            ]);
        }
    }
};
