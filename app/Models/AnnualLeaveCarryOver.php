<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnnualLeaveCarryOver extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_VOIDED = 'voided';

    public const SOURCE_MANUAL_OPENING_BALANCE = 'manual_opening_balance';
    public const SOURCE_YEAR_END_GENERATED = 'year_end_generated';
    public const SOURCE_MANUAL_ADJUSTMENT = 'manual_adjustment';

    protected $fillable = [
        'user_id',
        'from_year',
        'to_year',
        'attendance_code',
        'suggested_days',
        'approved_days',
        'status',
        'source',
        'notes',
        'generated_by',
        'generated_at',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'voided_by',
        'voided_at',
        'void_reason',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'user_id' => 'integer',
            'from_year' => 'integer',
            'to_year' => 'integer',
            'suggested_days' => 'decimal:2',
            'approved_days' => 'decimal:2',
            'generated_by' => 'integer',
            'generated_at' => 'datetime',
            'approved_by' => 'integer',
            'approved_at' => 'datetime',
            'rejected_by' => 'integer',
            'rejected_at' => 'datetime',
            'voided_by' => 'integer',
            'voided_at' => 'datetime',
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

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejector()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function voider()
    {
        return $this->belongsTo(User::class, 'voided_by');
    }
}
