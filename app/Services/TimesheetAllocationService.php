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

    public const EXCEEDED_MESSAGE = 'The allocated hours for this department or Manpower Category have been exceeded. Please contact the project administrator.';

    public const MISSING_CATEGORY_MESSAGE = 'Your project assignment has no Manpower Category for this controlled discipline. Please contact the project administrator.';

    public const INVALID_CATEGORY_MESSAGE = 'Your assigned Manpower Category is not available for this project discipline. Please contact the project administrator.';

    public const LEGACY_SETUP_MESSAGE = 'The Manpower Category setup for this project discipline needs administrator review before time can be submitted.';

    /**
     * Validate an all-or-nothing submission and return allocation snapshots keyed by entry index.
     */
    public function validateSubmission(User $user, array $entries, ?Timesheet $excludingTimesheet = null): array
    {
        $canonicalCategories = array_keys(config('manpower_categories.labels'));
        $projectIds = collect($entries)->pluck('project_id')->filter()->map(fn ($id) => (int) $id)->unique();
        $assignedCategories = DB::table('project_user')
            ->where('user_id', $user->id)
            ->whereIn('project_id', $projectIds)
            ->pluck('manpower_category', 'project_id');
        $requested = collect($entries)
            ->map(function (array $entry, int $index) use ($user, $assignedCategories) {
                $hours = (float) ($entry['regular_hours'] ?? 0) + (float) ($entry['overtime_hours'] ?? 0);
                $projectId = (int) ($entry['project_id'] ?? 0);
                $departmentId = (int) (($entry['department_id'] ?? null) ?: $user->department_id);
                $manpowerCategory = $assignedCategories->get($projectId);

                return compact('index', 'hours', 'projectId', 'departmentId', 'manpowerCategory');
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
            ->with('manpowerCategoryAllocations')
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

        $allocatedProjectIds = ProjectDepartmentAllocation::query()
            ->whereIn('project_id', $pairs->pluck('project_id')->unique())
            ->pluck('project_id')
            ->map(fn ($id) => (int) $id)
            ->unique();

        $snapshots = [];
        $errors = [];

        foreach ($requested as $key => $rows) {
            $allocation = $allocations->get($key);
            if (! $allocation) {
                if (! $allocatedProjectIds->contains((int) $rows->first()['projectId'])) {
                    foreach ($rows as $row) {
                        $snapshots[$row['index']] = ['manpower_category_snapshot' => null, 'allocation_bucket_snapshot' => null];
                    }

                    continue;
                }

                $this->addErrors($errors, $rows, 'department_id', self::EXCEEDED_MESSAGE);

                continue;
            }

            $requestedHours = (float) $rows->sum('hours');
            $usageQuery = $this->usageQuery($allocation->project_id, $allocation->department_id, $excludingTimesheet?->id);
            $overallConsumed = (float) (clone $usageQuery)->sum(DB::raw('entries.regular_hours + entries.overtime_hours'));

            if ($overallConsumed + $requestedHours > (float) $allocation->allocated_hours + 0.0001) {
                $this->addErrors($errors, $rows, 'department_id', self::EXCEEDED_MESSAGE);

                continue;
            }

            $categoryAllocations = $allocation->manpowerCategoryAllocations;
            if ($categoryAllocations->isEmpty()) {
                foreach ($rows as $row) {
                    $snapshots[$row['index']] = ['manpower_category_snapshot' => null, 'allocation_bucket_snapshot' => null];
                }

                continue;
            }

            if ($categoryAllocations->contains(fn ($item) => ! in_array($item->manpower_category, $canonicalCategories, true))
                || $categoryAllocations->whereIn('manpower_category', $canonicalCategories)->count() !== count($canonicalCategories)) {
                $this->addErrors($errors, $rows, 'department_id', self::LEGACY_SETUP_MESSAGE);

                continue;
            }

            $validRows = collect();
            foreach ($rows as $row) {
                if (! $row['manpowerCategory']) {
                    $errors['entries.'.$row['index'].'.department_id'] = self::MISSING_CATEGORY_MESSAGE;

                    continue;
                }

                $categoryAllocation = $categoryAllocations->firstWhere('manpower_category', $row['manpowerCategory']);
                if (! $categoryAllocation || ($categoryAllocation->allocated_hours !== null && (float) $categoryAllocation->allocated_hours === 0.0)) {
                    $errors['entries.'.$row['index'].'.department_id'] = self::INVALID_CATEGORY_MESSAGE;

                    continue;
                }

                $validRows->push($row + [
                    'bucket' => $categoryAllocation->allocated_hours === null ? self::BUCKET_SHARED : self::BUCKET_RESERVED,
                ]);
            }

            $sharedRows = $validRows->where('bucket', self::BUCKET_SHARED);
            if ($sharedRows->isNotEmpty()) {
                $reservedTotal = (float) $categoryAllocations->sum(fn ($row) => $row->allocated_hours === null ? 0 : (float) $row->allocated_hours);
                $sharedLimit = (float) $allocation->allocated_hours - $reservedTotal;
                $sharedConsumed = (float) (clone $usageQuery)
                    ->where(function ($query) use ($canonicalCategories) {
                        $query->where('entries.allocation_bucket_snapshot', self::BUCKET_SHARED)
                            ->orWhereNull('entries.allocation_bucket_snapshot')
                            ->orWhereNull('entries.manpower_category_snapshot')
                            ->orWhereNotIn('entries.manpower_category_snapshot', $canonicalCategories);
                    })
                    ->sum(DB::raw('entries.regular_hours + entries.overtime_hours'));

                if ((float) $sharedRows->sum('hours') > $sharedLimit - $sharedConsumed + 0.0001) {
                    $this->addErrors($errors, $sharedRows, 'department_id', self::EXCEEDED_MESSAGE);
                    $validRows = $validRows->reject(fn ($row) => $row['bucket'] === self::BUCKET_SHARED);
                }
            }

            foreach ($validRows->where('bucket', self::BUCKET_RESERVED)->groupBy('manpowerCategory') as $category => $categoryRows) {
                $categoryAllocation = $categoryAllocations->firstWhere('manpower_category', $category);
                $reservedConsumed = (float) (clone $usageQuery)
                    ->where('entries.allocation_bucket_snapshot', self::BUCKET_RESERVED)
                    ->where('entries.manpower_category_snapshot', $category)
                    ->sum(DB::raw('entries.regular_hours + entries.overtime_hours'));

                if ((float) $categoryRows->sum('hours') > (float) $categoryAllocation->allocated_hours - $reservedConsumed + 0.0001) {
                    $this->addErrors($errors, $categoryRows, 'department_id', self::EXCEEDED_MESSAGE);
                    $validRows = $validRows->reject(fn ($row) => $row['bucket'] === self::BUCKET_RESERVED && $row['manpowerCategory'] === $category);
                }
            }

            foreach ($validRows as $row) {
                $snapshots[$row['index']] = [
                    'manpower_category_snapshot' => $row['manpowerCategory'],
                    'allocation_bucket_snapshot' => $row['bucket'],
                ];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $snapshots;
    }

    /**
     * Resolve non-consuming draft snapshots from the current project assignment.
     * A trusted existing snapshot may be retained while an assignment still needs admin review.
     */
    public function snapshotsForDraft(User $user, array $entries, array $existingSnapshots = []): array
    {
        $rows = collect($entries)->map(function (array $entry, int $index) use ($user) {
            return [
                'index' => $index,
                'projectId' => (int) ($entry['project_id'] ?? 0),
                'departmentId' => (int) (($entry['department_id'] ?? null) ?: $user->department_id),
            ];
        });
        $projectIds = $rows->pluck('projectId')->filter()->unique();
        $assignedCategories = DB::table('project_user')
            ->where('user_id', $user->id)
            ->whereIn('project_id', $projectIds)
            ->pluck('manpower_category', 'project_id');
        $pairs = $rows->filter(fn ($row) => $row['projectId'] > 0 && $row['departmentId'] > 0);

        if ($pairs->isEmpty()) {
            return $rows->mapWithKeys(fn ($row) => [$row['index'] => [
                'manpower_category_snapshot' => null,
                'allocation_bucket_snapshot' => null,
            ]])->all();
        }

        $allocations = ProjectDepartmentAllocation::query()
            ->with('manpowerCategoryAllocations')
            ->where(function ($query) use ($pairs) {
                foreach ($pairs as $pair) {
                    $query->orWhere(fn ($query) => $query
                        ->where('project_id', $pair['projectId'])
                        ->where('department_id', $pair['departmentId']));
                }
            })
            ->get()
            ->keyBy(fn ($allocation) => $allocation->project_id.'|'.$allocation->department_id);

        return $rows->mapWithKeys(function ($row) use ($allocations, $assignedCategories, $existingSnapshots) {
            $allocation = $allocations->get($row['projectId'].'|'.$row['departmentId']);
            $snapshot = null;

            if ($allocation?->usesManpowerCategories()) {
                $assignedCategory = $assignedCategories->get($row['projectId']);
                $snapshot = $allocation->allowsManpowerCategory($assignedCategory)
                    ? $assignedCategory
                    : ($existingSnapshots[$row['index']] ?? null);
            }

            return [$row['index'] => [
                'manpower_category_snapshot' => $snapshot,
                'allocation_bucket_snapshot' => null,
            ]];
        })->all();
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

    private function addErrors(array &$errors, $rows, string $field, string $message): void
    {
        foreach ($rows as $row) {
            $errors['entries.'.$row['index'].'.'.$field] = $message;
        }
    }
}
