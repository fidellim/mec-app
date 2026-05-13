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
        return view('manage.departments.index', ['departments' => Department::with('hod')->orderBy('name')->paginate(20)]);
    }

    public function create()
    {
        return view('manage.departments.form', ['department' => new Department(), 'hods' => User::where('role', 'hod')->orderBy('name')->get()]);
    }

    public function store(Request $request, AuditLogService $audit)
    {
        $department = Department::create($this->validated($request));
        $audit->record('department_created', $department, null, $department->toArray());
        return redirect()->route('manage.departments.index')->with('success', 'Department created.');
    }

    public function edit(Department $department)
    {
        return view('manage.departments.form', ['department' => $department, 'hods' => User::where('role', 'hod')->orderBy('name')->get()]);
    }

    public function update(Request $request, Department $department, AuditLogService $audit)
    {
        $old = $department->toArray();
        $department->update($this->validated($request, $department));
        $audit->record('department_updated', $department, $old, $department->fresh()->toArray());
        return redirect()->route('manage.departments.index')->with('success', 'Department updated.');
    }

    private function validated(Request $request, ?Department $department = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('departments')->ignore($department)],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('departments')->ignore($department)],
            'hod_id' => ['nullable', 'exists:users,id'],
        ]);
    }
}
