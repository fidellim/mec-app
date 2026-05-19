<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class AutomationSetting extends Model
{
    public const TIMESHEET_MISSING_REMINDERS = 'timesheet_missing_reminders';

    protected $fillable = [
        'key',
        'name',
        'description',
        'is_enabled',
        'last_run_at',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'is_enabled' => 'boolean',
            'last_run_at' => 'datetime',
        ];
    }

    public static function enabled(string $key, bool $default = true): bool
    {
        try {
            $setting = self::where('key', $key)->first();
        } catch (\Throwable $exception) {
            Log::warning('Automation setting check failed.', [
                'key' => $key,
                'message' => $exception->getMessage(),
            ]);

            return $default;
        }

        return $setting ? $setting->is_enabled : $default;
    }

    public static function markRan(string $key): void
    {
        try {
            self::where('key', $key)->update(['last_run_at' => now()]);
        } catch (\Throwable $exception) {
            Log::warning('Automation last run update failed.', [
                'key' => $key,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
