<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HodExclusionService
{
    public function notificationExcluded(User $hod, User|int|null $submitter): bool
    {
        $submitterId = $submitter instanceof User ? $submitter->id : $submitter;

        if (! $submitterId || $hod->role !== 'hod') {
            return false;
        }

        return $hod->hodNotificationExcludedSubmitters()
            ->whereKey($submitterId)
            ->exists();
    }

    public function approvalExcluded(User $hod, User|int|null $submitter): bool
    {
        $submitterId = $submitter instanceof User ? $submitter->id : $submitter;

        if (! $submitterId || $hod->role !== 'hod') {
            return false;
        }

        return $hod->hodApprovalExcludedSubmitters()
            ->whereKey($submitterId)
            ->exists();
    }

    public function shouldReceiveApprovalRequestEmail(User $hod, User $submitter): bool
    {
        return ! $this->notificationExcluded($hod, $submitter)
            && ! $this->approvalExcluded($hod, $submitter);
    }

    public function eligibleApproverCountFor(User $submitter, ?int $excludingHodId = null): int
    {
        if (! $submitter->department_id) {
            return 0;
        }

        $hodIds = DB::table('department_hod')
            ->where('department_id', $submitter->department_id)
            ->pluck('user_id')
            ->push(DB::table('departments')->whereKey($submitter->department_id)->value('hod_id'))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($hodIds->isEmpty()) {
            return 0;
        }

        return User::whereIn('id', $hodIds)
            ->where('role', 'hod')
            ->where('is_active', true)
            ->when($excludingHodId, fn ($query) => $query->whereKeyNot($excludingHodId))
            ->whereDoesntHave('hodApprovalExcludedSubmitters', fn ($query) => $query->whereKey($submitter->id))
            ->count();
    }

    public function validSubmittersForHod(User $hod): Collection
    {
        if ($hod->role !== 'hod') {
            return collect();
        }

        $departmentIds = $this->hodApproverDepartmentIds($hod);

        if ($departmentIds->isEmpty()) {
            return collect();
        }

        return User::whereIn('department_id', $departmentIds)
            ->whereIn('role', ['employee', 'hod'])
            ->where('is_active', true)
            ->whereKeyNot($hod->id)
            ->orderBy('name')
            ->get();
    }

    public function hodApproverDepartmentIds(User $hod): Collection
    {
        if ($hod->role !== 'hod') {
            return collect();
        }

        return DB::table('department_hod')
            ->where('user_id', $hod->id)
            ->pluck('department_id')
            ->merge(DB::table('departments')->where('hod_id', $hod->id)->pluck('id'))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    public function syncForHod(User $hod, array $notificationIds, array $approvalIds): array
    {
        if ($hod->role !== 'hod') {
            $old = $this->snapshotFor($hod);
            $hod->hodNotificationExcludedSubmitters()->sync([]);
            $hod->hodApprovalExcludedSubmitters()->sync([]);

            return [$old, $this->snapshotFor($hod)];
        }

        $validIds = $this->validSubmittersForHod($hod)->pluck('id')->all();
        $notificationIds = $this->normalizeIds($notificationIds, $validIds);
        $approvalIds = $this->normalizeIds($approvalIds, $validIds);

        $submitters = User::whereIn('id', $approvalIds)->get(['id', 'department_id']);
        foreach ($submitters as $submitter) {
            if ($this->eligibleApproverCountFor($submitter, $hod->id) < 1) {
                throw ValidationException::withMessages([
                    'hod_approval_exclusion_ids' => 'Approval exclusions must leave at least one eligible HOD approver for every selected user.',
                ]);
            }
        }

        $old = $this->snapshotFor($hod);
        $hod->hodNotificationExcludedSubmitters()->sync($notificationIds);
        $hod->hodApprovalExcludedSubmitters()->sync($approvalIds);

        return [$old, $this->snapshotFor($hod)];
    }

    public function pruneInvalidForUser(User $user): void
    {
        if ($user->role !== 'hod') {
            DB::table('hod_notification_exclusions')->where('hod_user_id', $user->id)->delete();
            DB::table('hod_approval_exclusions')->where('hod_user_id', $user->id)->delete();
        }

        if (! in_array($user->role, ['employee', 'hod'], true)) {
            DB::table('hod_notification_exclusions')->where('employee_user_id', $user->id)->delete();
            DB::table('hod_approval_exclusions')->where('employee_user_id', $user->id)->delete();
        }

        $this->pruneInvalidPairs();
    }

    public function pruneInvalidPairs(): void
    {
        foreach (['hod_notification_exclusions', 'hod_approval_exclusions'] as $table) {
            $invalidHodIds = DB::table($table)
                ->join('users as hods', $table.'.hod_user_id', '=', 'hods.id')
                ->where(function ($query) {
                    $query->where('hods.role', '!=', 'hod')
                        ->orWhere('hods.is_active', false);
                })
                ->pluck($table.'.id');

            if ($invalidHodIds->isNotEmpty()) {
                DB::table($table)->whereIn('id', $invalidHodIds)->delete();
            }

            $invalidSubmitterIds = DB::table($table)
                ->join('users as submitters', $table.'.employee_user_id', '=', 'submitters.id')
                ->where(function ($query) {
                    $query->whereNotIn('submitters.role', ['employee', 'hod'])
                        ->orWhere('submitters.is_active', false)
                        ->orWhereNull('submitters.department_id');
                })
                ->pluck($table.'.id');

            if ($invalidSubmitterIds->isNotEmpty()) {
                DB::table($table)->whereIn('id', $invalidSubmitterIds)->delete();
            }

            $rows = DB::table($table)
                ->join('users as submitters', $table.'.employee_user_id', '=', 'submitters.id')
                ->select($table.'.id', $table.'.hod_user_id', 'submitters.department_id')
                ->get();

            foreach ($rows as $row) {
                $isManaged = DB::table('department_hod')
                    ->where('department_id', $row->department_id)
                    ->where('user_id', $row->hod_user_id)
                    ->exists()
                    || DB::table('departments')
                        ->whereKey($row->department_id)
                        ->where('hod_id', $row->hod_user_id)
                        ->exists();

                if (! $isManaged) {
                    DB::table($table)->where('id', $row->id)->delete();
                }
            }
        }

        DB::table('hod_approval_exclusions')
            ->orderBy('id')
            ->get()
            ->each(function ($row) {
                $submitter = User::find($row->employee_user_id);
                if (! $submitter || $this->eligibleApproverCountFor($submitter, (int) $row->hod_user_id) < 1) {
                    DB::table('hod_approval_exclusions')->where('id', $row->id)->delete();
                }
            });
    }

    public function snapshotFor(User $hod): array
    {
        return [
            'notification_excluded_user_ids' => $hod->hodNotificationExcludedSubmitters()->pluck('users.id')->map(fn ($id) => (int) $id)->sort()->values()->all(),
            'approval_excluded_user_ids' => $hod->hodApprovalExcludedSubmitters()->pluck('users.id')->map(fn ($id) => (int) $id)->sort()->values()->all(),
        ];
    }

    private function normalizeIds(array $ids, array $validIds): array
    {
        $valid = collect($validIds)->map(fn ($id) => (int) $id)->all();

        return collect($ids)
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->intersect($valid)
            ->unique()
            ->values()
            ->all();
    }
}
