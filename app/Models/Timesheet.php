<?php

namespace App\Models;

use App\Services\DashboardSummaryService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Services\TimesheetCorrectionLifecycleService;

class Timesheet extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_WITHDRAWN = 'withdrawn';
    public const STATUS_RECALLED = 'recalled';
    public const STATUS_VOIDED = 'voided';

    public const ACTIVE_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SUBMITTED,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_WITHDRAWN,
        self::STATUS_RECALLED,
    ];

    protected $fillable = [
        'user_id', 'department_id', 'timesheet_period_id', 'status', 'submitted_at',
        'approved_at', 'approved_by', 'rejected_at', 'rejected_by', 'rejection_comment',
        'voided_at', 'voided_by', 'void_reason',
        'total_regular_hours', 'total_overtime_hours', 'total_hours',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'user_id' => 'integer',
            'department_id' => 'integer',
            'timesheet_period_id' => 'integer',
            'approved_by' => 'integer',
            'rejected_by' => 'integer',
            'voided_by' => 'integer',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'voided_at' => 'datetime',
            'total_regular_hours' => 'decimal:2',
            'total_overtime_hours' => 'decimal:2',
            'total_hours' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (Timesheet $timesheet) {
            app(DashboardSummaryService::class)->forgetForTimesheet(
                $timesheet,
                $timesheet->getOriginal('department_id'),
                $timesheet->getOriginal('timesheet_period_id')
            );

            if ($timesheet->wasChanged('status')) app(TimesheetCorrectionLifecycleService::class)->supersedeOpen($timesheet);
        });

        static::deleted(function (Timesheet $timesheet) {
            app(DashboardSummaryService::class)->forgetForTimesheet(
                $timesheet,
                $timesheet->getOriginal('department_id'),
                $timesheet->getOriginal('timesheet_period_id')
            );
        });
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

    public function correctionRequests()
    {
        return $this->hasMany(TimesheetCorrectionRequest::class);
    }

    public function auditLogs()
    {
        return $this->morphMany(AuditLog::class, 'auditable')->latest();
    }

    public function statusHistories()
    {
        return $this->hasMany(TimesheetStatusHistory::class)->latest('occurred_at');
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

    public function editableBy(User $user): bool
    {
        return (int) $this->user_id === (int) $user->id
            && in_array($this->status, [self::STATUS_DRAFT, self::STATUS_REJECTED, self::STATUS_WITHDRAWN, self::STATUS_RECALLED], true);
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }
}
