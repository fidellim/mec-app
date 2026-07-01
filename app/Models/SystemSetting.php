<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class SystemSetting extends Model
{
    public const SETUP_MODE_ENABLED = 'setup_mode_enabled';

    protected $fillable = [
        'key',
        'name',
        'description',
        'boolean_value',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'boolean_value' => 'boolean',
        ];
    }

    public static function setupMode(): self
    {
        return self::firstOrCreate(
            ['key' => self::SETUP_MODE_ENABLED],
            [
                'name' => 'Setup Mode',
                'description' => 'Temporarily pauses employee and HOD access while administrators finish production setup.',
                'boolean_value' => false,
            ],
        );
    }

    public static function setupModeEnabled(bool $default = false): bool
    {
        try {
            return (bool) self::where('key', self::SETUP_MODE_ENABLED)->value('boolean_value');
        } catch (\Throwable $exception) {
            Log::warning('System setting lookup failed.', [
                'key' => self::SETUP_MODE_ENABLED,
                'message' => $exception->getMessage(),
            ]);

            return $default;
        }
    }
}
