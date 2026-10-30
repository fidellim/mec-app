<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Project;
use App\Models\Timesheet;
use App\Models\TimesheetPeriod;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class HistoricalTimesheetImportService
{
    public function __construct(private TimesheetAllocationService $allocations, private AuditLogService $audit) {}

    public function populate(array $item): array
    {
        $item['original_entries'] = $item['entries'];
        $user = User::where('employee_code', $item['employee_code'])->first();
        $projects = Project::with(['assignedUsers' => fn ($query) => $query->whereKey($user?->id ?? 0)])->whereIn('project_code', array_column($item['entries'], 'project_code'))->get()->keyBy('project_code');
        foreach ($item['entries'] as &$entry) {
            $entry['department_id'] = $user?->department_id;
            $entry['manpower_category'] = $user ? $projects->get($entry['project_code'])?->manpowerCategoryFor($user) : null;
        }
        unset($entry);
        $item['initial_preview_entries'] = $item['entries'];

        return $item;
    }

    /** Resolve references in batches and reserve preview capacity in the same order as confirmation. */
    public function preview(array $items): array
    {
        $users = User::with('department')->whereIn('employee_code', array_column($items, 'employee_code'))->get()->keyBy('employee_code');
        $codes = collect($items)->flatMap(fn ($item) => array_column($item['entries'] ?? [], 'project_code'))->filter()->unique();
        $projects = Project::with(['assignedUsers' => fn ($query) => $query->whereKey($users->pluck('id')), 'departmentAllocations.manpowerCategoryAllocations'])->whereIn('project_code', $codes)->get()->keyBy('project_code');
        $starts = array_values(array_unique(array_column($items, 'start_date')));
        $periods = TimesheetPeriod::where(function ($query) use ($starts) {
            if (! $starts) {
                $query->whereRaw('1 = 0');
            }
            foreach ($starts as $start) {
                $query->orWhereDate('start_date', $start);
            }
        })->get()->keyBy(fn ($period) => $period->start_date->toDateString());
        $departments = Department::all()->keyBy('id');
        $existing = Timesheet::whereIn('user_id', $users->pluck('id'))->whereIn('timesheet_period_id', $periods->pluck('id'))->whereIn('status', Timesheet::ACTIVE_STATUSES)->get()->keyBy(fn ($sheet) => $sheet->user_id.'|'.$sheet->timesheet_period_id);
        $seen = [];
        $pending = [];
        $results = [];
        foreach ($items as $index => $item) {
            $result = $item + ['errors' => [], 'warnings' => [], 'state' => 'ready', 'resolved_entries' => [], 'snapshots' => []];
            $user = $users->get($item['employee_code'] ?? '');
            $period = $periods->get($item['start_date'] ?? '');
            $result['user'] = $user;
            $result['period'] = $period;
            if (isset($item['parse_error'])) {
                $result['errors'][] = $item['parse_error'];
            } else {
                if (! $user) {
                    $result['errors'][] = 'Employee code does not match an existing employee.';
                } elseif (! $user->department_id || ! $user->department) {
                    $result['errors'][] = 'The employee needs an existing department before importing.';
                } elseif (! $user->is_active) {
                    $result['warnings'][] = 'This employee is currently inactive.';
                }
                if (! $period || $period->end_date->toDateString() !== CarbonImmutable::parse($item['start_date'])->addDays(6)->toDateString()) {
                    $result['errors'][] = 'No matching Monday–Sunday weekly period exists. Create the historical period first.';
                } elseif ($period->end_date->isToday() || $period->end_date->isFuture()) {
                    $result['errors'][] = 'Only completed historical weeks can be imported.';
                }
                $key = ($user?->id ?? $item['employee_code']).'|'.$item['start_date'];
                if ($user && $period && $existing->has($user->id.'|'.$period->id)) {
                    $result['state'] = 'conflict';
                    $result['errors'][] = 'An existing timesheet already covers this employee and week. It will not be overwritten.';
                } elseif (isset($seen[$key])) {
                    $result['state'] = 'conflict';
                    $result['errors'][] = 'Another workbook in this batch already covers this employee and week.';
                }
                // The first workbook owns the slot even if it needs correction; never silently pick a later version.
                $seen[$key] = true;
                $activeEntries = array_values(array_filter($item['entries'], fn ($entry) => ! (is_numeric($entry['regular_hours'] ?? null) && is_numeric($entry['overtime_hours'] ?? null) && (float) $entry['regular_hours'] === 0.0 && (float) $entry['overtime_hours'] === 0.0)));
                $validator = Validator::make(['entries' => $activeEntries], [
                    'entries' => ['required', 'array', 'min:1', 'max:500'],
                    'entries.*.work_date' => ['required', 'date_format:Y-m-d'],
                    'entries.*.attendance_code' => ['required', Rule::in(array_keys(config('timesheet.attendance_codes')))],
                    'entries.*.project_code' => ['nullable', 'string', 'max:100'],
                    'entries.*.department_id' => ['required', 'integer'],
                    'entries.*.manpower_category' => ['nullable', Rule::in(array_keys(config('manpower_categories.labels')))],
                    'entries.*.regular_hours' => ['required', 'numeric', 'min:0', 'max:24', 'decimal:0,2'],
                    'entries.*.overtime_hours' => ['required', 'numeric', 'min:0', 'max:24', 'decimal:0,2'],
                    'entries.*.remarks' => ['nullable', 'string', 'max:2000'],
                ]);
                $result['errors'] = array_merge($result['errors'], $validator->errors()->all());
                if (! $validator->fails()) {
                    $daily = [];
                    foreach ($activeEntries as $entryIndex => $entry) {
                        $label = 'Entry '.($entryIndex + 1).' ('.$entry['work_date'].'): ';
                        $hours = (float) $entry['regular_hours'] + (float) $entry['overtime_hours'];
                        if ($entry['work_date'] < $item['start_date'] || $entry['work_date'] > CarbonImmutable::parse($item['start_date'])->addDays(6)->toDateString()) {
                            $result['errors'][] = $label.'date must be within the workbook week.';
                        }
                        if (! $departments->has((int) $entry['department_id'])) {
                            $result['errors'][] = $label.'select an existing discipline.';
                        } elseif (! $departments->get((int) $entry['department_id'])->is_active) {
                            $result['warnings'][] = $label.'the selected discipline is inactive.';
                        }
                        $project = $projects->get($entry['project_code'] ?? '');
                        $leave = in_array($entry['attendance_code'], config('timesheet.leave_attendance_codes'), true);
                        if (filled($entry['project_code']) && ! $project) {
                            $result['errors'][] = $label.'project number does not match an existing project.';
                        }
                        if (! $project && $hours > 0 && ! in_array($entry['attendance_code'], config('timesheet.project_optional_attendance_codes'), true)) {
                            $result['errors'][] = $label.'a project is required for these hours.';
                        }
                        if ($leave && (filled($entry['project_code']) || (float) $entry['overtime_hours'] > 0)) {
                            $result['errors'][] = $label.'leave entries cannot have a project or overtime.';
                        }
                        if ($project && ! $project->is_active) {
                            $result['warnings'][] = $project->project_code.': this project is currently inactive.';
                        }
                        if ($project && $user && $project->timesheet_assignment_mode === Project::ASSIGNMENT_SELECTED_USERS && ! $project->assignedUsers->contains('id', $user->id)) {
                            $result['warnings'][] = $project->project_code.': employee is not currently assigned to this project.';
                        }
                        if ($project && $hours > 0 && ! $project->departmentAllocations->contains('department_id', (int) $entry['department_id'])) {
                            $result['errors'][] = $label.'no hours are allocated to this project discipline. Re-allocate the project before retrying.';
                        }
                        if ($hours > 0) {
                            $result['resolved_entries'][] = $entry + ['project_id' => $project?->id];
                        }
                        $daily[$entry['work_date']] = ($daily[$entry['work_date']] ?? 0) + (int) round($hours * 100);
                    }
                    if (max($daily ?: [0]) > 2400) {
                        $result['errors'][] = 'Combined regular and overtime hours cannot exceed 24 hours per day.';
                    }
                    if (! $result['resolved_entries']) {
                        $result['errors'][] = 'At least one entry must have hours greater than zero.';
                    }
                }
                if (! $result['errors'] && $user) {
                    try {
                        $result['snapshots'] = $this->allocations->validateHistoricalImport($user, $result['resolved_entries'], $pending);
                        foreach ($result['resolved_entries'] as $i => $entry) {
                            $pending[] = $entry + ($result['snapshots'][$i] ?? ['manpower_category_snapshot' => null, 'allocation_bucket_snapshot' => null]);
                        }
                    } catch (ValidationException $exception) {
                        foreach ($exception->errors() as $field => $messages) {
                            preg_match('/entries\.(\d+)\./', $field, $matches);
                            $entry = $result['resolved_entries'][(int) ($matches[1] ?? 0)] ?? [];
                            $label = ($entry['project_code'] ?? 'Project').' / '.($departments->get((int) ($entry['department_id'] ?? 0))?->name ?? 'Discipline');
                            if (filled($entry['manpower_category'] ?? null)) {
                                $label .= ' / '.config('manpower_categories.labels.'.$entry['manpower_category'], $entry['manpower_category']);
                            }
                            foreach ($messages as $message) {
                                $result['errors'][] = $label.': '.($message === TimesheetAllocationService::MISSING_CATEGORY_MESSAGE ? 'Select a Manpower Category for these historical entries.' : $message);
                            }
                        }
                    }
                }
            }
            if ($result['errors'] && $result['state'] !== 'conflict') {
                $result['state'] = 'blocked';
            }
            $result['errors'] = array_values(array_unique($result['errors']));
            $result['warnings'] = array_values(array_unique($result['warnings']));
            $results[$index] = $result;
        }

        return $results;
    }

    public function commit(array $items, string $batchId, User $actor, ?array $reviewed = null): array
    {
        $results = [];
        $seen = [];
        foreach ($items as $index => $item) {
            $key = ($item['employee_code'] ?? '').'|'.($item['start_date'] ?? '');
            $receipt = ['filename' => $item['filename'], 'employee_code' => $item['employee_code'] ?? '', 'start_date' => $item['start_date'] ?? '', 'state' => 'failed', 'message' => '', 'timesheet_id' => null];
            if (isset($seen[$key]) && ! isset($item['parse_error'])) {
                $results[] = array_replace($receipt, ['state' => 'skipped', 'message' => 'Duplicate employee and week within this batch.']);

                continue;
            }
            $seen[$key] = true;
            if ($reviewed !== null && ($reviewed[$index]['state'] ?? 'blocked') !== 'ready') {
                $results[] = array_replace($receipt, ['state' => ($reviewed[$index]['state'] ?? '') === 'conflict' ? 'skipped' : 'failed', 'message' => implode(' ', $reviewed[$index]['errors'] ?? ['This timesheet was not ready in the reviewed preview.'])]);

                continue;
            }
            try {
                $results[] = DB::transaction(function () use ($item, $receipt, $batchId, $actor) {
                    // Serialise imports for an employee. The production unique active-period index
                    // also protects against normal employee submissions racing this request.
                    User::where('employee_code', $item['employee_code'] ?? '')->lockForUpdate()->first();
                    $review = $this->preview([$item])[0];
                    if ($review['state'] !== 'ready') {
                        return array_replace($receipt, ['state' => $review['state'] === 'conflict' ? 'skipped' : 'failed', 'message' => implode(' ', $review['errors'])]);
                    }
                    $sheet = Timesheet::create([
                        'user_id' => $review['user']->id, 'department_id' => $review['user']->department_id,
                        'timesheet_period_id' => $review['period']->id, 'status' => Timesheet::STATUS_APPROVED,
                        'submitted_at' => null, 'approved_at' => null, 'approved_by' => null,
                        'total_regular_hours' => collect($review['resolved_entries'])->sum('regular_hours'),
                        'total_overtime_hours' => collect($review['resolved_entries'])->sum('overtime_hours'),
                        'total_hours' => collect($review['resolved_entries'])->sum(fn ($entry) => (float) $entry['regular_hours'] + (float) $entry['overtime_hours']),
                    ]);
                    foreach ($review['resolved_entries'] as $i => $entry) {
                        $sheet->entries()->create([
                            'work_date' => $entry['work_date'], 'day_name' => CarbonImmutable::parse($entry['work_date'])->format('l'),
                            'attendance_code' => $entry['attendance_code'], 'project_id' => $entry['project_id'],
                            'department_id' => $entry['department_id'], 'regular_hours' => $entry['regular_hours'],
                            'overtime_hours' => $entry['overtime_hours'], 'remarks' => $entry['remarks'] ?? null,
                        ] + ($review['snapshots'][$i] ?? []));
                    }
                    $metadata = [
                        'source' => 'historical_import', 'batch_id' => $batchId,
                        'filename' => $item['filename'], 'worksheet' => $item['worksheet'], 'sha256' => $item['sha256'],
                        'original_approver' => null, 'original_approved_at' => null,
                        'original_entries' => $item['original_entries'], 'imported_entries' => $item['entries'],
                        'initial_preview_entries' => $item['initial_preview_entries'] ?? $item['original_entries'],
                        'preview_edited' => ($item['initial_preview_entries'] ?? $item['original_entries']) != $item['entries'],
                        'warnings' => $review['warnings'],
                    ];
                    $sheet->statusHistories()->create([
                        'actor_id' => $actor->id, 'action' => 'timesheet_historical_imported',
                        'old_status' => null, 'new_status' => Timesheet::STATUS_APPROVED,
                        'comment' => 'Imported as previously approved. Original approver and approval date are unknown. Historical leave hours do not create leave records or additional deductions.',
                        'ip_address' => request()->ip(), 'metadata' => $metadata, 'occurred_at' => now(),
                    ]);
                    $this->audit->record('timesheet_historical_imported', $sheet, null, ['status' => Timesheet::STATUS_APPROVED, 'import' => $metadata]);

                    return array_replace($receipt, ['state' => 'imported', 'message' => 'Imported as previously approved.', 'timesheet_id' => $sheet->id]);
                }, 3);
            } catch (Throwable $exception) {
                report($exception);
                $results[] = array_replace($receipt, ['message' => 'This timesheet could not be saved. No partial timesheet was retained. Retry the workbook; existing timesheets will be skipped.']);
            }
        }

        return $results;
    }
}
