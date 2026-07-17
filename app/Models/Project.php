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

    protected $fillable = ['project_code', 'project_name', 'client_name', 'is_active', 'timesheet_assignment_mode'];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function entries()
    {
        return $this->hasMany(TimesheetEntry::class);
    }

    public function assignedUsers()
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

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
