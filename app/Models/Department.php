<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'code', 'hod_id', 'is_active'];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'hod_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function hod()
    {
        return $this->belongsTo(User::class, 'hod_id');
    }

    public function hods()
    {
        return $this->belongsToMany(User::class, 'department_hod')->withTimestamps();
    }

    public function timesheets()
    {
        return $this->hasMany(Timesheet::class);
    }

    public function projectAllocations() { return $this->hasMany(ProjectDepartmentAllocation::class); }

    public function leavePlans()
    {
        return $this->hasMany(LeavePlan::class);
    }
}
