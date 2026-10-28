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

    public function preview(TimesheetEntry $entry, int $destinationId, ?string $classification): array
    {
        $this->ensureEligible($entry);
        if ((int) $entry->department_id === $destinationId) {
            $this->fail('Choose a department different from the current department.');
        }
        $destination = ProjectDepartmentAllocation::with(['department', 'manpowerCategoryAllocations'])
            ->where('project_id', $entry->project_id)->where('department_id', $destinationId)->first();
        if (! $destination || ! $destination->department?->is_active) {
            $this->fail('Allocate hours to an active destination department before transferring.');
        }
        $source = ProjectDepartmentAllocation::where('project_id', $entry->project_id)->where('department_id', $entry->department_id)->first();
        $sourceRemaining = (float) ($source?->allocated_hours ?? 0) - $this->usage($entry->project_id, $entry->department_id)->sum(DB::raw('entries.regular_hours + entries.overtime_hours'));
        $destinationRemaining = (float) $destination->allocated_hours - $this->usage($entry->project_id, $destinationId)->sum(DB::raw('entries.regular_hours + entries.overtime_hours'));
        $categories = array_keys(config('manpower_categories.labels'));
        $category = $entry->manpower_category_snapshot;
        $knownCategory = in_array($category, $categories, true);
        if ($knownCategory && filled($classification) && $classification !== $category) {
            $this->fail('The recorded historical Manpower Category cannot be changed by this transfer.');
        }

        // Preserve known historical classifications; never infer them from today's assignment.
        $bucket = $knownCategory ? TimesheetAllocationService::BUCKET_SHARED : null;
        $poolRemaining = null;
        $poolLabel = null;
        $blocker = null;
        if ($destination->usesManpowerCategories()) {
            if (! $destination->hasCurrentManpowerCategoryConfiguration()) {
                $blocker = 'The destination Manpower Category setup needs administrator review.';
            } else {
                $category = $knownCategory ? $category : $classification;
                if (! in_array($category, $categories, true)) {
                    $blocker = 'Select the historical Manpower Category for this unclassified entry.';
                } elseif (! $destination->allowsManpowerCategory($category)) {
                    $blocker = 'This Manpower Category is not allowed in the destination department.';
                } else {
                    $setting = $destination->manpowerCategoryAllocations->firstWhere('manpower_category', $category);
                    $bucket = $setting->allocated_hours === null ? TimesheetAllocationService::BUCKET_SHARED : TimesheetAllocationService::BUCKET_RESERVED;
                    $pool = $this->usage($entry->project_id, $destinationId);
                    if ($bucket === TimesheetAllocationService::BUCKET_SHARED) {
                        $limit = (float) $destination->allocated_hours - (float) $destination->manpowerCategoryAllocations->sum('allocated_hours');
                        $pool->where(function ($q) use ($categories) {
                            $q->where('entries.allocation_bucket_snapshot', TimesheetAllocationService::BUCKET_SHARED)
                                ->orWhereNull('entries.allocation_bucket_snapshot')
                                ->orWhereNull('entries.manpower_category_snapshot')
                                ->orWhereNotIn('entries.manpower_category_snapshot', $categories);
                        });
                        $poolLabel = 'Shared remainder';
                    } else {
                        $limit = (float) $setting->allocated_hours;
                        $pool->where('entries.allocation_bucket_snapshot', TimesheetAllocationService::BUCKET_RESERVED)
                            ->where('entries.manpower_category_snapshot', $category);
                        $poolLabel = config('manpower_categories.labels.'.$category).' reservation';
                    }
                    $poolRemaining = $limit - (float) $pool->sum(DB::raw('entries.regular_hours + entries.overtime_hours'));
                }
            }
        }

        $hours = $this->hours($entry);
        $classificationBlocker = $blocker;
        $shortfall = max(0, $hours - $destinationRemaining, $poolRemaining === null ? 0 : $hours - $poolRemaining);
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
            'source_after' => (float) $sourceRemaining + $hours,
            'destination_before' => $destinationRemaining,
            'destination_after' => $destinationRemaining - $hours,
            'pool_label' => $poolLabel,
            'pool_before' => $poolRemaining,
            'pool_after' => $poolRemaining === null ? null : $poolRemaining - $hours,
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
            'category' => $entry->manpower_category_snapshot,
            'category_known' => array_key_exists($entry->manpower_category_snapshot ?? '', config('manpower_categories.labels')),
        ];
    }

    public function previewBatch(Collection $entries, int $destinationId, array $classifications = []): array
    {
        $entries = $entries->sortBy('id')->values();
        if ($entries->isEmpty() || $entries->count() > 50 || $entries->pluck('timesheet_id')->unique()->count() !== 1
            || $entries->pluck('project_id')->unique()->count() !== 1) {
            $this->fail('Select between 1 and 50 entries from one employee week and one project.');
        }
        if (array_diff(array_keys($classifications), $entries->pluck('id')->all()) !== []) {
            $this->fail('Category selections must belong to the selected entries.');
        }
        $alreadyThere = $entries->where('department_id', $destinationId)->pluck('id');
        if ($alreadyThere->isNotEmpty()) {
            $this->fail('Entries #'.$alreadyThere->implode(', #').' already belong to the destination. Remove them from this selection.');
        }

        $items = $entries->sortBy('id')->values()->map(function ($entry) use ($destinationId, $classifications) {
            $preview = $this->preview($entry, $destinationId, $classifications[$entry->id] ?? null);
            unset($preview['version']);

            return $preview;
        });
        $hours = (float) $items->sum('hours');
        $destinationBefore = $items->first()['destination_before'];
        $sources = $entries->groupBy(fn ($entry) => $entry->department_id ?? 'unclassified')->map(function ($group) use ($items) {
            $selected = $items->filter(fn ($item) => $group->contains('id', $item['entry']['id']));
            $first = $selected->first();

            return ['label' => $first['source_name'], 'before' => $first['source_before'], 'after' => $first['source_before'] + $selected->sum('hours')];
        })->values();
        $pools = $items->filter(fn ($item) => $item['pool_label'] !== null)->groupBy(fn ($item) => $item['allocation_bucket_snapshot'] === 'shared' ? 'shared' : 'reserved:'.$item['manpower_category_snapshot'])
            ->map(function ($group) {
                $first = $group->first();

                return ['label' => $first['pool_label'], 'before' => $first['pool_before'], 'after' => $first['pool_before'] - $group->sum('hours')];
            })->values();
        $blockers = $items->filter(fn ($item) => $item['classification_blocker'] !== null)
            ->map(fn ($item) => 'Entry #'.$item['entry']['id'].': '.$item['classification_blocker'])->values();
        $departmentShortfall = max(0, $hours - $destinationBefore);
        if ($departmentShortfall > 0.0001) {
            $blockers->push('Destination department needs '.number_format($departmentShortfall, 2).' more available hours for the whole selection.');
        }
        foreach ($pools as $pool) {
            if ($pool['after'] < -0.0001) {
                $blockers->push($pool['label'].' needs '.number_format(-$pool['after'], 2).' more available hours.');
            }
        }
        $preview = [
            'entries' => $items->all(), 'count' => $items->count(), 'hours' => $hours,
            'regular' => (float) $entries->sum('regular_hours'), 'overtime' => (float) $entries->sum('overtime_hours'),
            'destination_id' => $destinationId, 'destination_name' => $items->first()['destination_name'],
            'controlled' => $items->first()['controlled'], 'sources' => $sources->all(), 'pools' => $pools->all(),
            'destination_before' => $destinationBefore, 'destination_after' => $destinationBefore - $hours,
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
                ->whereIn('department_id', $entries->pluck('department_id')->push($data['department_id'])->filter()->unique())
                ->orderBy('department_id')->lockForUpdate()->get();
            $preview = $this->previewBatch($entries, (int) $data['department_id'], $data['classifications'] ?? []);
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
                    'department_id' => $preview['destination_id'],
                    'manpower_category_snapshot' => $item['manpower_category_snapshot'],
                    'allocation_bucket_snapshot' => $item['allocation_bucket_snapshot'],
                ]);
                app(AuditLogService::class)->record('timesheet_entry_department_transferred', $entry,
                    $old + ['department_name' => $item['source_name']],
                    $entry->only(array_keys($old)) + [
                        'department_name' => $preview['destination_name'], 'reason' => trim($data['reason']),
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
                ->whereIn('department_id', array_filter([$locked->department_id, $data['department_id']]))
                ->orderBy('department_id')->lockForUpdate()->get();
            $preview = $this->preview($locked, (int) $data['department_id'], $data['manpower_category'] ?? null);
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
            app(AuditLogService::class)->record('timesheet_entry_department_transferred', $locked,
                $old + ['department_name' => $preview['source_name']],
                $locked->only(array_keys($old)) + [
                    'department_name' => $preview['destination_name'],
                    'reason' => trim($data['reason']),
                    'preview' => $preview,
                ]);

            return $locked;
        }, 3);
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
