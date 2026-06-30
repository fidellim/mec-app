<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeavePlanStatusHistory extends Model
{
    protected $fillable = [
        'leave_plan_id',
        'actor_id',
        'action',
        'old_status',
        'new_status',
        'old_approval_stage',
        'new_approval_stage',
        'comment',
        'ip_address',
        'metadata',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'leave_plan_id' => 'integer',
            'actor_id' => 'integer',
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function leavePlan()
    {
        return $this->belongsTo(LeavePlan::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
