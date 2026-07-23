<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use App\Services\AdminExclusionService;
use App\Services\AuditLogService;
use App\Services\DashboardSummaryService;
use App\Services\HodExclusionService;
use App\Services\LeaveEntitlementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $request->merge([
            'search' => is_array($request->input('search'))
                ? collect($request->input('search'))->map(fn ($value) => is_string($value) ? trim($value) : $value)->all()
                : (is_string($request->input('search')) ? trim($request->input('search')) : $request->input('search')),
        ]);
        $visibleRoles = $request->user()->role === 'admin'
            ? $this->adminViewableRoles()
            : array_keys(config('roles.labels'));
        $regionLabels = [
            'uae' => 'United Arab Emirates',
            'ph' => 'Philippines',
            'unknown' => 'Unknown',
        ];

        if ($request->boolean('user_lookup')) {
            return response()->json($this->userLookup($request, $visibleRoles));
        }

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
            'role' => ['nullable', Rule::in($visibleRoles)],
            'region' => ['nullable', Rule::in(array_keys($regionLabels))],
            'search' => [
                'nullable',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($value === null || $value === '') {
                        return;
                    }

                    $values = is_array($value) ? $value : [$value];

                    foreach ($values as $name) {
                        if (! is_string($name) || mb_strlen($name) > 100) {
                            $fail('Select a valid user name.');

                            return;
                        }
                    }
                },
            ],
        ]);
        $departmentFilter = $filters['department_id'] ?? null;
        $roleFilter = $filters['role'] ?? null;
        $regionFilter = $filters['region'] ?? null;
        $searchFilter = $filters['search'] ?? null;
        $selectedSearchNames = collect(is_array($searchFilter) ? $searchFilter : (filled($searchFilter) ? [$searchFilter] : []))
            ->filter(fn ($value) => filled($value))
            ->unique()
            ->values()
            ->all();
        $searchLike = is_string($searchFilter) && filled($searchFilter) ? '%'.addcslashes($searchFilter, '\%_').'%' : null;

        return view('manage.users.index', [
            'users' => User::with(['department', 'primaryDepartments', 'managedDepartments'])
                ->when($request->user()->role === 'admin', fn ($query) => $query->whereIn('role', $this->adminViewableRoles()))
                ->when($departmentFilter === 'unassigned', fn ($query) => $query->whereNull('department_id'))
                ->when(filled($departmentFilter) && $departmentFilter !== 'unassigned', fn ($query) => $query->where('department_id', $departmentFilter))
                ->when(filled($roleFilter), fn ($query) => $query->where('role', $roleFilter))
                ->when($regionFilter === 'uae', fn ($query) => $query->where(function ($query) {
                    $query->where('employee_code', 'like', 'MEC-HR-%')
                        ->orWhere('employee_code', 'like', 'MCE-HR-%');
                }))
                ->when($regionFilter === 'ph', fn ($query) => $query->where('employee_code', 'like', 'MEC-PHIL-HR-%'))
                ->when($regionFilter === 'unknown', fn ($query) => $query->where(function ($query) {
                    $query->whereNull('employee_code')
                        ->orWhere('employee_code', '')
                        ->orWhere(function ($query) {
                            $query->where('employee_code', 'not like', 'MEC-HR-%')
                                ->where('employee_code', 'not like', 'MCE-HR-%')
                                ->where('employee_code', 'not like', 'MEC-PHIL-HR-%');
                        });
                }))
                ->when(is_array($searchFilter) && filled($selectedSearchNames), fn ($query) => $query->whereIn('name', $selectedSearchNames))
                ->when(! is_array($searchFilter) && filled($searchLike), fn ($query) => $query->where('name', 'like', $searchLike))
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
            'departments' => Department::orderBy('name')->get(),
            'selectedDepartmentId' => $departmentFilter,
            'selectedRole' => $roleFilter,
            'selectedRegion' => $regionFilter,
            'selectedSearch' => $searchFilter,
            'selectedSearchNames' => $selectedSearchNames,
            'visibleRoleLabels' => collect(config('roles.labels'))->only($visibleRoles),
            'regionLabels' => $regionLabels,
            'replacementHods' => User::where('role', 'hod')
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create(HodExclusionService $hodExclusions)
    {
        return view('manage.users.form', [
            'userModel' => new User,
            'departments' => Department::where('is_active', true)->orderBy('name')->get(),
            'hodExclusionCandidates' => collect(),
            'hodNotificationExclusionIds' => [],
            'hodApprovalExclusionIds' => [],
            'hodVisibilityExclusionIds' => [],
            'hodVisibilityExcludableIds' => [],
            'adminExclusionCandidates' => collect(),
            'adminNotificationExclusionIds' => [],
            'adminApprovalExclusionIds' => [],
        ]);
    }

    public function show(User $user, LeaveEntitlementService $entitlements)
    {
        abort_if(auth()->user()->role === 'admin' && ! in_array($user->role, $this->adminViewableRoles(), true), 403);

        return view('manage.users.show', [
            'userModel' => $user->load(['department', 'primaryDepartments', 'managedDepartments']),
            'leaveBalances' => $entitlements->visibleBalancesFor($user, viewer: auth()->user()),
        ]);
    }

    public function store(Request $request, AuditLogService $audit, HodExclusionService $hodExclusions, AdminExclusionService $adminExclusions, LeaveEntitlementService $entitlements)
    {
        $data = $this->validated($request);
        $data['password'] = $request->validate(
            ['password' => ['required', 'string', 'min:10', 'max:64']],
            $this->passwordValidationMessages()
        )['password'];
        $user = User::create($data);
        $annualEntitlement = $entitlements->syncCurrentYearAnnualOverride($user);
        $entitlements->syncCurrentYearEligibleEntitlements($user->fresh());
        $hodExclusions->syncForHod(
            $user,
            $request->input('hod_notification_exclusion_ids', []),
            $request->input('hod_approval_exclusion_ids', []),
            $request->input('hod_visibility_exclusion_ids', [])
        );
        $adminExclusions->syncForAdmin(
            $user,
            $request->input('admin_notification_exclusion_ids', []),
            $request->input('admin_approval_exclusion_ids', [])
        );
        $audit->record('user_created', $user, null, $user->toArray());
        if ($annualEntitlement) {
            $audit->record('leave_entitlement_synced', $annualEntitlement, null, $annualEntitlement->toArray());
        }

        return redirect()->route('manage.users.index')->with('success', 'User created.');
    }

    public function edit(User $user, HodExclusionService $hodExclusions, AdminExclusionService $adminExclusions)
    {
        $this->authorizeEditableUser(request()->user(), $user);

        return view('manage.users.form', [
            'userModel' => $user,
            'departments' => Department::where('is_active', true)
                ->orWhere('id', $user->department_id)
                ->orderBy('name')
                ->get(),
            'hodExclusionCandidates' => $hodExclusions->validSubmittersForHod($user),
            'hodNotificationExclusionIds' => $user->hodNotificationExcludedSubmitters()->pluck('users.id')->map(fn ($id) => (int) $id)->all(),
            'hodApprovalExclusionIds' => $user->hodApprovalExcludedSubmitters()->pluck('users.id')->map(fn ($id) => (int) $id)->all(),
            'hodVisibilityExclusionIds' => $user->hodVisibilityExcludedSubmitters()->pluck('users.id')->map(fn ($id) => (int) $id)->all(),
            'hodVisibilityExcludableIds' => $hodExclusions->visibilityExcludableSubmitterIdsForHod($user)->all(),
            'adminExclusionCandidates' => $user->role === 'admin' ? $adminExclusions->validHods() : collect(),
            'adminNotificationExclusionIds' => $user->adminNotificationExcludedHods()->pluck('users.id')->map(fn ($id) => (int) $id)->all(),
            'adminApprovalExclusionIds' => $user->adminApprovalExcludedHods()->pluck('users.id')->map(fn ($id) => (int) $id)->all(),
        ]);
    }

    public function update(Request $request, User $user, AuditLogService $audit, DashboardSummaryService $dashboard, HodExclusionService $hodExclusions, AdminExclusionService $adminExclusions, LeaveEntitlementService $entitlements)
    {
        $this->authorizeEditableUser($request->user(), $user);

        $isSuperAdminUpdate = $request->user()->role === 'super_admin';
        $old = $user->toArray();
        $data = $isSuperAdminUpdate
            ? $this->validated($request, $user)
            : $this->validatedAdminProfile($request, $user);
        if ($isSuperAdminUpdate && $request->filled('password')) {
            $data['password'] = $request->validate(
                ['password' => ['nullable', 'string', 'min:10', 'max:64']],
                $this->passwordValidationMessages()
            )['password'];
        }
        $oldDepartmentId = $user->department_id;
        $oldRole = $user->role;
        $oldHodExclusions = $isSuperAdminUpdate ? $hodExclusions->snapshotFor($user) : null;
        $oldAdminExclusions = $isSuperAdminUpdate ? $adminExclusions->snapshotFor($user) : null;
        $assignedHodDepartmentIds = $isSuperAdminUpdate && $oldRole === 'hod'
            ? $user->primaryDepartments()->pluck('id')
                ->merge($user->managedDepartments()->pluck('departments.id'))
                ->unique()
                ->values()
            : collect();

        DB::transaction(function () use ($request, $user, $data, $old, $oldDepartmentId, $oldRole, $oldHodExclusions, $oldAdminExclusions, $assignedHodDepartmentIds, $isSuperAdminUpdate, $audit, $dashboard, $hodExclusions, $adminExclusions, $entitlements) {
            $user->update($data);
            $audit->record('user_updated', $user, $old, $user->fresh()->toArray());

            $previousAnnualEntitlement = $user->leaveEntitlements()
                ->where('year', (int) now()->year)
                ->where('attendance_code', LeaveEntitlementService::ANNUAL_LEAVE_CODE)
                ->first()
                ?->toArray();
            $annualEntitlement = $entitlements->syncCurrentYearAnnualOverride($user->fresh());
            $entitlements->syncCurrentYearEligibleEntitlements($user->fresh());

            if ($annualEntitlement && $previousAnnualEntitlement !== $annualEntitlement->toArray()) {
                $audit->record('leave_entitlement_synced', $annualEntitlement, $previousAnnualEntitlement, $annualEntitlement->toArray());
            }

            if ($isSuperAdminUpdate && $oldRole === 'hod' && ($data['role'] ?? null) !== 'hod') {
                $clearedPrimaryDepartments = $user->primaryDepartments()->update(['hod_id' => null]);
                $detachedApproverDepartments = $user->managedDepartments()->detach();

                if ($clearedPrimaryDepartments > 0 || $detachedApproverDepartments > 0) {
                    $audit->record('user_hod_assignments_cleared', $user, [
                        'role' => $oldRole,
                        'department_ids' => $assignedHodDepartmentIds->all(),
                    ], [
                        'role' => $data['role'],
                        'cleared_primary_departments' => $clearedPrimaryDepartments,
                        'detached_approver_departments' => $detachedApproverDepartments,
                    ]);
                }
            }

            if ($isSuperAdminUpdate) {
                $hodExclusions->pruneInvalidForUser($user->fresh());
                $adminExclusions->pruneInvalidForUser($user->fresh());
                [, $newExclusions] = $hodExclusions->syncForHod(
                    $user->fresh(),
                    $request->input('hod_notification_exclusion_ids', []),
                    $request->input('hod_approval_exclusion_ids', []),
                    $request->input('hod_visibility_exclusion_ids', [])
                );

                if ($oldHodExclusions !== $newExclusions) {
                    $audit->record('user_hod_exclusions_updated', $user, $oldHodExclusions, $newExclusions);
                }

                [, $newAdminExclusions] = $adminExclusions->syncForAdmin(
                    $user->fresh(),
                    $request->input('admin_notification_exclusion_ids', []),
                    $request->input('admin_approval_exclusion_ids', [])
                );

                if ($oldAdminExclusions !== $newAdminExclusions) {
                    $audit->record('user_admin_exclusions_updated', $user, $oldAdminExclusions, $newAdminExclusions);
                }
            }

            if (
                array_key_exists('department_id', $data)
                && $data['department_id']
                && (int) $oldDepartmentId !== (int) $data['department_id']
            ) {
                $editableStatuses = ['draft', 'rejected', 'withdrawn', 'recalled'];

                $pendingTimesheets = $user->timesheets()
                    ->whereIn('status', $editableStatuses)
                    ->get(['id', 'department_id', 'timesheet_period_id']);

                $movedTimesheets = $user->timesheets()
                    ->whereIn('status', $editableStatuses)
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
                        'statuses' => $editableStatuses,
                    ]);
                }
            }
        });

        return redirect()->route('manage.users.index')->with('success', 'User updated.');
    }

    public function destroy(Request $request, User $user, AuditLogService $audit)
    {
        abort_if((int) $user->id === (int) $request->user()->id, 403, 'You cannot delete your own account.');

        $assignedDepartments = $user->primaryDepartments
            ->merge($user->managedDepartments)
            ->unique('id')
            ->values();

        if ($assignedDepartments->isNotEmpty()) {
            $request->validate([
                'replacement_hod_id' => [
                    'required',
                    Rule::exists('users', 'id')->where(fn ($query) => $query
                        ->where('role', 'hod')
                        ->where('is_active', true)
                        ->where('id', '!=', $user->id)
                    ),
                ],
            ], [
                'replacement_hod_id.required' => 'Select a replacement Head of Department before deleting this user.',
                'replacement_hod_id.exists' => 'The replacement Head of Department must be active.',
            ]);
        }

        $old = $user->load(['department', 'primaryDepartments', 'managedDepartments'])->toArray();
        $old['timesheets_count'] = $user->timesheets()->count();

        DB::transaction(function () use ($request, $user, $audit, $assignedDepartments, $old) {
            if ($assignedDepartments->isNotEmpty()) {
                $replacementHodId = $request->integer('replacement_hod_id');
                $assignedDepartmentIds = $assignedDepartments->pluck('id')->all();

                $user->primaryDepartments()->update(['hod_id' => $replacementHodId]);

                foreach ($assignedDepartmentIds as $departmentId) {
                    DB::table('department_hod')->updateOrInsert(
                        [
                            'department_id' => $departmentId,
                            'user_id' => $replacementHodId,
                        ],
                        [
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }
            }

            $audit->record('user_deleted', $user, $old, [
                'replacement_hod_id' => $assignedDepartments->isNotEmpty() ? $request->integer('replacement_hod_id') : null,
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
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'joining_date' => ['nullable', 'date'],
            'marital_status' => ['nullable', Rule::in(['single', 'married', 'widowed', 'separated'])],
            'eligible_for_parental_leave' => ['boolean'],
            'eligible_for_bereavement_spouse_leave' => ['boolean'],
            'eligible_for_bereavement_immediate_family_leave' => ['boolean'],
            'eligible_for_maternity_leave' => ['boolean'],
            'eligible_for_paternity_leave' => ['boolean'],
            'eligible_for_vawc_leave' => ['boolean'],
            'eligible_for_special_women_leave' => ['boolean'],
            'is_solo_parent' => ['boolean'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'role' => ['required', Rule::in(['super_admin', 'admin', 'hod', 'employee'])],
            'is_active' => ['boolean'],
            'receives_hod_timesheet_submission_emails' => ['boolean'],
            'annual_leave_allowance_days' => ['nullable', 'numeric', 'min:0', 'multiple_of:0.5'],
            'hod_notification_exclusion_ids' => ['nullable', 'array'],
            'hod_notification_exclusion_ids.*' => ['integer', Rule::exists('users', 'id')],
            'hod_approval_exclusion_ids' => ['nullable', 'array'],
            'hod_approval_exclusion_ids.*' => ['integer', Rule::exists('users', 'id')],
            'hod_visibility_exclusion_ids' => ['nullable', 'array'],
            'hod_visibility_exclusion_ids.*' => ['integer', Rule::exists('users', 'id')],
            'admin_notification_exclusion_ids' => ['nullable', 'array'],
            'admin_notification_exclusion_ids.*' => ['integer', Rule::exists('users', 'id')],
            'admin_approval_exclusion_ids' => ['nullable', 'array'],
            'admin_approval_exclusion_ids.*' => ['integer', Rule::exists('users', 'id')],
        ], [
            'employee_code.required' => 'Employee number is required for employees and HODs.',
            'employee_code.regex' => 'Employee number must use the format MEC-HR-YYYY-NNN, MCE-HR-YYYY-NNN, or MEC-PHIL-HR-YYYY-NNN. The final number must be at least 3 digits.',
        ]) + [
            'is_active' => false,
            'eligible_for_parental_leave' => false,
            'eligible_for_bereavement_spouse_leave' => false,
            'eligible_for_bereavement_immediate_family_leave' => false,
            'eligible_for_maternity_leave' => false,
            'eligible_for_paternity_leave' => false,
            'eligible_for_vawc_leave' => false,
            'eligible_for_special_women_leave' => false,
            'is_solo_parent' => false,
            'receives_hod_timesheet_submission_emails' => false,
        ];

        $data['initials'] = filled($data['initials'] ?? null) ? trim($data['initials']) : null;
        $data['job_title'] = filled($data['job_title'] ?? null) ? trim($data['job_title']) : null;
        $data['gender'] = filled($data['gender'] ?? null) ? $data['gender'] : null;
        $data['joining_date'] = filled($data['joining_date'] ?? null) ? $data['joining_date'] : null;
        $data['marital_status'] = filled($data['marital_status'] ?? null) ? $data['marital_status'] : null;
        $data['annual_leave_allowance_days'] = filled($data['annual_leave_allowance_days'] ?? null) ? $data['annual_leave_allowance_days'] : null;
        unset(
            $data['hod_notification_exclusion_ids'],
            $data['hod_approval_exclusion_ids'],
            $data['hod_visibility_exclusion_ids'],
            $data['admin_notification_exclusion_ids'],
            $data['admin_approval_exclusion_ids']
        );

        return $data;
    }

    private function validatedAdminProfile(Request $request, User $user): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'employee_code' => [
                Rule::requiredIf(fn () => in_array($user->role, ['employee', 'hod'], true)),
                'nullable',
                'string',
                'max:50',
                'regex:/^(MEC|MCE|MEC-PHIL)-HR-\d{4}-\d{3,}$/',
                Rule::unique('users')->ignore($user),
            ],
            'initials' => ['nullable', 'string', 'max:20'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'joining_date' => ['nullable', 'date'],
            'marital_status' => ['nullable', Rule::in(['single', 'married', 'widowed', 'separated'])],
            'eligible_for_parental_leave' => ['boolean'],
            'eligible_for_bereavement_spouse_leave' => ['boolean'],
            'eligible_for_bereavement_immediate_family_leave' => ['boolean'],
            'eligible_for_maternity_leave' => ['boolean'],
            'eligible_for_paternity_leave' => ['boolean'],
            'eligible_for_vawc_leave' => ['boolean'],
            'eligible_for_special_women_leave' => ['boolean'],
            'is_solo_parent' => ['boolean'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'is_active' => ['boolean'],
            'annual_leave_allowance_days' => ['nullable', 'numeric', 'min:0', 'multiple_of:0.5'],
        ], [
            'employee_code.required' => 'Employee number is required for employees and HODs.',
            'employee_code.regex' => 'Employee number must use the format MEC-HR-YYYY-NNN, MCE-HR-YYYY-NNN, or MEC-PHIL-HR-YYYY-NNN. The final number must be at least 3 digits.',
        ]) + [
            'is_active' => false,
            'eligible_for_parental_leave' => false,
            'eligible_for_bereavement_spouse_leave' => false,
            'eligible_for_bereavement_immediate_family_leave' => false,
            'eligible_for_maternity_leave' => false,
            'eligible_for_paternity_leave' => false,
            'eligible_for_vawc_leave' => false,
            'eligible_for_special_women_leave' => false,
            'is_solo_parent' => false,
        ];

        $data['initials'] = filled($data['initials'] ?? null) ? trim($data['initials']) : null;
        $data['job_title'] = filled($data['job_title'] ?? null) ? trim($data['job_title']) : null;
        $data['gender'] = filled($data['gender'] ?? null) ? $data['gender'] : null;
        $data['joining_date'] = filled($data['joining_date'] ?? null) ? $data['joining_date'] : null;
        $data['marital_status'] = filled($data['marital_status'] ?? null) ? $data['marital_status'] : null;
        if (array_key_exists('annual_leave_allowance_days', $data)) {
            $data['annual_leave_allowance_days'] = filled($data['annual_leave_allowance_days']) ? $data['annual_leave_allowance_days'] : null;
        }

        return $data;
    }

    private function userLookup(Request $request, array $visibleRoles): array
    {
        $search = trim((string) $request->query('q', ''));
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 50;
        $searchLike = $search !== '' ? '%'.addcslashes($search, '\%_').'%' : null;

        $users = User::query()
            ->whereIn('role', $visibleRoles)
            ->when($searchLike, fn ($query) => $query->where('name', 'like', $searchLike))
            ->orderBy('name')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage + 1)
            ->get(['id', 'name'])
            ->values();

        return [
            'results' => $users->take($perPage)->map(fn (User $user) => [
                'value' => $user->name,
                'text' => $user->name,
            ])->all(),
            'has_more' => $users->count() > $perPage,
            'page' => $page,
        ];
    }

    private function authorizeEditableUser(User $actor, User $target): void
    {
        if ($actor->role === 'super_admin') {
            return;
        }

        abort_unless($actor->role === 'admin' && in_array($target->role, $this->adminEditableRoles(), true), 403);
    }

    private function adminViewableRoles(): array
    {
        return ['admin', 'hod', 'employee'];
    }

    private function adminEditableRoles(): array
    {
        return ['hod', 'employee'];
    }

    private function passwordValidationMessages(): array
    {
        return [
            'password.min' => 'Password must be between 10 and 64 characters. Letters, numbers, symbols, and spaces are allowed.',
            'password.max' => 'Password must be between 10 and 64 characters. Letters, numbers, symbols, and spaces are allowed.',
        ];
    }
}
