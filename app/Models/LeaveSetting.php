<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class LeaveSetting extends Model
{
    public const ANNUAL_LEAVE_DEFAULT_DAYS = 'annual_leave_default_days';
    public const ANNUAL_LEAVE_DEFAULT_DAYS_UAE = 'annual_leave_default_days_uae';
    public const ANNUAL_LEAVE_DEFAULT_DAYS_PH = 'annual_leave_default_days_ph';
    public const SICK_LEAVE_DEFAULT_DAYS_UAE = 'sick_leave_default_days_uae';
    public const SICK_LEAVE_DEFAULT_DAYS_PH = 'sick_leave_default_days_ph';
    public const MATERNITY_LEAVE_DEFAULT_DAYS_UAE = 'maternity_leave_default_days_uae';
    public const MATERNITY_LEAVE_DEFAULT_DAYS_PH = 'maternity_leave_default_days_ph';
    public const PARENTAL_LEAVE_DEFAULT_DAYS_UAE = 'parental_leave_default_days_uae';
    public const PARENTAL_LEAVE_DEFAULT_DAYS_PH = 'parental_leave_default_days_ph';
    public const BEREAVEMENT_COMPASSIONATE_LEAVE_DEFAULT_DAYS_UAE = 'bereavement_compassionate_leave_default_days_uae';
    public const BEREAVEMENT_COMPASSIONATE_LEAVE_DEFAULT_DAYS_PH = 'bereavement_compassionate_leave_default_days_ph';
    public const BEREAVEMENT_SPOUSE_LEAVE_DAYS_UAE = 'bereavement_spouse_leave_days_uae';
    public const BEREAVEMENT_IMMEDIATE_FAMILY_LEAVE_DAYS_UAE = 'bereavement_immediate_family_leave_days_uae';
    public const SERVICE_INCENTIVE_LEAVE_DEFAULT_DAYS_PH = 'service_incentive_leave_default_days_ph';

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
