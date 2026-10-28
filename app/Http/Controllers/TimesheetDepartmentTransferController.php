<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Project;
use App\Models\TimesheetEntry;
use App\Services\TimesheetDepartmentTransferService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TimesheetDepartmentTransferController extends Controller
{
    public function show(Request $request, Project $project, TimesheetEntry $entry, TimesheetDepartmentTransferService $transfers)
    {
        $this->authorizeEntry($request, $project, $entry);
        $entry->load(['timesheet.user', 'department']);
        $transfers->ensureEligible($entry);
        $data = $request->validate($this->destinationRules(false));
        if (filled($data['department_id'] ?? null)) {
            return response()->json($transfers->preview($entry, (int) $data['department_id'], $data['manpower_category'] ?? null));
        }

        $allocations = $project->departmentAllocations()->with('manpowerCategoryAllocations')->get()->keyBy('department_id');

        return response()->json([
            'entry' => $transfers->entrySummary($entry),
            'departments' => Department::where('is_active', true)->where('id', '!=', $entry->department_id ?? 0)->orderBy('name')->get(['id', 'name'])
                ->map(fn ($department) => ['id' => $department->id, 'name' => $department->name, 'allocated' => $allocations->has($department->id), 'controlled' => $allocations->get($department->id)?->usesManpowerCategories() ?? false]),
            'categories' => config('manpower_categories.labels'),
            'history' => AuditLog::with('user:id,name')->where('auditable_type', TimesheetEntry::class)->where('auditable_id', $entry->id)
                ->where('action', 'timesheet_entry_department_transferred')->latest('id')->limit(10)->get()
                ->map(fn ($log) => ['actor' => $log->user?->name ?? 'Former user', 'at' => $log->created_at->toDateTimeString(), 'from' => $log->old_values['department_name'] ?? 'Unknown', 'to' => $log->new_values['department_name'] ?? 'Unknown', 'reason' => $log->new_values['reason'] ?? '']),
        ]);
    }

    public function store(Request $request, Project $project, TimesheetEntry $entry, TimesheetDepartmentTransferService $transfers)
    {
        $this->authorizeEntry($request, $project, $entry);
        $data = $request->validate($this->destinationRules(true) + [
            'version' => ['required', 'string', 'size:64'],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);
        $moved = $transfers->transfer($entry, $data);

        return response()->json(['message' => 'Entry transferred successfully.', 'department_id' => $moved->department_id]);
    }

    private function authorizeEntry(Request $request, Project $project, TimesheetEntry $entry): void
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        abort_unless((int) $entry->project_id === (int) $project->id, 404);
    }

    private function destinationRules(bool $required): array
    {
        return [
            'department_id' => [$required ? 'required' : 'nullable', 'integer', 'exists:departments,id'],
            'manpower_category' => ['nullable', Rule::in(array_keys(config('manpower_categories.labels')))],
        ];
    }
}
