<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectDepartmentAllocation extends Model
{
    protected $fillable = ['project_id', 'department_id', 'allocated_hours'];

    protected function casts(): array
    {
        return ['project_id' => 'integer', 'department_id' => 'integer', 'allocated_hours' => 'decimal:2'];
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function jobLevelAllocations()
    {
        return $this->hasMany(ProjectDepartmentJobLevelAllocation::class);
    }
}
