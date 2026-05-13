<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        return view('manage.users.index', ['users' => User::with('department')->orderBy('name')->paginate(20)]);
    }

    public function create()
    {
        return view('manage.users.form', ['userModel' => new User(), 'departments' => Department::orderBy('name')->get()]);
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
        return view('manage.users.form', ['userModel' => $user, 'departments' => Department::orderBy('name')->get()]);
    }

    public function update(Request $request, User $user, AuditLogService $audit)
    {
        $old = $user->toArray();
        $data = $this->validated($request, $user);
        if ($request->filled('password')) {
            $data['password'] = $request->validate(['password' => ['nullable', 'min:8']])['password'];
        }
        $user->update($data);
        $audit->record('user_updated', $user, $old, $user->fresh()->toArray());

        return redirect()->route('manage.users.index')->with('success', 'User updated.');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users')->ignore($user)],
            'employee_code' => ['nullable', 'string', 'max:50', Rule::unique('users')->ignore($user)],
            'department_id' => ['nullable', 'exists:departments,id'],
            'role' => ['required', Rule::in(['super_admin', 'admin', 'hod', 'employee'])],
            'is_active' => ['boolean'],
        ]) + ['is_active' => false];
    }
}
