<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HolidayDate extends Model
{
    use HasFactory;

    protected $fillable = [
        'holiday_event_id',
        'region',
        'holiday_date',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'holiday_event_id' => 'integer',
            'holiday_date' => 'date',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(HolidayEvent::class, 'holiday_event_id');
    }
}
