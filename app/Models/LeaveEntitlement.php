<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveEntitlement extends Model
{
    public const SOURCE_REGIONAL_DEFAULT = 'regional_default';
    public const SOURCE_USER_OVERRIDE = 'user_override';

    protected $fillable = [
        'user_id',
        'year',
        'attendance_code',
        'allowance_days',
        'claimable_allowance_days',
        'source',
        'region',
        'setting_key',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'user_id' => 'integer',
            'year' => 'integer',
            'allowance_days' => 'decimal:2',
            'claimable_allowance_days' => 'decimal:2',
            'created_by' => 'integer',
            'updated_by' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
