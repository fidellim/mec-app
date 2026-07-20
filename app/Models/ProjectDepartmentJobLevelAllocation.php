<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectDepartmentJobLevelAllocation extends Model
{
    protected $fillable = ['project_department_allocation_id', 'job_level', 'allocated_hours'];

    protected function casts(): array
    {
        return ['project_department_allocation_id' => 'integer', 'allocated_hours' => 'decimal:2'];
    }

    public function departmentAllocation()
    {
        return $this->belongsTo(ProjectDepartmentAllocation::class, 'project_department_allocation_id');
    }
}
