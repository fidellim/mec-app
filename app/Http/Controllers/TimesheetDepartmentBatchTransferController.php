<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Project;
use App\Models\Timesheet;
use App\Models\TimesheetEntry;
use App\Services\TimesheetDepartmentTransferService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TimesheetDepartmentBatchTransferController extends Controller
{
    public function show(Request $request, Project $project, Timesheet $timesheet, TimesheetDepartmentTransferService $transfers)
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        $data = $request->validate($this->rules(false, $request->boolean('category_only')));
        $entries = TimesheetEntry::with(['timesheet.user', 'department'])->where('project_id', $project->id)
            ->where('timesheet_id', $timesheet->id)->whereIn('id', $data['entry_ids'])->orderBy('id')->get();
        if ($entries->count() !== count($data['entry_ids'])) {
            throw ValidationException::withMessages(['transfer' => 'Select entries from this project and one employee week. Some selected entries may have been removed.']);
        }
        $entries->each(fn ($entry) => $transfers->ensureEligible($entry));
        if (filled($data['department_id'] ?? null) || $request->boolean('category_only')) {
            return response()->json($transfers->previewBatch($entries, isset($data['department_id']) ? (int) $data['department_id'] : null, $data['classifications'] ?? [], $request->boolean('category_only')));
        }
        $allocations = $project->departmentAllocations()->with('manpowerCategoryAllocations')->get()->keyBy('department_id');

        return response()->json([
            'entries' => $entries->map(fn ($entry) => $transfers->entrySummary($entry)),
            'employee' => $timesheet->user->name,
            'week' => 'Week '.$timesheet->period->week_number.', '.$timesheet->period->year,
            'departments' => Department::where('is_active', true)->orderBy('name')->get(['id', 'name'])
                ->map(fn ($department) => ['id' => $department->id, 'name' => $department->name, 'allocated' => $allocations->has($department->id), 'controlled' => $allocations->get($department->id)?->usesManpowerCategories() ?? false]),
            'categories' => config('manpower_categories.labels'),
        ]);
    }

    public function store(Request $request, Project $project, Timesheet $timesheet, TimesheetDepartmentTransferService $transfers)
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        $data = $request->validate($this->rules(true, $request->boolean('category_only')) + [
            'version' => ['required', 'string', 'size:64'], 'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);
        $result = $transfers->transferBatch($project->id, $timesheet, $data);

        return response()->json($result + ['message' => 'All selected entries updated successfully.']);
    }

    private function rules(bool $saving, bool $categoryOnly): array
    {
        return [
            'entry_ids' => ['required', 'array', 'min:1', 'max:50'], 'entry_ids.*' => ['required', 'integer', 'distinct'],
            'department_id' => [$saving && ! $categoryOnly ? 'required' : 'nullable', 'integer', 'exists:departments,id'],
            'category_only' => ['nullable', 'boolean'],
            'classifications' => ['nullable', 'array', 'max:50'],
            'classifications.*' => ['nullable', Rule::in(array_keys(config('manpower_categories.labels')))],
        ];
    }
}
