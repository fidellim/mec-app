<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
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
        'holiday_date',
        'region',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'holiday_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function regionLabel(): string
    {
        return self::REGIONS[$this->region] ?? ucfirst((string) $this->region);
    }
}
