<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Timesheet;
use App\Models\TimesheetEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ManpowerPoolConversionService
{
    // Match timesheet editing and transfers: sheets, entries, then allocation rows.
    public function lock(Project $project, array $settings): void
    {
        Project::whereKey($project->id)->lockForUpdate()->firstOrFail();
        $departments = $project->departmentAllocations()->with('manpowerCategoryAllocations')->get()->filter(fn ($allocation) => $allocation->manpowerCategoryAllocations->contains(fn ($category) => $category->allocated_hours === null
                && (float) ($settings[$allocation->department_id][$category->manpower_category] ?? 0) > 0))->pluck('department_id');
        if ($departments->isNotEmpty()) {
            Timesheet::whereHas('entries', fn ($q) => $q->where('project_id', $project->id)->whereIn('department_id', $departments))
                ->orderBy('id')->lockForUpdate()->get(['id']);
            TimesheetEntry::where('project_id', $project->id)->whereIn('department_id', $departments)->orderBy('id')->lockForUpdate()->get(['id']);
        }
        $project->departmentAllocations()->orderBy('department_id')->lockForUpdate()->get();
    }

    public function preview(Project $project, array $allocations, array $settings, bool $locked = false): array
    {
        $stored = $project->departmentAllocations()->whereIn('department_id', array_keys($settings))->with(['department', 'manpowerCategoryAllocations'])->orderBy('department_id')->get();
        $transitions = [];
        foreach ($stored as $allocation) {
            foreach ($allocation->manpowerCategoryAllocations->sortBy(fn ($c) => array_search($c->manpower_category, array_keys(config('manpower_categories.labels')), true)) as $category) {
                $proposed = $settings[$allocation->department_id][$category->manpower_category] ?? null;
                if ($category->allocated_hours === null && $proposed !== null && (float) $proposed > 0) {
                    $transitions[$allocation->department_id][$category->manpower_category] = (float) $proposed;
                }
            }
        }
        $rows = TimesheetEntry::query()->select('timesheet_entries.*', 'timesheets.status as consumption_status')
            ->join('timesheets', 'timesheets.id', '=', 'timesheet_entries.timesheet_id')
            ->where('timesheet_entries.project_id', $project->id)->whereIn('timesheet_entries.department_id', array_keys($transitions))
            ->whereIn('timesheets.status', [Timesheet::STATUS_SUBMITTED, Timesheet::STATUS_APPROVED])
            ->whereRaw('timesheet_entries.regular_hours + timesheet_entries.overtime_hours > 0')
            ->orderBy('timesheet_entries.id')->when($locked, fn ($q) => $q->lockForUpdate())->get();
        $open = DB::table('timesheet_correction_request_entries as items')->join('timesheet_correction_requests as requests', 'requests.id', '=', 'items.timesheet_correction_request_id')
            ->whereIn('items.timesheet_entry_id', $rows->pluck('id'))->where('requests.status', 'open')
            ->when($locked, fn ($q) => $q->lockForUpdate())->pluck('items.timesheet_entry_id');
        $rows->each(fn ($entry) => $entry->setAttribute('open_correction', $open->contains($entry->id)));
        $categories = array_keys(config('manpower_categories.labels'));
        $errors = [];
        $conversions = [];
        $balances = [];
        foreach ($transitions as $departmentId => $targets) {
            $entries = $rows->where('department_id', $departmentId);
            $key = "job_level_allocations.$departmentId";
            $department = $stored->firstWhere('department_id', $departmentId)->department;
            $legacy = $entries->filter(fn ($e) => ! in_array($e->manpower_category_snapshot, $categories, true)
                || ! in_array($e->allocation_bucket_snapshot, ['shared', 'reserved'], true));
            if ($legacy->isNotEmpty()) {
                $errors[$key][] = $department->name.': cannot convert Shared to Reserved while '.number_format($legacy->sum(fn ($e) => $this->units($e)) / 100, 2).' hours have Legacy / Unclassified or incomplete pool snapshots. Classify these entries in the entry review docket first.';
            }
            $moved = 0;
            foreach ($targets as $category => $reservation) {
                $matching = $entries->where('manpower_category_snapshot', $category)->where('allocation_bucket_snapshot', 'shared');
                $approved = $matching->filter(fn ($e) => $e->consumption_status === Timesheet::STATUS_APPROVED)->sum(fn ($e) => $this->units($e));
                $pending = $matching->filter(fn ($e) => $e->consumption_status === Timesheet::STATUS_SUBMITTED)->sum(fn ($e) => $this->units($e));
                $reserved = $entries->where('manpower_category_snapshot', $category)->where('allocation_bucket_snapshot', 'reserved')->sum(fn ($e) => $this->units($e));
                if ($matching->isNotEmpty() && ! auth()->user()?->isSuperAdmin()) {
                    $errors[$key][] = 'Only a Super Admin can convert recorded shared hours to reserved hours.';
                }
                if ($matching->contains(fn ($e) => (bool) $e->open_correction)) {
                    $errors[$key][] = config('manpower_categories.labels.'.$category).': resolve or withdraw open correction requests on the affected entries before converting.';
                }
                if ((int) round($reservation * 100) < $reserved + $approved + $pending) {
                    $errors["$key.$category.hours"][] = 'Reservation must cover '.number_format(($reserved + $approved + $pending) / 100, 2).' existing reserved and shared hours.';
                }
                $moved += $approved + $pending;
                $conversions[] = [
                    'department_id' => $departmentId, 'department' => $department->name,
                    'category' => $category, 'label' => config('manpower_categories.labels.'.$category),
                    'count' => $matching->count(), 'entry_ids' => $matching->pluck('id')->all(),
                    'approved' => $approved / 100, 'pending' => $pending / 100,
                    'hours' => ($approved + $pending) / 100, 'reservation' => $reservation,
                    'reserved_remaining' => ((int) round($reservation * 100) - $reserved - $approved - $pending) / 100,
                ];
            }
            foreach ($entries->where('allocation_bucket_snapshot', 'reserved')->whereIn('manpower_category_snapshot', $categories)->groupBy('manpower_category_snapshot') as $category => $reservedEntries) {
                $budget = $settings[$departmentId][$category] ?? null;
                $used = $reservedEntries->sum(fn ($e) => $this->units($e));
                if ($budget === null || (int) round((float) $budget * 100) < $used) {
                    $errors["$key.$category.hours"][] = 'Reservation cannot be lower than '.number_format($used / 100, 2).' submitted and approved hours already used.';
                }
            }
            $total = (int) round((float) ($allocations[$departmentId] ?? 0) * 100);
            $consumed = $entries->sum(fn ($e) => $this->units($e));
            $shared = $entries->filter(fn ($e) => $e->allocation_bucket_snapshot !== 'reserved' || ! in_array($e->manpower_category_snapshot, $categories, true))->sum(fn ($e) => $this->units($e));
            $reservedBudget = collect($settings[$departmentId])->filter(fn ($hours) => $hours !== null)->sum(fn ($hours) => (int) round((float) $hours * 100));
            if ($total < $consumed) {
                $errors["department_allocations.$departmentId"][] = 'Allocation cannot be lower than '.number_format($consumed / 100, 2).' submitted and approved hours already used.';
            }
            if ($total - $reservedBudget < $shared - $moved) {
                $errors[$key][] = 'Keep at least '.number_format(($shared - $moved) / 100, 2).' shared hours for entries that are not being converted.';
            }
            $balances[] = ['department_id' => $departmentId, 'department' => $department->name,
                'allocation' => $total / 100, 'consumed' => $consumed / 100, 'remaining' => ($total - $consumed) / 100,
                'shared_allocation' => ($total - $reservedBudget) / 100, 'shared_consumed' => ($shared - $moved) / 100,
                'shared_remaining' => ($total - $reservedBudget - $shared + $moved) / 100];
        }
        $fingerprint = $rows->map(fn ($e) => [$e->only(['id', 'timesheet_id', 'department_id', 'manpower_category_snapshot', 'allocation_bucket_snapshot', 'regular_hours', 'overtime_hours', 'work_date', 'updated_at']), $e->consumption_status, (bool) $e->open_correction])->all();
        $version = hash_hmac('sha256', json_encode([$conversions, $balances, $fingerprint,
            $stored->map(fn ($a) => [$a->department_id, $a->allocated_hours, $a->manpowerCategoryAllocations->sortBy('manpower_category')->map(fn ($c) => [$c->manpower_category, $c->allocated_hours])->values()->all()])->all()], JSON_THROW_ON_ERROR), config('app.key'));

        return ['conversions' => $conversions, 'balances' => $balances, 'errors' => $errors, 'valid' => $errors === [], 'version' => $version];
    }

    public function apply(Project $project, array $plan, ?string $version, ?string $reason, AuditLogService $audit): void
    {
        if ($plan['errors']) {
            throw ValidationException::withMessages($plan['errors']);
        }
        if ($version && ! hash_equals($plan['version'], $version)) {
            throw ValidationException::withMessages(['pool_conversion_version' => 'Allocation usage or the proposed settings changed. Review the Shared to Reserved preview again before saving.']);
        }
        if (collect($plan['conversions'])->sum('count') === 0) {
            return;
        }
        if (! $version || ! hash_equals($plan['version'], $version)) {
            throw ValidationException::withMessages(['pool_conversion_version' => 'Allocation usage or the proposed settings changed. Review the Shared to Reserved preview again before saving.']);
        }
        $batchId = (string) Str::uuid();
        $count = collect($plan['conversions'])->sum('count');
        foreach ($plan['conversions'] as $conversion) {
            foreach (TimesheetEntry::whereIn('id', $conversion['entry_ids'])->orderBy('id')->lockForUpdate()->get() as $entry) {
                $old = $entry->only(['project_id', 'timesheet_id', 'department_id', 'manpower_category_snapshot', 'allocation_bucket_snapshot', 'regular_hours', 'overtime_hours', 'work_date']);
                $entry->update(['allocation_bucket_snapshot' => 'reserved']);
                $context = ['department_name' => $conversion['department'], 'category_label' => $conversion['label']];
                $audit->record('timesheet_entry_allocation_pool_converted', $entry, $old + $context,
                    $entry->only(array_keys($old)) + $context + ['reason' => $reason, 'batch_id' => $batchId, 'batch_count' => $count]);
            }
        }
        $audit->record('project_manpower_pools_converted', $project, null, ['batch_id' => $batchId, 'reason' => $reason,
            'conversions' => collect($plan['conversions'])->map(fn ($c) => collect($c)->except('entry_ids')->all())->all(), 'balances' => $plan['balances']]);
    }

    private function units(TimesheetEntry $entry): int
    {
        return (int) round(((float) $entry->regular_hours + (float) $entry->overtime_hours) * 100);
    }
}
