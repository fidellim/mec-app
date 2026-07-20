<?php

namespace App\Services;

use App\Models\Timesheet;
use App\Models\TimesheetCorrectionRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AdminHodCorrectionRequestQuery
{
    public function requestsFor(User $actor): Builder
    {
        return $this->scopeRequests(TimesheetCorrectionRequest::query(), $actor);
    }

    public function scopeRequests(Builder $query, User $actor): Builder
    {
        abort_unless(in_array($actor->role, ['admin', 'super_admin'], true), 403);

        return $query
            ->where('status', TimesheetCorrectionRequest::STATUS_OPEN)
            ->whereHas('timesheet.user', fn (Builder $userQuery) => $this->scopeEligibleHods($userQuery, $actor));
    }

    public function scopeTimesheets(Builder $query, User $actor): Builder
    {
        abort_unless(in_array($actor->role, ['admin', 'super_admin'], true), 403);

        return $query
            ->whereHas('user', fn (Builder $userQuery) => $this->scopeEligibleHods($userQuery, $actor))
            ->whereHas('correctionRequests', fn (Builder $requestQuery) => $requestQuery->where('status', TimesheetCorrectionRequest::STATUS_OPEN));
    }

    public function countFor(User $actor): int
    {
        return $this->requestsFor($actor)->count();
    }

    private function scopeEligibleHods(Builder $query, User $actor): Builder
    {
        return $query
            ->where('role', 'hod')
            ->when($actor->role === 'admin', fn (Builder $hodQuery) => $hodQuery
                ->whereDoesntHave('approvalExcludedByAdmins', fn (Builder $adminQuery) => $adminQuery->whereKey($actor->id)));
    }
}
