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
        'name', 'email', 'password', 'employee_code', 'initials', 'job_title', 'department_id', 'role', 'is_active',
        'receives_hod_timesheet_submission_emails',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'department_id' => 'integer',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'receives_hod_timesheet_submission_emails' => 'boolean',
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

    public function leavePlans()
    {
        return $this->hasMany(LeavePlan::class);
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
