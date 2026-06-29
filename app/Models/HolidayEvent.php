<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HolidayEvent extends Model
{
    use HasFactory;

    public const REGION_GLOBAL = 'global';
    public const REGION_UAE = 'uae';
    public const REGION_PH = 'ph';

    public const REGIONS = [
        self::REGION_GLOBAL => 'Global',
        self::REGION_UAE => 'United Arab Emirates',
        self::REGION_PH => 'Philippines',
    ];

    protected $fillable = [
        'name',
        'region',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function dates(): HasMany
    {
        return $this->hasMany(HolidayDate::class);
    }

    public function regionLabel(): string
    {
        return self::REGIONS[$this->region] ?? ucfirst((string) $this->region);
    }

    public function dateRangeLabel(): string
    {
        $start = $this->start_date?->toDateString();
        $end = $this->end_date?->toDateString();

        return $start === $end ? (string) $start : $start.' to '.$end;
    }
}
