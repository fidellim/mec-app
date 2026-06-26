<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\CarbonPeriod;

class LeavePlan extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLATION_REQUESTED = 'cancellation_requested';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_RECALLED = 'recalled';
    public const STATUS_VOIDED = 'voided';

    public const ACTIVE_OVERLAP_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SUBMITTED,
        self::STATUS_APPROVED,
        self::STATUS_CANCELLATION_REQUESTED,
    ];

    protected $fillable = [
        'user_id',
        'department_id',
        'attendance_code',
        'start_date',
        'end_date',
        'duration_type',
        'half_day_period',
        'reason',
        'status',
        'submitted_at',
        'approved_at',
        'approved_by',
        'rejected_at',
        'rejected_by',
        'rejection_comment',
        'cancellation_requested_at',
        'cancellation_reason',
        'cancelled_at',
        'cancelled_by',
        'cancellation_rejection_comment',
        'recalled_at',
        'recalled_by',
        'recall_reason',
        'voided_at',
        'voided_by',
        'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'user_id' => 'integer',
            'department_id' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'approved_by' => 'integer',
            'rejected_at' => 'datetime',
            'rejected_by' => 'integer',
            'cancellation_requested_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'cancelled_by' => 'integer',
            'recalled_at' => 'datetime',
            'recalled_by' => 'integer',
            'voided_at' => 'datetime',
            'voided_by' => 'integer',
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

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejector()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function canceller()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function recaller()
    {
        return $this->belongsTo(User::class, 'recalled_by');
    }

    public function voider()
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function auditLogs()
    {
        return $this->morphMany(AuditLog::class, 'auditable')->latest();
    }

    public function editableBy(User $user): bool
    {
        return (int) $this->user_id === (int) $user->id
            && in_array($this->status, [self::STATUS_DRAFT, self::STATUS_REJECTED, self::STATUS_RECALLED], true);
    }

    public function leaveLabel(): string
    {
        return $this->attendance_code.' - '.(config('timesheet.attendance_codes')[$this->attendance_code] ?? $this->attendance_code);
    }

    public function calendarDayCount(): float
    {
        if ($this->duration_type === 'half_day') {
            return 0.5;
        }

        return $this->start_date->diffInDays($this->end_date) + 1;
    }

    public function weekdayCount(): float
    {
        if ($this->duration_type === 'half_day') {
            return 0.5;
        }

        return collect(CarbonPeriod::create($this->start_date, $this->end_date))
            ->filter(fn ($date) => ! $date->isWeekend())
            ->count();
    }

    public function durationLabel(): string
    {
        return $this->formatDayCount($this->calendarDayCount(), 'calendar day')
            .' / '.$this->formatDayCount($this->weekdayCount(), 'weekday');
    }

    public function leaveLengthLabel(): string
    {
        $label = str_replace('_', ' ', $this->duration_type);

        if ($this->half_day_period) {
            $label .= ' - '.$this->half_day_period;
        }

        return ucfirst($label).' ('.$this->durationLabel().')';
    }

    private function formatDayCount(float $count, string $singular): string
    {
        $formatted = floor($count) === $count ? (string) (int) $count : rtrim(rtrim(number_format($count, 2), '0'), '.');

        return $formatted.' '.$singular.($count === 1.0 ? '' : 's');
    }
}
