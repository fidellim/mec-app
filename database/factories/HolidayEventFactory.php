<?php

namespace Database\Factories;

use App\Models\HolidayEvent;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

class HolidayEventFactory extends Factory
{
    protected $model = HolidayEvent::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'region' => HolidayEvent::REGION_GLOBAL,
            'start_date' => '2026-05-12',
            'end_date' => '2026-05-12',
            'is_active' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (HolidayEvent $event) {
            if ($event->dates()->exists()) {
                return;
            }

            $dates = collect(CarbonPeriod::create(
                CarbonImmutable::parse($event->start_date),
                CarbonImmutable::parse($event->end_date)
            ))->map(fn ($date) => [
                'region' => $event->region,
                'holiday_date' => $date->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ])->all();

            $event->dates()->createMany($dates);
        });
    }
}
