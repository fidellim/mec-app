<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimesheetStatusHistory extends Model
{
    protected $fillable = [
        'timesheet_id',
        'actor_id',
        'action',
        'old_status',
        'new_status',
        'comment',
        'ip_address',
        'metadata',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'timesheet_id' => 'integer',
            'actor_id' => 'integer',
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function timesheet()
    {
        return $this->belongsTo(Timesheet::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
