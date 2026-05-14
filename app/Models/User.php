<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'employee_code', 'department_id', 'role', 'is_active',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'department_id' => 'integer',
            'password' => 'hashed',
            'is_active' => 'boolean',
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

    public function timesheets()
    {
        return $this->hasMany(Timesheet::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isAdminLike(): bool
    {
        return in_array($this->role, ['admin', 'super_admin'], true);
    }
}
