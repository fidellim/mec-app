<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class LeaveSetting extends Model
{
    public const ANNUAL_LEAVE_DEFAULT_DAYS = 'annual_leave_default_days';

    protected $fillable = [
        'key',
        'name',
        'description',
        'decimal_value',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'decimal_value' => 'decimal:2',
        ];
    }

    public static function decimalValue(string $key, float $default = 0.0): float
    {
        try {
            $setting = self::where('key', $key)->first();
        } catch (\Throwable $exception) {
            Log::warning('Leave setting lookup failed.', [
                'key' => $key,
                'message' => $exception->getMessage(),
            ]);

            return $default;
        }

        return $setting ? (float) $setting->decimal_value : $default;
    }
}
