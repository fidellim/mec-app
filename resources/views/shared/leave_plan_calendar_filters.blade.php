<form class="filter-card mb-3" method="get">
    <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
    <div class="row g-3 align-items-end">
        <div class="col-md-6 col-xl-3">
            <label class="form-label">Department</label>
            <select class="form-select" name="department_id">
                <option value="">All departments</option>
                @foreach($departments as $department)
                    <option value="{{ $department->id }}" @selected((int) $filters['department_id'] === (int) $department->id || (isset($selectedDepartmentId) && (int) $selectedDepartmentId === (int) $department->id))>{{ $department->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6 col-xl-3">
            <label class="form-label">Employee</label>
            <select class="form-select" name="employee_id">
                <option value="">All employees</option>
                @foreach($employees as $employee)
                    <option value="{{ $employee->id }}" @selected((int) $filters['employee_id'] === (int) $employee->id)>{{ $employee->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6 col-xl-2">
            <label class="form-label">Status</label>
            <select class="form-select" name="status">
                <option value="">Default statuses</option>
                @foreach(['draft','submitted','approved','rejected','cancellation_requested','recalled','cancelled','voided'] as $status)
                    <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6 col-xl-2">
            <label class="form-label">Leave type</label>
            <select class="form-select" name="attendance_code">
                <option value="">All leave types</option>
                @foreach($attendanceCodes as $code => $label)
                    <option value="{{ $code }}" @selected($filters['attendance_code'] === $code)>{{ $code }} - {{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-xl-2 d-flex justify-content-end gap-2">
            <button class="btn btn-primary flex-fill">Filter</button>
            <a class="btn btn-outline-secondary" href="{{ route($resetRoute, ['month' => $month->format('Y-m')]) }}">Reset</a>
        </div>
    </div>
</form>
