<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Project;
use App\Models\Timesheet;
use App\Models\TimesheetEntry;
use App\Models\User;
use App\Services\HistoricalTimesheetImportService;
use App\Services\HistoricalTimesheetImportStore;
use App\Services\HistoricalTimesheetWorkbookReader;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class HistoricalTimesheetImportController extends Controller
{
    public function index()
    {
        return view('admin.timesheets.import');
    }

    public function upload(Request $request, HistoricalTimesheetWorkbookReader $reader, HistoricalTimesheetImportStore $store, HistoricalTimesheetImportService $imports)
    {
        // Explicitly clean PHP upload files even when request validation fails.
        $uploads = collect($request->allFiles())->flatten();
        try {
            $request->validate([
                'workbooks' => ['required', 'array', 'min:1', 'max:'.config('timesheet_import.max_files')],
                'workbooks.*' => ['required', 'file', 'max:'.config('timesheet_import.max_file_kb'), 'extensions:xlsx,xlsm'],
            ]);
            if ($uploads->sum(fn ($file) => $file->getSize()) > config('timesheet_import.max_batch_kb') * 1024) {
                return back()->withErrors(['workbooks' => 'The combined workbook size must not exceed 20 MB.']);
            }
            $items = [];
            $previewBytes = 0;
            foreach ($request->file('workbooks') as $file) {
                $filename = Str::limit(basename(str_replace('\\', '/', $file->getClientOriginalName())), 200, '');
                $path = null;
                try {
                    $path = $file->move($store->directory(), Str::uuid().'.upload')->getPathname();
                    $item = $reader->read($path);
                    $item['filename'] = $filename;
                    $item['sha256'] = hash_file('sha256', $path);
                    $item = $imports->populate($item);
                    $itemBytes = strlen(json_encode($item, JSON_THROW_ON_ERROR));
                    if ($previewBytes + $itemBytes > config('timesheet_import.max_preview_kb') * 1024) {
                        throw new \RuntimeException('The extracted preview exceeds the batch limit. Upload fewer workbooks in the next batch.');
                    }
                    $previewBytes += $itemBytes;
                    $items[] = $item;
                } catch (Throwable $exception) {
                    $items[] = ['filename' => $filename, 'parse_error' => $exception instanceof \RuntimeException ? $exception->getMessage() : 'The workbook could not be read. Check its format and save it again in Excel.', 'entries' => []];
                    if (! $exception instanceof \RuntimeException) {
                        report($exception);
                    }
                } finally {
                    if ($path && is_file($path)) {
                        unlink($path);
                    }
                }
            }
            $id = $store->create($request->user()->id, $items);

            return redirect()->route('admin.timesheets.import.show', $id);
        } finally {
            foreach ($uploads as $file) {
                if (is_file($file->getPathname())) {
                    unlink($file->getPathname());
                }
            }
        }
    }

    public function show(Request $request, string $batch, HistoricalTimesheetImportStore $store, HistoricalTimesheetImportService $imports)
    {
        $data = $store->access($batch, $request->user()->id, function (&$data) use ($imports) {
            if ($data['results'] === null) {
                $data['review'] = DB::transaction(fn () => $imports->preview($data['items']));
                // Keep only the reviewed indices/messages in staging, never serialised Eloquent objects.
                $reviewed = collect($data['review'])->map(fn ($item) => ['state' => $item['state'], 'errors' => $item['errors'], 'user_id' => $item['user']?->id])->all();
                if (isset($data['reviewed']) && $data['reviewed'] !== $reviewed) {
                    // Another tab must not silently expand the set approved by an older preview.
                    $data['revision']++;
                }
                $data['reviewed'] = $reviewed;
                $data['preview_revision'] = $data['revision'];
                $result = $data;
                unset($data['review']);

                return $result;
            }

            return $data;
        });
        $selected = filter_var($request->query('sheet', 0), FILTER_VALIDATE_INT);
        $selected = $selected === false || ! isset($data['items'][$selected]) ? 0 : $selected;
        $previewTimesheet = null;
        $review = $data['review'][$selected] ?? null;
        if ($review && $review['user']?->department && $review['period'] && $review['resolved_entries']) {
            $previewTimesheet = new Timesheet([
                'total_regular_hours' => collect($review['resolved_entries'])->sum('regular_hours'),
                'total_overtime_hours' => collect($review['resolved_entries'])->sum('overtime_hours'),
                'total_hours' => collect($review['resolved_entries'])->sum(fn ($entry) => (float) $entry['regular_hours'] + (float) $entry['overtime_hours']),
            ]);
            $previewTimesheet->setRelation('user', $review['user']);
            $previewTimesheet->setRelation('department', $review['user']->department);
            $previewTimesheet->setRelation('period', $review['period']);
            $projects = Project::whereIn('id', array_column($review['resolved_entries'], 'project_id'))->get()->keyBy('id');
            $departments = Department::all()->keyBy('id');
            $previewTimesheet->setRelation('entries', collect($review['resolved_entries'])->map(function ($entry, $i) use ($projects, $departments) {
                $model = new TimesheetEntry($entry + ['day_name' => Carbon::parse($entry['work_date'])->format('l'), 'manpower_category_snapshot' => $entry['manpower_category']]);
                $model->id = $i + 1;
                $model->setRelation('project', $projects->get($entry['project_id']));
                $model->setRelation('department', $departments->get((int) $entry['department_id']));

                return $model;
            }));
        }

        return view('admin.timesheets.import', [
            'batchId' => $batch, 'batch' => $data, 'selected' => $selected, 'previewTimesheet' => $previewTimesheet,
            'departments' => Department::orderBy('name')->get(),
            'projects' => Project::orderBy('project_code')->get(['id', 'project_code', 'project_name', 'is_active']),
            'employees' => User::with('department:id,name')->whereNotNull('employee_code')->where('employee_code', '!=', '')->orderBy('name')->get(['id', 'name', 'employee_code', 'department_id', 'is_active']),
        ]);
    }

    public function assignEmployee(Request $request, string $batch, int $sheet, HistoricalTimesheetImportStore $store, HistoricalTimesheetImportService $imports)
    {
        $validated = $request->validate([
            'revision' => ['required', 'integer'], 'assignment_action' => ['required', 'in:assign,restore'],
            'employee_id' => ['required_if:assignment_action,assign', 'nullable', 'integer', 'exists:users,id'],
            'assignment_reason' => ['required', 'string', 'max:2000'],
            'incorrect_employee_match' => ['sometimes', 'accepted'],
        ]);
        $store->access($batch, $request->user()->id, function (&$data) use ($validated, $sheet, $request, $imports) {
            abort_unless($data['results'] === null && $data['revision'] === (int) $validated['revision'], 409, 'This preview has changed or has already been imported. Reload it before continuing.');
            abort_unless(isset($data['items'][$sheet]) && ! isset($data['items'][$sheet]['parse_error']), 404);
            $target = isset($validated['employee_id']) ? User::find($validated['employee_id']) : null;
            $item = $imports->assignEmployee($data['items'][$sheet], $request->user(), $target, $validated['assignment_reason'], $request->boolean('incorrect_employee_match'), $validated['assignment_action'] === 'restore');
            abort_if(strlen(json_encode($item, JSON_THROW_ON_ERROR)) + strlen(json_encode(array_diff_key($data['items'], [$sheet => true]), JSON_THROW_ON_ERROR)) > config('timesheet_import.max_preview_kb') * 1024, 422, 'The preview size limit has been reached. Start a new import for further corrections.');
            $data['items'][$sheet] = $item;
            $data['revision']++;
            unset($data['reviewed'], $data['preview_revision']);
        });

        return redirect()->route('admin.timesheets.import.show', ['batch' => $batch, 'sheet' => $sheet]);
    }

    public function update(Request $request, string $batch, int $sheet, HistoricalTimesheetImportStore $store)
    {
        $validated = $request->validate([
            'revision' => ['required', 'integer'], 'entry_count' => ['required', 'integer', 'min:1', 'max:500'],
            'entries' => ['required', 'array', 'min:1', 'max:500'],
            'entries.*' => ['required', 'array:work_date,attendance_code,project_code,department_id,manpower_category,regular_hours,overtime_hours,remarks,source_row'],
            'entries.*.work_date' => ['required', 'string', 'max:20'],
            'entries.*.attendance_code' => ['nullable', 'string', 'max:20'],
            'entries.*.project_code' => ['nullable', 'string', 'max:100'],
            'entries.*.department_id' => ['nullable', 'integer'],
            'entries.*.manpower_category' => ['nullable', 'string', 'max:100'],
            'entries.*.regular_hours' => ['required', 'numeric', 'min:0', 'max:24', 'decimal:0,2'],
            'entries.*.overtime_hours' => ['required', 'numeric', 'min:0', 'max:24', 'decimal:0,2'],
            'entries.*.remarks' => ['nullable', 'string', 'max:2000'],
            'entries.*.source_row' => ['nullable', 'integer'],
        ]);
        abort_unless(count($validated['entries']) === (int) $validated['entry_count'], 422, 'The entry list was incomplete. Save the preview again.');
        $store->access($batch, $request->user()->id, function (&$data) use ($validated, $sheet) {
            abort_unless($data['results'] === null && $data['revision'] === (int) $validated['revision'], 409, 'This preview has changed or has already been imported. Reload it before continuing.');
            abort_unless(isset($data['items'][$sheet]) && ! isset($data['items'][$sheet]['parse_error']), 404);
            $data['items'][$sheet]['entries'] = array_values(array_map(fn ($entry) => array_replace([
                'project_code' => '', 'attendance_code' => '', 'department_id' => null,
                'manpower_category' => null, 'remarks' => '', 'source_row' => null,
            ], $entry), $validated['entries']));
            $data['revision']++;
            unset($data['reviewed'], $data['preview_revision']);
        });

        return redirect()->route('admin.timesheets.import.show', ['batch' => $batch, 'sheet' => $sheet]);
    }

    public function confirm(Request $request, string $batch, HistoricalTimesheetImportStore $store, HistoricalTimesheetImportService $imports)
    {
        $request->validate(['revision' => ['required', 'integer']]);
        $store->access($batch, $request->user()->id, function (&$data) use ($imports, $batch, $request) {
            if ($data['results'] !== null) {
                return; // An identical retry displays the receipt; it never runs another import.
            }
            abort_unless($data['revision'] === (int) $request->input('revision') && ($data['preview_revision'] ?? null) === $data['revision'], 409, 'Review the latest saved preview before importing.');
            $data['results'] = $imports->commit($data['items'], $batch, $request->user(), $data['reviewed']);
            // A receipt contains no staged entry data. Retrying failures requires another upload.
            $data['items'] = [];
            unset($data['reviewed'], $data['preview_revision']);
        });

        return redirect()->route('admin.timesheets.import.show', $batch);
    }

    public function cancel(Request $request, string $batch, HistoricalTimesheetImportStore $store)
    {
        $store->access($batch, $request->user()->id, function (&$data) {
            $data['items'] = [];
            $data['results'] = [];
        });

        return redirect()->route('admin.timesheets.import.index')->with('success', 'Import cancelled. Preview data has been removed.');
    }
}
