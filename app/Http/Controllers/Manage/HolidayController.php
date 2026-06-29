<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\HolidayDate;
use App\Models\HolidayEvent;
use App\Services\AuditLogService;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Validation\Rule;

class HolidayController extends Controller
{
    public function index()
    {
        return view('manage.holidays.index', [
            'holidays' => HolidayEvent::query()
                ->orderByDesc('start_date')
                ->orderBy('region')
                ->paginate(20),
            'regions' => HolidayEvent::REGIONS,
        ]);
    }

    public function create()
    {
        return view('manage.holidays.form', [
            'holiday' => new HolidayEvent(['region' => HolidayEvent::REGION_GLOBAL, 'is_active' => true]),
            'regions' => HolidayEvent::REGIONS,
        ]);
    }

    public function store(Request $request, AuditLogService $audit)
    {
        $data = $this->validated($request, includeEndDate: true);

        DB::transaction(function () use ($audit, $data) {
            $holiday = HolidayEvent::create($this->eventAttributes($data));
            $this->replaceHolidayDates($holiday);

            $audit->record('holiday_created', $holiday, null, $holiday->load('dates')->toArray());
        });

        return redirect()->route('manage.holidays.index')->with('success', 'Holiday created.');
    }

    public function edit(HolidayEvent $holiday)
    {
        return view('manage.holidays.form', [
            'holiday' => $holiday,
            'regions' => HolidayEvent::REGIONS,
        ]);
    }

    public function update(Request $request, HolidayEvent $holiday, AuditLogService $audit)
    {
        $data = $this->validated($request, $holiday, includeEndDate: true);

        DB::transaction(function () use ($audit, $data, $holiday) {
            $old = $holiday->load('dates')->toArray();
            $holiday->update($this->eventAttributes($data));
            $this->replaceHolidayDates($holiday);

            $audit->record('holiday_updated', $holiday, $old, $holiday->fresh('dates')->toArray());
        });

        return redirect()->route('manage.holidays.index')->with('success', 'Holiday updated.');
    }

    public function status(HolidayEvent $holiday, AuditLogService $audit)
    {
        $old = $holiday->toArray();
        $holiday->update(['is_active' => ! $holiday->is_active]);
        $audit->record($holiday->is_active ? 'holiday_activated' : 'holiday_deactivated', $holiday, $old, $holiday->fresh()->toArray());

        return redirect()
            ->route('manage.holidays.index')
            ->with('success', $holiday->is_active ? 'Holiday reactivated.' : 'Holiday deactivated.');
    }

    private function validated(Request $request, ?HolidayEvent $holiday = null, bool $includeEndDate = false): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'holiday_date' => ['required', 'date'],
            'region' => ['required', Rule::in(array_keys(HolidayEvent::REGIONS))],
            'is_active' => ['boolean'],
        ];

        if ($includeEndDate) {
            $rules['holiday_end_date'] = ['nullable', 'date', 'after_or_equal:holiday_date'];
        }

        $validator = ValidatorFacade::make($request->all(), $rules);

        $validator->after(function ($validator) use ($request, $holiday, $includeEndDate) {
            if (! $request->filled(['holiday_date', 'region'])) {
                return;
            }

            if ($validator->errors()->has('holiday_date') || $validator->errors()->has('holiday_end_date')) {
                return;
            }

            $startDate = CarbonImmutable::parse($request->input('holiday_date'));
            $endDate = $includeEndDate && $request->filled('holiday_end_date')
                ? CarbonImmutable::parse($request->input('holiday_end_date'))
                : $startDate;

            if ($endDate->lt($startDate)) {
                return;
            }

            $duplicateExists = HolidayDate::query()
                ->where('region', $request->input('region'))
                ->whereDate('holiday_date', '>=', $startDate->toDateString())
                ->whereDate('holiday_date', '<=', $endDate->toDateString())
                ->when($holiday, fn ($query) => $query->where('holiday_event_id', '!=', $holiday->id))
                ->exists();

            if ($duplicateExists) {
                $validator->errors()->add('holiday_date', 'A holiday already exists for this region and date.');
            }
        });

        return $validator->validate() + ['is_active' => false];
    }

    private function eventAttributes(array $data): array
    {
        return [
            'name' => $data['name'],
            'region' => $data['region'],
            'start_date' => $data['holiday_date'],
            'end_date' => $data['holiday_end_date'] ?? $data['holiday_date'],
            'is_active' => $data['is_active'],
        ];
    }

    private function replaceHolidayDates(HolidayEvent $holiday): void
    {
        $holiday->dates()->delete();

        $dates = collect(CarbonPeriod::create(
            CarbonImmutable::parse($holiday->start_date),
            CarbonImmutable::parse($holiday->end_date)
        ))->map(fn ($date) => [
            'region' => $holiday->region,
            'holiday_date' => $date->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ])->all();

        $holiday->dates()->createMany($dates);
    }
}
