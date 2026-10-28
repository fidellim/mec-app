<?php

namespace App\Services;

use App\Models\ProjectDepartmentAllocation;
use App\Models\Timesheet;
use App\Models\TimesheetEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TimesheetDepartmentTransferService
{
    public function ensureEligible(TimesheetEntry $entry): void
    {
        if (! in_array($entry->timesheet->status, [Timesheet::STATUS_SUBMITTED, Timesheet::STATUS_APPROVED], true)) {
            $this->fail('Only submitted or approved entries can be transferred.');
        }
        if ($entry->correctionRequestEntries()->whereHas('request', fn ($q) => $q->where('status', 'open'))->exists()) {
            $this->fail('This entry has an open correction request. Resolve or withdraw it before transferring.');
        }
        if ($this->hours($entry) <= 0) {
            $this->fail('This entry has no hours to transfer.');
        }
    }

    public function preview(TimesheetEntry $entry, int $destinationId, ?string $classification, array $releasingIds = []): array
    {
        $this->ensureEligible($entry);
        $releasingIds = $releasingIds ?: [$entry->id];
        $sameDepartment = (int) $entry->department_id === $destinationId;
        if ($sameDepartment && (blank($classification) || ($classification === $entry->manpower_category_snapshot
            && in_array($entry->allocation_bucket_snapshot, ['shared', 'reserved'], true)))) {
            $this->fail('Entry #'.$entry->id.': choose a different category or department, or remove this unchanged entry from the selection.');
        }
        $destination = ProjectDepartmentAllocation::with(['department', 'manpowerCategoryAllocations'])
            ->where('project_id', $entry->project_id)->where('department_id', $destinationId)->first();
        if (! $destination || ! $destination->department?->is_active) {
            $this->fail('Allocate hours to an active destination department before transferring.');
        }
        $source = ProjectDepartmentAllocation::with(['department', 'manpowerCategoryAllocations'])->where('project_id', $entry->project_id)->where('department_id', $entry->department_id)->first();
        $sourceRemaining = (float) ($source?->allocated_hours ?? 0) - $this->usage($entry->project_id, $entry->department_id)->sum(DB::raw('entries.regular_hours + entries.overtime_hours'));
        $destinationRemaining = (float) $destination->allocated_hours - $this->usage($entry->project_id, $destinationId)->sum(DB::raw('entries.regular_hours + entries.overtime_hours'));
        $categories = array_keys(config('manpower_categories.labels'));
        $category = filled($classification) ? $classification : $entry->manpower_category_snapshot;
        $knownCategory = in_array($category, $categories, true);
        if (filled($classification) && ! $knownCategory) {
            $this->fail('Choose a valid Manpower Category.');
        }
        $bucket = $knownCategory ? TimesheetAllocationService::BUCKET_SHARED : null;
        $targetPool = null;
        $sourcePool = null;
        $blocker = null;
        if ($source?->usesManpowerCategories()) {
            $oldCategory = $entry->manpower_category_snapshot;
            $oldBucket = $entry->allocation_bucket_snapshot;
            if ($oldBucket === 'shared' || $oldBucket === null || ! in_array($oldCategory, $categories, true)) {
                $sourcePool = $this->poolStats($source, 'shared', null, $releasingIds);
            } elseif ($oldBucket === 'reserved') {
                $sourcePool = $this->poolStats($source, 'reserved', $oldCategory, $releasingIds);
            }
        }
        if ($destination->usesManpowerCategories()) {
            if (! $destination->hasCurrentManpowerCategoryConfiguration()) {
                $blocker = 'The destination Manpower Category setup needs administrator review.';
            } elseif (! $knownCategory) {
                $blocker = 'Select a Manpower Category for this unclassified entry.';
            } elseif (! $destination->allowsManpowerCategory($category)) {
                $blocker = 'This Manpower Category is not allowed in the destination department.';
            } else {
                $setting = $destination->manpowerCategoryAllocations->firstWhere('manpower_category', $category);
                $bucket = $setting->allocated_hours === null ? 'shared' : 'reserved';
                $targetPool = $this->poolStats($destination, $bucket, $category, $releasingIds);
            }
        }
        $releasedHours = (float) $this->usage($entry->project_id, $destinationId)->whereIn('entries.id', $releasingIds)->sum(DB::raw('entries.regular_hours + entries.overtime_hours'));
        $destinationAvailable = $destinationRemaining + $releasedHours;
        $hours = $this->hours($entry);
        $classificationBlocker = $blocker;
        $shortfall = max(0,
            $hours > $releasedHours + 0.0001 ? $hours - $destinationAvailable : 0,
            $targetPool && $hours > $targetPool['released'] + 0.0001 ? $hours - $targetPool['available'] : 0);
        $pools = collect([$sourcePool, $targetPool])->filter()->keyBy('key')->map(function ($pool) use ($targetPool, $hours) {
            return $pool + ['after' => $pool['available'] - ($pool['key'] === ($targetPool['key'] ?? null) ? $hours : 0)];
        })->values()->all();
        $departments = [
            ['id' => $entry->department_id, 'label' => $entry->department?->name ?? 'Unclassified department', 'before' => (float) $sourceRemaining, 'after' => (float) $sourceRemaining + ($sameDepartment ? 0 : $hours)],
        ];
        if (! $sameDepartment) {
            $departments[] = ['id' => $destinationId, 'label' => $destination->department->name, 'before' => $destinationRemaining, 'after' => $destinationAvailable - $hours];
        }
        if ($blocker === null && $shortfall > 0.0001) {
            $blocker = 'Insufficient allocation. Increase available hours by at least '.number_format($shortfall, 2).' before transferring.';
        }
        $preview = [
            'entry' => $this->entrySummary($entry),
            'destination_id' => $destinationId,
            'destination_name' => $destination->department->name,
            'controlled' => $destination->usesManpowerCategories(),
            'source_name' => $entry->department?->name ?? 'Unclassified department',
            'hours' => $hours,
            'source_before' => (float) $sourceRemaining,
            'source_after' => (float) $sourceRemaining + ($sameDepartment ? 0 : $hours),
            'destination_before' => $destinationRemaining,
            'destination_after' => $destinationAvailable - $hours,
            'destination_available' => $destinationAvailable, 'destination_released' => $releasedHours,
            'departments' => $departments, 'pools' => $pools,
            'target_pool' => $targetPool, 'source_pool' => $sourcePool,
            'category_only' => $sameDepartment,
            'category_before' => $this->categoryLabel($entry->manpower_category_snapshot),
            'category_after' => $this->categoryLabel($category),
            'pool_label' => $targetPool['label'] ?? null,
            'pool_before' => $targetPool['before'] ?? null,
            'pool_after' => $targetPool ? $targetPool['available'] - $hours : null,
            'shortfall' => $shortfall,
            'blocker' => $blocker,
            'classification_blocker' => $classificationBlocker,
            'can_transfer' => $blocker === null,
            'manpower_category_snapshot' => $category,
            'allocation_bucket_snapshot' => $bucket,
        ];
        $preview['version'] = hash_hmac('sha256', json_encode([
            $entry->getAttributes(), $entry->timesheet->status, $preview,
        ], JSON_THROW_ON_ERROR), config('app.key'));

        return $preview;
    }

    public function entrySummary(TimesheetEntry $entry): array
    {
        return [
            'id' => $entry->id, 'employee' => $entry->timesheet->user->name,
            'date' => $entry->work_date->toDateString(), 'description' => $entry->description,
            'regular' => $entry->regular_hours, 'overtime' => $entry->overtime_hours,
            'status' => $entry->timesheet->status, 'department' => $entry->department?->name ?? 'Unclassified department',
            'category' => $entry->manpower_category_snapshot, 'department_id' => $entry->department_id,
            'category_known' => array_key_exists($entry->manpower_category_snapshot ?? '', config('manpower_categories.labels')),
        ];
    }

    public function previewBatch(Collection $entries, ?int $destinationId, array $classifications = [], bool $categoryOnly = false): array
    {
        if ($categoryOnly) {
            $destinationId = null;
        }
        $entries = $entries->sortBy('id')->values();
        if ($entries->isEmpty() || $entries->count() > 50 || $entries->pluck('timesheet_id')->unique()->count() !== 1
            || $entries->pluck('project_id')->unique()->count() !== 1) {
            $this->fail('Select between 1 and 50 entries from one employee week and one project.');
        }
        if (array_diff(array_keys($classifications), $entries->pluck('id')->all()) !== []) {
            $this->fail('Category selections must belong to the selected entries.');
        }
        $releasingIds = $entries->pluck('id')->all();
        $items = $entries->map(function ($entry) use ($destinationId, $classifications, $categoryOnly, $releasingIds) {
            $preview = $this->preview($entry, $categoryOnly ? (int) $entry->department_id : (int) $destinationId, $classifications[$entry->id] ?? null, $releasingIds);
            unset($preview['version']);

            return $preview;
        });
        $hours = (float) $items->sum('hours');
        $departments = collect();
        foreach ($entries as $entry) {
            $item = $items->firstWhere('entry.id', $entry->id);
            $key = $entry->department_id ?? 'unclassified';
            if (! $departments->has($key)) {
                $departments->put($key, ['id' => $entry->department_id, 'label' => $item['source_name'], 'before' => $item['source_before'], 'released' => 0.0, 'incoming' => 0.0]);
            }
            $sourceRow = $departments->get($key);
            $sourceRow['released'] += $item['hours'];
            $departments->put($key, $sourceRow);
            $targetKey = $item['destination_id'];
            if (! $departments->has($targetKey)) {
                $departments->put($targetKey, ['id' => $targetKey, 'label' => $item['destination_name'], 'before' => $item['destination_before'], 'released' => 0.0, 'incoming' => 0.0]);
            }
            $targetRow = $departments->get($targetKey);
            $targetRow['incoming'] += $item['hours'];
            $departments->put($targetKey, $targetRow);
        }
        $departments = $departments->map(fn ($row) => $row + ['after' => $row['before'] + $row['released'] - $row['incoming']])->values();
        $sources = $departments->filter(fn ($row) => $categoryOnly || $row['id'] !== $destinationId)->values();
        $pools = $items->flatMap(fn ($item) => array_values(array_filter([$item['source_pool'], $item['target_pool']])))->keyBy('key')->map(function ($pool) use ($items) {
            $incoming = (float) $items->filter(fn ($item) => ($item['target_pool']['key'] ?? null) === $pool['key'])->sum('hours');

            return $pool + ['incoming' => $incoming, 'after' => $pool['available'] - $incoming];
        })->values();
        $blockers = $items->filter(fn ($item) => $item['classification_blocker'] !== null)
            ->map(fn ($item) => 'Entry #'.$item['entry']['id'].': '.$item['classification_blocker'])->values();
        foreach ($departments as $department) {
            if ($department['incoming'] > $department['released'] + 0.0001 && $department['after'] < -0.0001) {
                $blockers->push($department['label'].' needs '.number_format(-$department['after'], 2).' more available hours for the whole selection.');
            }
        }
        foreach ($pools as $pool) {
            if ($pool['incoming'] > $pool['released'] + 0.0001 && $pool['after'] < -0.0001) {
                $blockers->push($pool['label'].' needs '.number_format(-$pool['after'], 2).' more available hours.');
            }
        }
        $destinationBalance = $departments->firstWhere('id', $destinationId);
        $preview = [
            'entries' => $items->all(), 'count' => $items->count(), 'hours' => $hours,
            'regular' => (float) $entries->sum('regular_hours'), 'overtime' => (float) $entries->sum('overtime_hours'),
            'destination_id' => $categoryOnly ? null : $destinationId, 'destination_name' => $categoryOnly ? 'Current departments' : $items->first()['destination_name'],
            'controlled' => $items->contains('controlled', true), 'sources' => $sources->all(), 'pools' => $pools->all(), 'departments' => $departments->all(), 'category_only' => $categoryOnly,
            'destination_before' => $destinationBalance['before'] ?? null, 'destination_after' => $destinationBalance['after'] ?? null,
            'can_transfer' => $blockers->isEmpty(), 'blockers' => $blockers->all(),
        ];
        $preview['version'] = hash_hmac('sha256', json_encode([
            $entries->sortBy('id')->values()->map(fn ($entry) => [$entry->getAttributes(), $entry->timesheet->status])->all(), $preview,
        ], JSON_THROW_ON_ERROR), config('app.key'));

        return $preview;
    }

    public function transferBatch(int $projectId, Timesheet $timesheet, array $data): array
    {
        return DB::transaction(function () use ($projectId, $timesheet, $data) {
            $lockedSheet = Timesheet::whereKey($timesheet->id)->lockForUpdate()->firstOrFail();
            $entries = TimesheetEntry::where('project_id', $projectId)->where('timesheet_id', $lockedSheet->id)
                ->whereIn('id', $data['entry_ids'])->orderBy('id')->lockForUpdate()->get();
            if ($entries->count() !== count($data['entry_ids'])) {
                $this->fail('One or more selected entries were removed or belong to another employee week or project. Reload the selection.');
            }
            $entries->each(fn ($entry) => $entry->setRelation('timesheet', $lockedSheet));
            ProjectDepartmentAllocation::where('project_id', $projectId)
                ->whereIn('department_id', $entries->pluck('department_id')->push($data['department_id'] ?? null)->filter()->unique())
                ->orderBy('department_id')->lockForUpdate()->get();
            $preview = $this->previewBatch($entries, isset($data['department_id']) ? (int) $data['department_id'] : null, $data['classifications'] ?? [], (bool) ($data['category_only'] ?? false));
            if (! hash_equals($preview['version'], $data['version'])) {
                $this->fail('The selection or allocation usage changed. Refresh the preview before transferring.');
            }
            if (! $preview['can_transfer']) {
                $this->fail(implode(' ', $preview['blockers']));
            }

            $batchId = (string) Str::uuid();
            $items = collect($preview['entries'])->keyBy('entry.id');
            foreach ($entries as $entry) {
                $item = $items->get($entry->id);
                $old = $entry->only(['project_id', 'timesheet_id', 'department_id', 'manpower_category_snapshot', 'allocation_bucket_snapshot', 'regular_hours', 'overtime_hours', 'work_date']);
                $entry->update([
                    'department_id' => $item['destination_id'],
                    'manpower_category_snapshot' => $item['manpower_category_snapshot'],
                    'allocation_bucket_snapshot' => $item['allocation_bucket_snapshot'],
                ]);
                app(AuditLogService::class)->record($old['department_id'] === $item['destination_id'] ? 'timesheet_entry_manpower_category_corrected' : 'timesheet_entry_department_transferred', $entry,
                    $old + ['department_name' => $item['source_name'], 'category_label' => $item['category_before']],
                    $entry->only(array_keys($old)) + [
                        'department_name' => $item['destination_name'], 'category_label' => $item['category_after'], 'reason' => trim($data['reason']),
                        'batch_id' => $batchId, 'batch_count' => $preview['count'],
                    ]);
            }

            return ['batch_id' => $batchId, 'count' => $preview['count'], 'hours' => $preview['hours']];
        }, 3);
    }

    public function transfer(TimesheetEntry $entry, array $data): TimesheetEntry
    {
        return DB::transaction(function () use ($entry, $data) {
            // Match workflow lock order; allocation rows also serialize with submissions and allocation edits.
            $timesheet = Timesheet::whereKey($entry->timesheet_id)->lockForUpdate()->firstOrFail();
            $locked = TimesheetEntry::whereKey($entry->id)->lockForUpdate()->firstOrFail();
            $locked->setRelation('timesheet', $timesheet);
            ProjectDepartmentAllocation::where('project_id', $locked->project_id)
                ->whereIn('department_id', array_filter([$locked->department_id, $data['department_id'] ?? null]))
                ->orderBy('department_id')->lockForUpdate()->get();
            $preview = $this->preview($locked, ! empty($data['category_only']) ? (int) $locked->department_id : (int) $data['department_id'], $data['manpower_category'] ?? null);
            if (! hash_equals($preview['version'], $data['version'])) {
                $this->fail('The entry or allocation usage changed. Refresh the preview and review the new balances.');
            }
            if (! $preview['can_transfer']) {
                $this->fail($preview['blocker']);
            }
            $old = $locked->only(['project_id', 'timesheet_id', 'department_id', 'manpower_category_snapshot', 'allocation_bucket_snapshot', 'regular_hours', 'overtime_hours', 'work_date']);
            $locked->update([
                'department_id' => $preview['destination_id'],
                'manpower_category_snapshot' => $preview['manpower_category_snapshot'],
                'allocation_bucket_snapshot' => $preview['allocation_bucket_snapshot'],
            ]);
            app(AuditLogService::class)->record($preview['category_only'] ? 'timesheet_entry_manpower_category_corrected' : 'timesheet_entry_department_transferred', $locked,
                $old + ['department_name' => $preview['source_name'], 'category_label' => $preview['category_before']],
                $locked->only(array_keys($old)) + [
                    'department_name' => $preview['destination_name'],
                    'category_label' => $preview['category_after'],
                    'reason' => trim($data['reason']),
                    'preview' => $preview,
                ]);

            return $locked;
        }, 3);
    }

    private function categoryLabel(?string $category): string
    {
        return config('manpower_categories.labels.'.$category, 'Legacy / unclassified');
    }

    private function poolStats(ProjectDepartmentAllocation $allocation, string $bucket, ?string $category, array $releasingIds): array
    {
        $query = $this->usage($allocation->project_id, $allocation->department_id);
        if ($bucket === 'shared') {
            $limit = (float) $allocation->allocated_hours - (float) $allocation->manpowerCategoryAllocations->sum('allocated_hours');
            $query->where(function ($q) {
                $q->where('entries.allocation_bucket_snapshot', 'shared')->orWhereNull('entries.allocation_bucket_snapshot')
                    ->orWhereNull('entries.manpower_category_snapshot')->orWhereNotIn('entries.manpower_category_snapshot', array_keys(config('manpower_categories.labels')));
            });
            $label = 'Shared remainder';
        } else {
            $limit = (float) ($allocation->manpowerCategoryAllocations->firstWhere('manpower_category', $category)?->allocated_hours ?? 0);
            $query->where('entries.allocation_bucket_snapshot', 'reserved')->where('entries.manpower_category_snapshot', $category);
            $label = $this->categoryLabel($category).' reservation';
        }
        $before = $limit - (float) (clone $query)->sum(DB::raw('entries.regular_hours + entries.overtime_hours'));
        $released = (float) $query->whereIn('entries.id', $releasingIds)->sum(DB::raw('entries.regular_hours + entries.overtime_hours'));

        return ['key' => $allocation->department_id.'|'.$bucket.($bucket === 'reserved' ? ':'.$category : ''),
            'label' => $allocation->department->name.' · '.$label, 'before' => $before, 'released' => $released, 'available' => $before + $released];
    }

    private function hours(TimesheetEntry $entry): float
    {
        return (float) $entry->regular_hours + (float) $entry->overtime_hours;
    }

    private function usage(int $projectId, ?int $departmentId)
    {
        return DB::table('timesheet_entries as entries')->join('timesheets', 'timesheets.id', '=', 'entries.timesheet_id')
            ->where('entries.project_id', $projectId)->where('entries.department_id', $departmentId)
            ->whereIn('timesheets.status', [Timesheet::STATUS_SUBMITTED, Timesheet::STATUS_APPROVED]);
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['transfer' => $message]);
    }
}
