<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\DashboardSummaryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'department_id' => [
                'nullable',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($value === null || $value === '') {
                        return;
                    }

                    if ($value === 'unassigned') {
                        return;
                    }

                    if (! ctype_digit((string) $value) || ! Department::whereKey($value)->exists()) {
                        $fail('Select a valid department.');
                    }
                },
            ],
        ]);
        $departmentFilter = $filters['department_id'] ?? null;

        return view('manage.users.index', [
            'users' => User::with(['department', 'headedDepartment'])
                ->when($departmentFilter === 'unassigned', fn ($query) => $query->whereNull('department_id'))
                ->when(filled($departmentFilter) && $departmentFilter !== 'unassigned', fn ($query) => $query->where('department_id', $departmentFilter))
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
            'departments' => Department::orderBy('name')->get(),
            'selectedDepartmentId' => $departmentFilter,
            'replacementHods' => User::where('role', 'hod')
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create()
    {
        return view('manage.users.form', ['userModel' => new User(), 'departments' => Department::where('is_active', true)->orderBy('name')->get()]);
    }

    public function store(Request $request, AuditLogService $audit)
    {
        $data = $this->validated($request);
        $data['password'] = $request->validate(['password' => ['required', 'min:8']])['password'];
        $user = User::create($data);
        $audit->record('user_created', $user, null, $user->toArray());

        return redirect()->route('manage.users.index')->with('success', 'User created.');
    }

    public function edit(User $user)
    {
        return view('manage.users.form', [
            'userModel' => $user,
            'departments' => Department::where('is_active', true)
                ->orWhere('id', $user->department_id)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(Request $request, User $user, AuditLogService $audit, DashboardSummaryService $dashboard)
    {
        $old = $user->toArray();
        $data = $this->validated($request, $user);
        if ($request->filled('password')) {
            $data['password'] = $request->validate(['password' => ['nullable', 'min:8']])['password'];
        }
        $oldDepartmentId = $user->department_id;

        DB::transaction(function () use ($user, $data, $old, $oldDepartmentId, $audit, $dashboard) {
            $user->update($data);
            $audit->record('user_updated', $user, $old, $user->fresh()->toArray());

            if (
                array_key_exists('department_id', $data)
                && $data['department_id']
                && (int) $oldDepartmentId !== (int) $data['department_id']
            ) {
                $pendingTimesheets = $user->timesheets()
                    ->whereIn('status', ['draft', 'rejected'])
                    ->get(['id', 'department_id', 'timesheet_period_id']);

                $movedTimesheets = $user->timesheets()
                    ->whereIn('status', ['draft', 'rejected'])
                    ->update(['department_id' => $data['department_id']]);

                if ($movedTimesheets > 0) {
                    $pendingTimesheets->each(function ($timesheet) use ($dashboard, $data, $oldDepartmentId) {
                        $timesheet->department_id = $data['department_id'];
                        $dashboard->forgetForTimesheet($timesheet, $oldDepartmentId);
                    });

                    $audit->record('user_pending_timesheets_reassigned', $user, [
                        'department_id' => $oldDepartmentId,
                    ], [
                        'department_id' => $data['department_id'],
                        'timesheets_count' => $movedTimesheets,
                        'statuses' => ['draft', 'rejected'],
                    ]);
                }
            }
        });

        return redirect()->route('manage.users.index')->with('success', 'User updated.');
    }

    public function destroy(Request $request, User $user, AuditLogService $audit)
    {
        abort_if((int) $user->id === (int) $request->user()->id, 403, 'You cannot delete your own account.');

        $headedDepartment = $user->headedDepartment;

        if ($headedDepartment) {
            $request->validate([
                'replacement_hod_id' => [
                    'required',
                    Rule::exists('users', 'id')->where(fn ($query) => $query
                        ->where('role', 'hod')
                        ->where('is_active', true)
                        ->where('department_id', $headedDepartment->id)
                        ->where('id', '!=', $user->id)
                    ),
                ],
            ], [
                'replacement_hod_id.required' => 'Select a replacement Head of Department before deleting this user.',
                'replacement_hod_id.exists' => 'The replacement Head of Department must be active and in the same department.',
            ]);
        }

        $old = $user->load(['department', 'headedDepartment'])->toArray();
        $old['timesheets_count'] = $user->timesheets()->count();

        DB::transaction(function () use ($request, $user, $audit, $headedDepartment, $old) {
            if ($headedDepartment) {
                $headedDepartment->update(['hod_id' => $request->integer('replacement_hod_id')]);
            }

            $audit->record('user_deleted', $user, $old, [
                'replacement_hod_id' => $headedDepartment ? $request->integer('replacement_hod_id') : null,
            ]);

            $user->delete();
        });

        return redirect()->route('manage.users.index')->with('success', 'User deleted. Related timesheets and entries were removed.');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users')->ignore($user)],
            'employee_code' => [
                Rule::requiredIf(fn () => in_array($request->role, ['employee', 'hod'], true)),
                'nullable',
                'string',
                'max:50',
                'regex:/^(MEC|MCE|MEC-PHIL)-HR-\d{4}-\d{3,}$/',
                Rule::unique('users')->ignore($user),
            ],
            'initials' => ['nullable', 'string', 'max:20'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'role' => ['required', Rule::in(['super_admin', 'admin', 'hod', 'employee'])],
            'is_active' => ['boolean'],
        ], [
            'employee_code.required' => 'Employee number is required for employees and HODs.',
            'employee_code.regex' => 'Employee number must use the format MEC-HR-YYYY-NNN, MCE-HR-YYYY-NNN, or MEC-PHIL-HR-YYYY-NNN. The final number must be at least 3 digits.',
        ]) + ['is_active' => false];

        $data['initials'] = filled($data['initials'] ?? null) ? trim($data['initials']) : null;
        $data['job_title'] = filled($data['job_title'] ?? null) ? trim($data['job_title']) : null;

        return $data;
    }
}
