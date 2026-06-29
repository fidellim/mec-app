<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeavePlanApproverSetting extends Model
{
    public const DIRECTOR = 'director';
    public const HR_UAE = 'hr_uae';
    public const HR_PH = 'hr_ph';

    public const KEYS = [
        self::DIRECTOR,
        self::HR_UAE,
        self::HR_PH,
    ];

    protected $fillable = ['key', 'user_id'];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'user_id' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
