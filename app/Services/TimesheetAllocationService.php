<?php

namespace App\Services;

use App\Models\ProjectDepartmentAllocation;
use App\Models\Timesheet;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TimesheetAllocationService
{
    public const BUCKET_SHARED = 'shared';

    public const BUCKET_RESERVED = 'reserved';

    public const EXCEEDED_MESSAGE = 'The allocated hours for this department have been exceeded. Please contact the project administrator.';

    public const MISSING_LEVEL_MESSAGE = 'Your Job Level is not configured. Contact your administrator before submitting time to this project.';

    /**
     * Validate an all-or-nothing submission and return allocation snapshots keyed by entry index.
     * Call this inside the same database transaction that persists the submitted entries.
     */
    public function validateSubmission(User $user, array $entries, ?Timesheet $excludingTimesheet = null, array $jobLevelOverrides = []): array
    {
        $requested = collect($entries)
            ->map(function (array $entry, int $index) use ($user, $jobLevelOverrides) {
                $hours = (float) ($entry['regular_hours'] ?? 0) + (float) ($entry['overtime_hours'] ?? 0);
                $projectId = (int) ($entry['project_id'] ?? 0);
                $departmentId = (int) (($entry['department_id'] ?? null) ?: $user->department_id);

                $jobLevel = $jobLevelOverrides[$index] ?? $user->job_level;

                return compact('index', 'hours', 'projectId', 'departmentId', 'jobLevel');
            })
            ->filter(fn (array $row) => $row['hours'] > 0 && $row['projectId'] > 0 && $row['departmentId'] > 0)
            ->groupBy(fn (array $row) => $row['projectId'].'|'.$row['departmentId']);

        if ($requested->isEmpty()) {
            return [];
        }

        $pairs = $requested->map(function ($rows) {
            $row = $rows->first();

            return ['project_id' => $row['projectId'], 'department_id' => $row['departmentId']];
        })->values();

        $allocations = ProjectDepartmentAllocation::query()
            ->with('jobLevelAllocations')
            ->where(function ($query) use ($pairs) {
                foreach ($pairs as $pair) {
                    $query->orWhere(fn ($query) => $query
                        ->where('project_id', $pair['project_id'])
                        ->where('department_id', $pair['department_id']));
                }
            })
            ->orderBy('project_id')
            ->orderBy('department_id')
            ->lockForUpdate()
            ->get()
            ->keyBy(fn ($allocation) => $allocation->project_id.'|'.$allocation->department_id);
        $controlledProjectIds = ProjectDepartmentAllocation::query()
            ->whereIn('project_id', $pairs->pluck('project_id')->unique())
            ->pluck('project_id')
            ->map(fn ($id) => (int) $id)
            ->unique();

        $snapshots = [];
        $errors = [];

        foreach ($requested as $key => $rows) {
            $allocation = $allocations->get($key);
            if (! $allocation) {
                if (! $controlledProjectIds->contains((int) $rows->first()['projectId'])) {
                    foreach ($rows as $row) {
                        $snapshots[$row['index']] = ['job_level_snapshot' => $row['jobLevel'], 'allocation_bucket_snapshot' => null];
                    }

                    continue;
                }
                $this->addErrors($errors, $rows, self::EXCEEDED_MESSAGE);

                continue;
            }

            $requestedHours = (float) $rows->sum('hours');
            $usageQuery = $this->usageQuery($allocation->project_id, $allocation->department_id, $excludingTimesheet?->id);
            $overallConsumed = (float) (clone $usageQuery)->sum(DB::raw('entries.regular_hours + entries.overtime_hours'));

            if ($overallConsumed + $requestedHours > (float) $allocation->allocated_hours + 0.0001) {
                $this->addErrors($errors, $rows, self::EXCEEDED_MESSAGE);

                continue;
            }

            if ($allocation->jobLevelAllocations->isEmpty()) {
                foreach ($rows as $row) {
                    $snapshots[$row['index']] = ['job_level_snapshot' => $row['jobLevel'], 'allocation_bucket_snapshot' => null];
                }

                continue;
            }

            $jobLevels = $rows->pluck('jobLevel')->unique()->values();
            if ($jobLevels->count() !== 1 || ! $jobLevels->first()) {
                $this->addErrors($errors, $rows, self::MISSING_LEVEL_MESSAGE);

                continue;
            }

            $jobLevel = $jobLevels->first();

            $levelAllocation = $allocation->jobLevelAllocations->firstWhere('job_level', $jobLevel);
            if (! $levelAllocation || (float) $levelAllocation->allocated_hours === 0.0 && $levelAllocation->allocated_hours !== null) {
                $this->addErrors($errors, $rows, self::EXCEEDED_MESSAGE);

                continue;
            }

            if ($levelAllocation->allocated_hours === null) {
                $reservedTotal = (float) $allocation->jobLevelAllocations->sum(fn ($row) => $row->allocated_hours === null ? 0 : (float) $row->allocated_hours);
                $sharedLimit = (float) $allocation->allocated_hours - $reservedTotal;
                $sharedConsumed = (float) (clone $usageQuery)
                    ->where(fn ($query) => $query->where('entries.allocation_bucket_snapshot', self::BUCKET_SHARED)
                        ->orWhereNull('entries.allocation_bucket_snapshot'))
                    ->sum(DB::raw('entries.regular_hours + entries.overtime_hours'));
                $bucket = self::BUCKET_SHARED;
                $available = $sharedLimit - $sharedConsumed;
            } else {
                $reservedConsumed = (float) (clone $usageQuery)
                    ->where('entries.allocation_bucket_snapshot', self::BUCKET_RESERVED)
                    ->where('entries.job_level_snapshot', $jobLevel)
                    ->sum(DB::raw('entries.regular_hours + entries.overtime_hours'));
                $bucket = self::BUCKET_RESERVED;
                $available = (float) $levelAllocation->allocated_hours - $reservedConsumed;
            }

            if ($requestedHours > $available + 0.0001) {
                $this->addErrors($errors, $rows, self::EXCEEDED_MESSAGE);

                continue;
            }

            foreach ($rows as $row) {
                $snapshots[$row['index']] = ['job_level_snapshot' => $jobLevel, 'allocation_bucket_snapshot' => $bucket];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $snapshots;
    }

    private function usageQuery(int $projectId, int $departmentId, ?int $excludingTimesheetId = null)
    {
        return DB::table('timesheet_entries as entries')
            ->join('timesheets', 'timesheets.id', '=', 'entries.timesheet_id')
            ->where('entries.project_id', $projectId)
            ->where('entries.department_id', $departmentId)
            ->whereIn('timesheets.status', [Timesheet::STATUS_SUBMITTED, Timesheet::STATUS_APPROVED])
            ->when($excludingTimesheetId, fn ($query) => $query->where('timesheets.id', '!=', $excludingTimesheetId));
    }

    private function addErrors(array &$errors, $rows, string $message): void
    {
        foreach ($rows as $row) {
            $errors['entries.'.$row['index'].'.department_id'] = $message;
        }
    }
}
