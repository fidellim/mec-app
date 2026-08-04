<?php

namespace App\Models;

use App\Notifications\QueuedResetPassword;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const EMPLOYEE_TYPE_MEC_HR = 'MEC-HR';

    public const EMPLOYEE_TYPE_MCE_HR = 'MCE-HR';

    public const EMPLOYEE_TYPE_MEC_PHIL_HR = 'MEC-PHIL-HR';

    public const EMPLOYEE_TYPE_OTHER = 'other';

    protected $fillable = [
        'name', 'email', 'password', 'employee_code', 'initials', 'job_title', 'gender', 'joining_date', 'marital_status',
        'eligible_for_parental_leave', 'eligible_for_bereavement_spouse_leave', 'eligible_for_bereavement_immediate_family_leave',
        'eligible_for_maternity_leave', 'eligible_for_paternity_leave', 'eligible_for_vawc_leave', 'eligible_for_special_women_leave', 'is_solo_parent',
        'department_id', 'role', 'is_active', 'receives_hod_timesheet_submission_emails', 'annual_leave_allowance_days',
    ];

    protected $hidden = ['password', 'remember_token'];

    public static function employeeTypeLabels(): array
    {
        return [
            self::EMPLOYEE_TYPE_MEC_HR => self::EMPLOYEE_TYPE_MEC_HR,
            self::EMPLOYEE_TYPE_MCE_HR => self::EMPLOYEE_TYPE_MCE_HR,
            self::EMPLOYEE_TYPE_MEC_PHIL_HR => self::EMPLOYEE_TYPE_MEC_PHIL_HR,
            self::EMPLOYEE_TYPE_OTHER => 'Other / Unclassified',
        ];
    }

    public static function employeeTypeFromCode(?string $employeeCode): string
    {
        $employeeCode = strtoupper(trim((string) $employeeCode));

        foreach ([self::EMPLOYEE_TYPE_MEC_PHIL_HR, self::EMPLOYEE_TYPE_MEC_HR, self::EMPLOYEE_TYPE_MCE_HR] as $type) {
            if (str_starts_with($employeeCode, $type.'-')) {
                return $type;
            }
        }

        return self::employeeTypeLabels()[self::EMPLOYEE_TYPE_OTHER];
    }

    public function employeeTypeLabel(): string
    {
        return self::employeeTypeFromCode($this->employee_code);
    }

    public function scopeWithEmployeeTypes(Builder $query, array $employeeTypes): Builder
    {
        $employeeTypes = array_values(array_unique($employeeTypes));

        if ($employeeTypes === []) {
            return $query;
        }

        return $query->where(function (Builder $typeQuery) use ($employeeTypes): void {
            foreach ($employeeTypes as $employeeType) {
                if ($employeeType === self::EMPLOYEE_TYPE_OTHER) {
                    $typeQuery->orWhere(function (Builder $otherQuery): void {
                        $otherQuery
                            ->whereNull('employee_code')
                            ->orWhere(function (Builder $unclassifiedQuery): void {
                                foreach ([self::EMPLOYEE_TYPE_MEC_HR, self::EMPLOYEE_TYPE_MCE_HR, self::EMPLOYEE_TYPE_MEC_PHIL_HR] as $recognizedType) {
                                    $unclassifiedQuery->where('employee_code', 'not like', $recognizedType.'-%');
                                }
                            });
                    });

                    continue;
                }

                $typeQuery->orWhere('employee_code', 'like', $employeeType.'-%');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'department_id' => 'integer',
            'password' => 'hashed',
            'joining_date' => 'date',
            'eligible_for_parental_leave' => 'boolean',
            'eligible_for_bereavement_spouse_leave' => 'boolean',
            'eligible_for_bereavement_immediate_family_leave' => 'boolean',
            'eligible_for_maternity_leave' => 'boolean',
            'eligible_for_paternity_leave' => 'boolean',
            'eligible_for_vawc_leave' => 'boolean',
            'eligible_for_special_women_leave' => 'boolean',
            'is_solo_parent' => 'boolean',
            'is_active' => 'boolean',
            'receives_hod_timesheet_submission_emails' => 'boolean',
            'annual_leave_allowance_days' => 'decimal:2',
        ];
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function headedDepartment()
    {
        return $this->hasOne(Department::class, 'hod_id');
    }

    public function primaryDepartments()
    {
        return $this->hasMany(Department::class, 'hod_id');
    }

    public function managedDepartments()
    {
        return $this->belongsToMany(Department::class, 'department_hod')->withTimestamps();
    }

    public function managedDepartmentIds()
    {
        $ids = $this->managedDepartments()
            ->pluck('departments.id')
            ->merge($this->primaryDepartments()->pluck('id'));

        if ($this->department_id) {
            $ids->push($this->department_id);
        }

        return $ids
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    public function timesheets()
    {
        return $this->hasMany(Timesheet::class);
    }

    public function assignedProjects()
    {
        return $this->belongsToMany(Project::class)
            ->withPivot('manpower_category')
            ->withTimestamps();
    }

    public function managedProjects()
    {
        return $this->hasMany(Project::class, 'project_manager_id');
    }

    public function leavePlans()
    {
        return $this->hasMany(LeavePlan::class);
    }

    public function leaveEntitlements()
    {
        return $this->hasMany(LeaveEntitlement::class);
    }

    public function annualLeaveCarryOvers()
    {
        return $this->hasMany(AnnualLeaveCarryOver::class);
    }

    public function hodNotificationExcludedSubmitters()
    {
        return $this->belongsToMany(User::class, 'hod_notification_exclusions', 'hod_user_id', 'employee_user_id')
            ->withTimestamps();
    }

    public function hodApprovalExcludedSubmitters()
    {
        return $this->belongsToMany(User::class, 'hod_approval_exclusions', 'hod_user_id', 'employee_user_id')
            ->withTimestamps();
    }

    public function hodVisibilityExcludedSubmitters()
    {
        return $this->belongsToMany(User::class, 'hod_visibility_exclusions', 'hod_user_id', 'employee_user_id')
            ->withTimestamps();
    }

    public function notificationExcludedByHods()
    {
        return $this->belongsToMany(User::class, 'hod_notification_exclusions', 'employee_user_id', 'hod_user_id')
            ->withTimestamps();
    }

    public function approvalExcludedByHods()
    {
        return $this->belongsToMany(User::class, 'hod_approval_exclusions', 'employee_user_id', 'hod_user_id')
            ->withTimestamps();
    }

    public function visibilityExcludedByHods()
    {
        return $this->belongsToMany(User::class, 'hod_visibility_exclusions', 'employee_user_id', 'hod_user_id')
            ->withTimestamps();
    }

    public function adminNotificationExcludedHods()
    {
        return $this->belongsToMany(User::class, 'admin_notification_exclusions', 'admin_user_id', 'hod_user_id')
            ->withTimestamps();
    }

    public function adminApprovalExcludedHods()
    {
        return $this->belongsToMany(User::class, 'admin_approval_exclusions', 'admin_user_id', 'hod_user_id')
            ->withTimestamps();
    }

    public function notificationExcludedByAdmins()
    {
        return $this->belongsToMany(User::class, 'admin_notification_exclusions', 'hod_user_id', 'admin_user_id')
            ->withTimestamps();
    }

    public function approvalExcludedByAdmins()
    {
        return $this->belongsToMany(User::class, 'admin_approval_exclusions', 'hod_user_id', 'admin_user_id')
            ->withTimestamps();
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isAdminLike(): bool
    {
        return in_array($this->role, ['admin', 'super_admin'], true);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new QueuedResetPassword($token));
    }
}
