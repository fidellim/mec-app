<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Validation\Rule;

class HolidayController extends Controller
{
    public function index()
    {
        return view('manage.holidays.index', [
            'holidays' => Holiday::query()
                ->orderByDesc('holiday_date')
                ->orderBy('region')
                ->paginate(20),
            'regions' => Holiday::REGIONS,
        ]);
    }

    public function create()
    {
        return view('manage.holidays.form', [
            'holiday' => new Holiday(['region' => Holiday::REGION_GLOBAL, 'is_active' => true]),
            'regions' => Holiday::REGIONS,
        ]);
    }

    public function store(Request $request, AuditLogService $audit)
    {
        $holiday = Holiday::create($this->validated($request));
        $audit->record('holiday_created', $holiday, null, $holiday->toArray());

        return redirect()->route('manage.holidays.index')->with('success', 'Holiday created.');
    }

    public function edit(Holiday $holiday)
    {
        return view('manage.holidays.form', [
            'holiday' => $holiday,
            'regions' => Holiday::REGIONS,
        ]);
    }

    public function update(Request $request, Holiday $holiday, AuditLogService $audit)
    {
        $old = $holiday->toArray();
        $holiday->update($this->validated($request, $holiday));
        $audit->record('holiday_updated', $holiday, $old, $holiday->fresh()->toArray());

        return redirect()->route('manage.holidays.index')->with('success', 'Holiday updated.');
    }

    public function status(Holiday $holiday, AuditLogService $audit)
    {
        $old = $holiday->toArray();
        $holiday->update(['is_active' => ! $holiday->is_active]);
        $audit->record($holiday->is_active ? 'holiday_activated' : 'holiday_deactivated', $holiday, $old, $holiday->fresh()->toArray());

        return redirect()
            ->route('manage.holidays.index')
            ->with('success', $holiday->is_active ? 'Holiday reactivated.' : 'Holiday deactivated.');
    }

    private function validated(Request $request, ?Holiday $holiday = null): array
    {
        $validator = ValidatorFacade::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'holiday_date' => ['required', 'date'],
            'region' => ['required', Rule::in(array_keys(Holiday::REGIONS))],
            'is_active' => ['boolean'],
        ]);

        $validator->after(function ($validator) use ($request, $holiday) {
            if (! $request->filled(['holiday_date', 'region'])) {
                return;
            }

            $duplicateExists = Holiday::query()
                ->where('region', $request->input('region'))
                ->whereDate('holiday_date', $request->input('holiday_date'))
                ->when($holiday, fn ($query) => $query->whereKeyNot($holiday->id))
                ->exists();

            if ($duplicateExists) {
                $validator->errors()->add('holiday_date', 'A holiday already exists for this region and date.');
            }
        });

        return $validator->validate() + ['is_active' => false];
    }
}
