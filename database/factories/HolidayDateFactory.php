<?php

namespace Database\Factories;

use App\Models\HolidayDate;
use App\Models\HolidayEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

class HolidayDateFactory extends Factory
{
    protected $model = HolidayDate::class;

    public function definition(): array
    {
        return [
            'holiday_event_id' => HolidayEvent::factory(),
            'region' => HolidayEvent::REGION_GLOBAL,
            'holiday_date' => '2026-05-12',
        ];
    }
}
