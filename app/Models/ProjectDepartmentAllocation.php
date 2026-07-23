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

    public function manpowerCategoryAllocations()
    {
        return $this->hasMany(ProjectDepartmentManpowerCategoryAllocation::class);
    }

    public function usesManpowerCategories(): bool
    {
        return $this->manpowerCategoryAllocations->isNotEmpty();
    }

    public function hasCurrentManpowerCategoryConfiguration(): bool
    {
        $canonicalCategories = array_keys(config('manpower_categories.labels'));
        $rows = $this->manpowerCategoryAllocations;

        return $rows->isNotEmpty()
            && ! $rows->contains(fn ($row) => ! in_array($row->manpower_category, $canonicalCategories, true))
            && $rows->whereIn('manpower_category', $canonicalCategories)->count() === count($canonicalCategories);
    }

    public function allowsManpowerCategory(?string $manpowerCategory): bool
    {
        if (! $this->usesManpowerCategories()) {
            return true;
        }

        if (! $manpowerCategory || ! $this->hasCurrentManpowerCategoryConfiguration()) {
            return false;
        }

        $categoryAllocation = $this->manpowerCategoryAllocations->firstWhere('manpower_category', $manpowerCategory);

        return $categoryAllocation
            && ($categoryAllocation->allocated_hours === null || (float) $categoryAllocation->allocated_hours > 0);
    }
}
