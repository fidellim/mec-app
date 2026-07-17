<?php

namespace App\Models;

use App\Notifications\QueuedResetPassword;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'employee_code', 'initials', 'job_title', 'gender', 'joining_date', 'marital_status',
        'eligible_for_parental_leave', 'eligible_for_bereavement_spouse_leave', 'eligible_for_bereavement_immediate_family_leave',
        'eligible_for_maternity_leave', 'eligible_for_paternity_leave', 'eligible_for_vawc_leave', 'eligible_for_special_women_leave', 'is_solo_parent',
        'department_id', 'role', 'is_active', 'receives_hod_timesheet_submission_emails', 'annual_leave_allowance_days',
    ];

    protected $hidden = ['password', 'remember_token'];

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
        return $this->belongsToMany(Project::class)->withTimestamps();
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
