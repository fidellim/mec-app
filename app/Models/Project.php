<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Project extends Model
{
    use HasFactory;

    public const ASSIGNMENT_ALL_USERS = 'all_users';

    public const ASSIGNMENT_SELECTED_USERS = 'selected_users';

    protected $fillable = ['project_code', 'project_name', 'client_name', 'start_date', 'project_manager_id', 'is_active', 'timesheet_assignment_mode'];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'is_active' => 'boolean',
            'start_date' => 'date',
            'project_manager_id' => 'integer',
        ];
    }

    public function entries()
    {
        return $this->hasMany(TimesheetEntry::class);
    }

    public function assignedUsers()
    {
        return $this->belongsToMany(User::class)
            ->withPivot('manpower_category')
            ->withTimestamps();
    }

    public function manpowerCategoryFor(User $user): ?string
    {
        $assignedUser = $this->relationLoaded('assignedUsers')
            ? $this->assignedUsers->firstWhere('id', $user->id)
            : $this->assignedUsers()->whereKey($user->id)->first();

        return filled($assignedUser?->pivot?->manpower_category)
            ? $assignedUser->pivot->manpower_category
            : null;
    }

    public function projectManager() { return $this->belongsTo(User::class, 'project_manager_id'); }
    public function departmentAllocations() { return $this->hasMany(ProjectDepartmentAllocation::class); }

    public function scopeAvailableForTimesheetsBy(Builder $query, User $user): Builder
    {
        return $query->where('is_active', true)
            ->where(function (Builder $query) use ($user) {
                $query->where('timesheet_assignment_mode', self::ASSIGNMENT_ALL_USERS)
                    ->orWhere(function (Builder $query) use ($user) {
                        $query->where('timesheet_assignment_mode', self::ASSIGNMENT_SELECTED_USERS)
                            ->whereHas('assignedUsers', fn (Builder $query) => $query->whereKey($user->id));
                    });
            });
    }

    public function isAvailableForTimesheetsBy(User $user): bool
    {
        return $this->is_active && (
            $this->timesheet_assignment_mode === self::ASSIGNMENT_ALL_USERS
            || $this->assignedUsers()->whereKey($user->id)->exists()
        );
    }
}
