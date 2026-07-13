<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminExclusionService
{
    public function notificationExcluded(User $admin, User|int|null $hod): bool
    {
        $hodId = $hod instanceof User ? $hod->id : $hod;

        if (! $hodId || $admin->role !== 'admin' || ! $admin->is_active) {
            return false;
        }

        return $admin->adminNotificationExcludedHods()->whereKey($hodId)->exists();
    }

    public function approvalExcluded(User $admin, User|int|null $hod): bool
    {
        $hodId = $hod instanceof User ? $hod->id : $hod;

        if (! $hodId || $admin->role !== 'admin' || ! $admin->is_active) {
            return false;
        }

        return $admin->adminApprovalExcludedHods()->whereKey($hodId)->exists();
    }

    public function shouldReceiveHodSubmissionEmail(User $recipient, User $hod): bool
    {
        if ($recipient->role === 'super_admin') {
            return true;
        }

        return ! $this->notificationExcluded($recipient, $hod)
            && ! $this->approvalExcluded($recipient, $hod);
    }

    public function validHods(): Collection
    {
        return User::where('role', 'hod')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function eligibleApproverCountFor(User $hod, ?int $excludingAdminId = null): int
    {
        if ($hod->role !== 'hod' || ! $hod->is_active) {
            return 0;
        }

        return User::where('is_active', true)
            ->where(function ($query) use ($hod, $excludingAdminId) {
                $query->where('role', 'super_admin')
                    ->orWhere(function ($query) use ($hod, $excludingAdminId) {
                        $query->where('role', 'admin')
                            ->when($excludingAdminId, fn ($query) => $query->whereKeyNot($excludingAdminId))
                            ->whereDoesntHave('adminApprovalExcludedHods', fn ($query) => $query->whereKey($hod->id));
                    });
            })
            ->count();
    }

    public function syncForAdmin(User $admin, array $notificationIds, array $approvalIds): array
    {
        if ($admin->role !== 'admin' || ! $admin->is_active) {
            $old = $this->snapshotFor($admin);
            $admin->adminNotificationExcludedHods()->sync([]);
            $admin->adminApprovalExcludedHods()->sync([]);

            return [$old, $this->snapshotFor($admin)];
        }

        $validIds = $this->validHods()->pluck('id')->all();
        $notificationIds = $this->normalizeIds($notificationIds, $validIds);
        $approvalIds = $this->normalizeIds($approvalIds, $validIds);

        foreach (User::whereIn('id', $approvalIds)->get() as $hod) {
            if ($this->eligibleApproverCountFor($hod, $admin->id) < 1) {
                throw ValidationException::withMessages([
                    'admin_approval_exclusion_ids' => 'Approval exclusions must leave at least one eligible Admin or Super Admin reviewer for every selected HOD.',
                ]);
            }
        }

        $old = $this->snapshotFor($admin);
        $admin->adminNotificationExcludedHods()->sync($notificationIds);
        $admin->adminApprovalExcludedHods()->sync($approvalIds);

        return [$old, $this->snapshotFor($admin)];
    }

    public function pruneInvalidForUser(User $user): void
    {
        if ($user->role !== 'admin' || ! $user->is_active) {
            DB::table('admin_notification_exclusions')->where('admin_user_id', $user->id)->delete();
            DB::table('admin_approval_exclusions')->where('admin_user_id', $user->id)->delete();
        }

        if ($user->role !== 'hod' || ! $user->is_active) {
            DB::table('admin_notification_exclusions')->where('hod_user_id', $user->id)->delete();
            DB::table('admin_approval_exclusions')->where('hod_user_id', $user->id)->delete();
        }

        $this->pruneInvalidPairs();
    }

    public function pruneInvalidPairs(): void
    {
        foreach (['admin_notification_exclusions', 'admin_approval_exclusions'] as $table) {
            DB::table($table)
                ->whereIn('admin_user_id', User::where(fn ($query) => $query->where('role', '!=', 'admin')->orWhere('is_active', false))->select('id'))
                ->delete();

            DB::table($table)
                ->whereIn('hod_user_id', User::where(fn ($query) => $query->where('role', '!=', 'hod')->orWhere('is_active', false))->select('id'))
                ->delete();
        }

        DB::table('admin_approval_exclusions')
            ->orderBy('id')
            ->get()
            ->each(function ($row) {
                $hod = User::find($row->hod_user_id);
                if (! $hod || $this->eligibleApproverCountFor($hod, (int) $row->admin_user_id) < 1) {
                    DB::table('admin_approval_exclusions')->where('id', $row->id)->delete();
                }
            });
    }

    public function snapshotFor(User $admin): array
    {
        return [
            'notification_excluded_hod_ids' => $admin->adminNotificationExcludedHods()->pluck('users.id')->map(fn ($id) => (int) $id)->sort()->values()->all(),
            'approval_excluded_hod_ids' => $admin->adminApprovalExcludedHods()->pluck('users.id')->map(fn ($id) => (int) $id)->sort()->values()->all(),
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
