<?php

namespace App\Models;

use App\Services\LeaveEntitlementService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

    public const APPROVAL_STAGE_HOD = 'hod';

    public const APPROVAL_STAGE_DIRECTOR = 'director';

    public const APPROVAL_STAGE_HR = 'hr';

    public const BEREAVEMENT_RELATIONSHIP_SPOUSE = 'spouse';

    public const BEREAVEMENT_RELATIONSHIP_IMMEDIATE_FAMILY = 'immediate_family';

    public const BEREAVEMENT_RELATIONSHIPS = [
        self::BEREAVEMENT_RELATIONSHIP_SPOUSE,
        self::BEREAVEMENT_RELATIONSHIP_IMMEDIATE_FAMILY,
    ];

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
        'bereavement_relationship',
        'reason',
        'policy_exception_reason',
        'status',
        'approval_stage',
        'submitted_at',
        'approved_at',
        'approved_by',
        'hod_approved_at',
        'hod_approved_by',
        'director_approved_at',
        'director_approved_by',
        'hr_approved_at',
        'hr_approved_by',
        'rejected_at',
        'rejected_by',
        'rejection_comment',
        'rejected_approval_stage',
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
            'hod_approved_at' => 'datetime',
            'hod_approved_by' => 'integer',
            'director_approved_at' => 'datetime',
            'director_approved_by' => 'integer',
            'hr_approved_at' => 'datetime',
            'hr_approved_by' => 'integer',
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

    public function hodApprover()
    {
        return $this->belongsTo(User::class, 'hod_approved_by');
    }

    public function directorApprover()
    {
        return $this->belongsTo(User::class, 'director_approved_by');
    }

    public function hrApprover()
    {
        return $this->belongsTo(User::class, 'hr_approved_by');
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

    public function statusHistories()
    {
        return $this->hasMany(LeavePlanStatusHistory::class)->latest('occurred_at');
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

    public function weekdayCount(): float
    {
        return $this->countedLeaveDayCount();
    }

    public function countedLeaveDayCount(): float
    {
        return app(LeaveEntitlementService::class)->countedLeaveDayCountForPlan($this);
    }

    public function durationLabel(?float $countedDays = null): string
    {
        return $this->formatDayCount(
            $countedDays ?? $this->countedLeaveDayCount(),
            app(LeaveEntitlementService::class)->countBasisLabelForPlan($this),
        );
    }

    public function leaveLengthLabel(?float $countedDays = null): string
    {
        $label = str_replace('_', ' ', $this->duration_type);

        if ($this->half_day_period) {
            $label .= ' - '.$this->half_day_period;
        }

        return ucfirst($label).' ('.$this->durationLabel($countedDays).')';
    }

    public function bereavementRelationshipLabel(): ?string
    {
        return match ($this->bereavement_relationship) {
            self::BEREAVEMENT_RELATIONSHIP_SPOUSE => 'Spouse',
            self::BEREAVEMENT_RELATIONSHIP_IMMEDIATE_FAMILY => 'Immediate family',
            default => null,
        };
    }

    public static function bereavementRelationshipOptions(): array
    {
        return [
            self::BEREAVEMENT_RELATIONSHIP_SPOUSE => 'Spouse',
            self::BEREAVEMENT_RELATIONSHIP_IMMEDIATE_FAMILY => 'Immediate family',
        ];
    }

    public function approvalStageLabel(?string $stage = null): string
    {
        return match ($stage ?? $this->approval_stage) {
            self::APPROVAL_STAGE_HOD => 'Head of Department',
            self::APPROVAL_STAGE_DIRECTOR => 'Director of Engineering & Project Management',
            self::APPROVAL_STAGE_HR => 'HR Department',
            default => '-',
        };
    }

    public function approvalProgressLabel(): string
    {
        if ($this->status === self::STATUS_APPROVED) {
            return $this->hr_approved_at ? 'Approved by HR' : 'Approved';
        }

        if ($this->status === self::STATUS_REJECTED && $this->rejected_approval_stage) {
            return 'Rejected at '.$this->approvalStageLabel($this->rejected_approval_stage);
        }

        if ($this->status === self::STATUS_SUBMITTED && $this->approval_stage) {
            return 'Pending '.$this->approvalStageLabel();
        }

        return '-';
    }

    private function formatDayCount(float $count, string $singular): string
    {
        $formatted = floor($count) === $count ? (string) (int) $count : rtrim(rtrim(number_format($count, 2), '0'), '.');
        $isSingular = $count > 0.0 && $count <= 1.0;

        return $formatted.' '.$singular.($isSingular ? '' : 's');
    }
}
