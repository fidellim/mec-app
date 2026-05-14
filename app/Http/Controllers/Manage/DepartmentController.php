<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    public function index()
    {
        return view('manage.departments.index', [
            'departments' => Department::with('hod')
                ->withCount(['users', 'timesheets'])
                ->orderBy('name')
                ->paginate(20),
        ]);
    }

    public function create()
    {
        return view('manage.departments.form', ['department' => new Department(), 'hods' => User::where('role', 'hod')->where('is_active', true)->orderBy('name')->get()]);
    }

    public function store(Request $request, AuditLogService $audit)
    {
        $department = Department::create($this->validated($request));
        $audit->record('department_created', $department, null, $department->toArray());
        return redirect()->route('manage.departments.index')->with('success', 'Department created.');
    }

    public function edit(Department $department)
    {
        return view('manage.departments.form', ['department' => $department, 'hods' => User::where('role', 'hod')->where('is_active', true)->orderBy('name')->get()]);
    }

    public function update(Request $request, Department $department, AuditLogService $audit)
    {
        $old = $department->toArray();
        $department->update($this->validated($request, $department));
        $audit->record('department_updated', $department, $old, $department->fresh()->toArray());
        return redirect()->route('manage.departments.index')->with('success', 'Department updated.');
    }

    public function status(Department $department, AuditLogService $audit)
    {
        $old = $department->toArray();
        $department->update(['is_active' => ! $department->is_active]);
        $audit->record($department->is_active ? 'department_activated' : 'department_deactivated', $department, $old, $department->fresh()->toArray());

        return redirect()
            ->route('manage.departments.index')
            ->with('success', $department->is_active ? 'Department reactivated.' : 'Department deactivated.');
    }

    public function destroy(Department $department, AuditLogService $audit)
    {
        $department->loadCount(['users', 'timesheets']);

        if ($department->users_count > 0 || $department->timesheets_count > 0 || $department->hod_id) {
            return redirect()
                ->route('manage.departments.index')
                ->with('error', 'This department has users, timesheets, or an assigned HOD. Deactivate it instead of deleting it.');
        }

        $old = $department->toArray();
        $audit->record('department_deleted', $department, $old);
        $department->delete();

        return redirect()->route('manage.departments.index')->with('success', 'Unused department deleted.');
    }

    private function validated(Request $request, ?Department $department = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('departments')->ignore($department)],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('departments')->ignore($department)],
            'hod_id' => ['nullable', 'exists:users,id'],
            'is_active' => ['boolean'],
        ]) + ['is_active' => false];
    }
}
