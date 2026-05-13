<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Timesheet extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'department_id', 'timesheet_period_id', 'status', 'submitted_at',
        'approved_at', 'approved_by', 'rejected_at', 'rejected_by', 'rejection_comment',
        'total_regular_hours', 'total_overtime_hours', 'total_hours',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'total_regular_hours' => 'decimal:2',
            'total_overtime_hours' => 'decimal:2',
            'total_hours' => 'decimal:2',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function period()
    {
        return $this->belongsTo(TimesheetPeriod::class, 'timesheet_period_id');
    }

    public function entries()
    {
        return $this->hasMany(TimesheetEntry::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejector()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function editableBy(User $user): bool
    {
        return $this->user_id === $user->id && in_array($this->status, ['draft', 'rejected'], true);
    }
}
