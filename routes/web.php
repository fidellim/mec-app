<?php

use App\Http\Controllers\AdminHodTimesheetController;
use App\Http\Controllers\AdminAnnualLeaveCarryOverController;
use App\Http\Controllers\AdminLeavePlanController;
use App\Http\Controllers\AdminTimesheetController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeLeavePlanController;
use App\Http\Controllers\EmployeeTimesheetController;
use App\Http\Controllers\GuideController;
use App\Http\Controllers\HodLeavePlanController;
use App\Http\Controllers\HodTimesheetController;
use App\Http\Controllers\Manage\AuditLogController;
use App\Http\Controllers\Manage\AutomationSettingController;
use App\Http\Controllers\Manage\DepartmentController;
use App\Http\Controllers\Manage\HolidayController;
use App\Http\Controllers\Manage\LeavePlanApproverController;
use App\Http\Controllers\Manage\LeaveSettingController;
use App\Http\Controllers\Manage\ProjectController;
use App\Http\Controllers\ProjectUtilizationController;
use App\Http\Controllers\TimesheetCorrectionRequestController;
use App\Http\Controllers\Manage\SystemSettingController;
use App\Http\Controllers\Manage\TimesheetPeriodController;
use App\Http\Controllers\Manage\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendPasswordResetLink'])->middleware('throttle:forgot-password')->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:reset-password')->name('password.update');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'setup.mode'])->group(function () {
    Route::get('/setup-in-progress', fn () => view('setup.in-progress'))->name('setup.in-progress');

    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/guide', GuideController::class)->name('guide');

    Route::middleware('role:employee,hod,admin,super_admin')->prefix('my-timesheets')->name('employee.timesheets.')->group(function () {
        Route::get('/', [EmployeeTimesheetController::class, 'index'])->name('index');
        Route::get('/create', [EmployeeTimesheetController::class, 'create'])->name('create');
        Route::post('/', [EmployeeTimesheetController::class, 'store'])->middleware('throttle:authenticated-writes')->name('store');
        Route::get('/{timesheet}/history', [EmployeeTimesheetController::class, 'history'])->name('history');
        Route::get('/{timesheet}', [EmployeeTimesheetController::class, 'show'])->name('show');
        Route::get('/{timesheet}/edit', [EmployeeTimesheetController::class, 'edit'])->name('edit');
        Route::put('/{timesheet}', [EmployeeTimesheetController::class, 'update'])->middleware('throttle:authenticated-writes')->name('update');
        Route::post('/{timesheet}/recall', [EmployeeTimesheetController::class, 'recall'])->middleware('throttle:workflow-actions')->name('recall');
        Route::delete('/{timesheet}', [EmployeeTimesheetController::class, 'destroy'])->middleware('throttle:authenticated-writes')->name('destroy');
    });

    Route::middleware('role:employee,hod')->get('/my-managed-projects', [ProjectUtilizationController::class, 'index'])->name('managed-projects.index');
    Route::middleware('role:employee,hod,admin,super_admin')->get('/projects/{project}/utilization', [ProjectUtilizationController::class, 'show'])->name('projects.utilization');
    Route::middleware('role:employee,hod')->post('/timesheet-correction-requests', [TimesheetCorrectionRequestController::class, 'store'])->middleware('throttle:workflow-actions')->name('timesheet-corrections.store');
    Route::middleware('role:employee,hod')->post('/timesheet-correction-requests/{correctionRequest}/withdraw', [TimesheetCorrectionRequestController::class, 'withdraw'])->middleware('throttle:workflow-actions')->name('timesheet-corrections.withdraw');
    Route::middleware('role:hod,admin,super_admin')->post('/timesheets/{timesheet}/correction-requests/resolve', [TimesheetCorrectionRequestController::class, 'resolve'])->middleware('throttle:workflow-actions')->name('timesheet-corrections.resolve');

    Route::middleware('role:employee,hod,admin,super_admin')->prefix('my-leave-plans')->name('employee.leave-plans.')->group(function () {
        Route::get('/', [EmployeeLeavePlanController::class, 'index'])->name('index');
        Route::get('/calendar', [EmployeeLeavePlanController::class, 'calendar'])->name('calendar');
        Route::get('/create', [EmployeeLeavePlanController::class, 'create'])->name('create');
        Route::post('/', [EmployeeLeavePlanController::class, 'store'])->middleware('throttle:authenticated-writes')->name('store');
        Route::get('/{leavePlan}/history', [EmployeeLeavePlanController::class, 'history'])->name('history');
        Route::get('/{leavePlan}', [EmployeeLeavePlanController::class, 'show'])->name('show');
        Route::get('/{leavePlan}/edit', [EmployeeLeavePlanController::class, 'edit'])->name('edit');
        Route::put('/{leavePlan}', [EmployeeLeavePlanController::class, 'update'])->middleware('throttle:authenticated-writes')->name('update');
        Route::post('/{leavePlan}/cancel-request', [EmployeeLeavePlanController::class, 'requestCancellation'])->middleware('throttle:workflow-actions')->name('cancel-request');
        Route::delete('/{leavePlan}', [EmployeeLeavePlanController::class, 'destroy'])->middleware('throttle:authenticated-writes')->name('destroy');
    });

    Route::middleware('role:employee,hod,admin,super_admin')->prefix('assigned-leave-plans')->name('assigned.leave-plans.')->group(function () {
        Route::get('/', [HodLeavePlanController::class, 'assignedIndex'])->name('index');
        Route::get('/{leavePlan}/history', [HodLeavePlanController::class, 'history'])->name('history');
        Route::get('/{leavePlan}', [HodLeavePlanController::class, 'assignedShow'])->name('show');
        Route::post('/{leavePlan}/approve', [HodLeavePlanController::class, 'approve'])->middleware('throttle:workflow-actions')->name('approve');
        Route::post('/{leavePlan}/reject', [HodLeavePlanController::class, 'reject'])->middleware('throttle:workflow-actions')->name('reject');
        Route::post('/{leavePlan}/approve-cancellation', [HodLeavePlanController::class, 'approveCancellation'])->middleware('throttle:workflow-actions')->name('approve-cancellation');
        Route::post('/{leavePlan}/reject-cancellation', [HodLeavePlanController::class, 'rejectCancellation'])->middleware('throttle:workflow-actions')->name('reject-cancellation');
    });

    Route::middleware('role:hod')->prefix('department')->name('hod.')->group(function () {
        Route::get('/timesheets', [HodTimesheetController::class, 'index'])->name('timesheets.index');
        Route::get('/timesheets/{timesheet}/history', [HodTimesheetController::class, 'history'])->name('timesheets.history');
        Route::get('/timesheets/{timesheet}', [HodTimesheetController::class, 'show'])->name('timesheets.show');
        Route::post('/timesheets/{timesheet}/approve', [HodTimesheetController::class, 'approve'])->middleware('throttle:workflow-actions')->name('timesheets.approve');
        Route::post('/timesheets/{timesheet}/reject', [HodTimesheetController::class, 'reject'])->middleware('throttle:workflow-actions')->name('timesheets.reject');
        Route::post('/timesheets/{timesheet}/recall-approved', [HodTimesheetController::class, 'recallApproved'])->middleware('throttle:workflow-actions')->name('timesheets.recall-approved');
        Route::get('/tracker', [HodTimesheetController::class, 'tracker'])->name('tracker');
        Route::post('/tracker/reminders', [HodTimesheetController::class, 'remindMissing'])->middleware('throttle:manual-reminders')->name('tracker.reminders');
        Route::get('/leave-plans', [HodLeavePlanController::class, 'index'])->name('leave-plans.index');
        Route::get('/leave-entitlements', [HodLeavePlanController::class, 'leaveEntitlements'])->name('leave-entitlements.index');
        Route::get('/leave-plans/calendar', [HodLeavePlanController::class, 'calendar'])->name('leave-plans.calendar');
        Route::get('/leave-plans/{leavePlan}/history', [HodLeavePlanController::class, 'history'])->name('leave-plans.history');
        Route::get('/leave-plans/{leavePlan}', [HodLeavePlanController::class, 'show'])->name('leave-plans.show');
        Route::post('/leave-plans/{leavePlan}/approve', [HodLeavePlanController::class, 'approve'])->middleware('throttle:workflow-actions')->name('leave-plans.approve');
        Route::post('/leave-plans/{leavePlan}/reject', [HodLeavePlanController::class, 'reject'])->middleware('throttle:workflow-actions')->name('leave-plans.reject');
        Route::post('/leave-plans/{leavePlan}/recall-approved', [HodLeavePlanController::class, 'recallApproved'])->middleware('throttle:workflow-actions')->name('leave-plans.recall-approved');
        Route::post('/leave-plans/{leavePlan}/approve-cancellation', [HodLeavePlanController::class, 'approveCancellation'])->middleware('throttle:workflow-actions')->name('leave-plans.approve-cancellation');
        Route::post('/leave-plans/{leavePlan}/reject-cancellation', [HodLeavePlanController::class, 'rejectCancellation'])->middleware('throttle:workflow-actions')->name('leave-plans.reject-cancellation');
    });

    Route::middleware('role:admin,super_admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/timesheets', [AdminTimesheetController::class, 'index'])->name('timesheets.index');
        Route::get('/timesheets/export', [AdminTimesheetController::class, 'export'])->middleware('throttle:exports')->name('timesheets.export');
        Route::get('/timesheets/{timesheet}/history', [AdminTimesheetController::class, 'history'])->name('timesheets.history');
        Route::get('/timesheets/{timesheet}', [AdminTimesheetController::class, 'show'])->name('timesheets.show');
        Route::post('/timesheets/{timesheet}/recall-approved', [AdminTimesheetController::class, 'recallApproved'])->middleware('throttle:workflow-actions')->name('timesheets.recall-approved');
        Route::post('/timesheets/{timesheet}/void', [AdminTimesheetController::class, 'voidTimesheet'])->middleware('role:super_admin', 'throttle:workflow-actions')->name('timesheets.void');
        Route::get('/hod-timesheets', [AdminHodTimesheetController::class, 'index'])->name('hod-timesheets.index');
        Route::get('/hod-submission-tracker', [AdminHodTimesheetController::class, 'tracker'])->name('hod-tracker');
        Route::post('/hod-submission-tracker/reminders', [AdminHodTimesheetController::class, 'remindMissing'])->middleware('throttle:manual-reminders')->name('hod-tracker.reminders');
        Route::get('/leave-entitlements', [AdminLeavePlanController::class, 'leaveEntitlements'])->name('leave-entitlements.index');
        Route::get('/annual-leave-carry-overs', [AdminAnnualLeaveCarryOverController::class, 'index'])->name('annual-leave-carry-overs.index');
        Route::post('/annual-leave-carry-overs', [AdminAnnualLeaveCarryOverController::class, 'store'])->middleware('throttle:authenticated-writes')->name('annual-leave-carry-overs.store');
        Route::post('/annual-leave-carry-overs/import', [AdminAnnualLeaveCarryOverController::class, 'import'])->middleware('throttle:authenticated-writes')->name('annual-leave-carry-overs.import');
        Route::post('/annual-leave-carry-overs/generate', [AdminAnnualLeaveCarryOverController::class, 'generate'])->middleware('throttle:authenticated-writes')->name('annual-leave-carry-overs.generate');
        Route::post('/annual-leave-carry-overs/{carryOver}/approve', [AdminAnnualLeaveCarryOverController::class, 'approve'])->middleware('throttle:workflow-actions')->name('annual-leave-carry-overs.approve');
        Route::post('/annual-leave-carry-overs/{carryOver}/reject', [AdminAnnualLeaveCarryOverController::class, 'reject'])->middleware('throttle:workflow-actions')->name('annual-leave-carry-overs.reject');
        Route::post('/annual-leave-carry-overs/{carryOver}/void', [AdminAnnualLeaveCarryOverController::class, 'void'])->middleware('throttle:workflow-actions')->name('annual-leave-carry-overs.void');
        Route::get('/leave-plans', [AdminLeavePlanController::class, 'index'])->name('leave-plans.index');
        Route::get('/leave-plans/create', [AdminLeavePlanController::class, 'create'])->name('leave-plans.create');
        Route::post('/leave-plans', [AdminLeavePlanController::class, 'store'])->middleware('throttle:authenticated-writes')->name('leave-plans.store');
        Route::get('/leave-plans/import', [AdminLeavePlanController::class, 'import'])->name('leave-plans.import');
        Route::post('/leave-plans/import/preview', [AdminLeavePlanController::class, 'previewImport'])->middleware('throttle:authenticated-writes')->name('leave-plans.import.preview');
        Route::post('/leave-plans/import', [AdminLeavePlanController::class, 'storeImport'])->middleware('throttle:authenticated-writes')->name('leave-plans.import.store');
        Route::get('/leave-plans/export', [AdminLeavePlanController::class, 'export'])->middleware('throttle:exports')->name('leave-plans.export');
        Route::get('/leave-plans/calendar', [AdminLeavePlanController::class, 'calendar'])->name('leave-plans.calendar');
        Route::get('/leave-plans/{leavePlan}/history', [AdminLeavePlanController::class, 'history'])->name('leave-plans.history');
        Route::get('/leave-plans/{leavePlan}', [AdminLeavePlanController::class, 'show'])->name('leave-plans.show');
        Route::post('/leave-plans/{leavePlan}/approve', [HodLeavePlanController::class, 'approve'])->middleware('throttle:workflow-actions')->name('leave-plans.approve');
        Route::post('/leave-plans/{leavePlan}/reject', [HodLeavePlanController::class, 'reject'])->middleware('throttle:workflow-actions')->name('leave-plans.reject');
        Route::post('/leave-plans/{leavePlan}/recall-approved', [HodLeavePlanController::class, 'recallApproved'])->middleware('throttle:workflow-actions')->name('leave-plans.recall-approved');
        Route::post('/leave-plans/{leavePlan}/void', [HodLeavePlanController::class, 'voidApproved'])->middleware('role:super_admin', 'throttle:workflow-actions')->name('leave-plans.void');
        Route::post('/leave-plans/{leavePlan}/approve-cancellation', [HodLeavePlanController::class, 'approveCancellation'])->middleware('throttle:workflow-actions')->name('leave-plans.approve-cancellation');
        Route::post('/leave-plans/{leavePlan}/reject-cancellation', [HodLeavePlanController::class, 'rejectCancellation'])->middleware('throttle:workflow-actions')->name('leave-plans.reject-cancellation');
        Route::middleware('role:admin,super_admin')->group(function () {
            Route::post('/timesheets/{timesheet}/approve', [HodTimesheetController::class, 'approve'])->middleware('throttle:workflow-actions')->name('timesheets.approve');
            Route::post('/timesheets/{timesheet}/reject', [HodTimesheetController::class, 'reject'])->middleware('throttle:workflow-actions')->name('timesheets.reject');
        });
    });

    Route::middleware('role:admin,super_admin')->prefix('manage')->name('manage.')->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::match(['put', 'patch'], 'users/{user}', [UserController::class, 'update'])->middleware('throttle:authenticated-writes')->name('users.update');
        Route::patch('holidays/{holiday}/status', [HolidayController::class, 'status'])->middleware('throttle:authenticated-writes')->name('holidays.status');
        Route::get('holidays', [HolidayController::class, 'index'])->name('holidays.index');
        Route::get('holidays/create', [HolidayController::class, 'create'])->name('holidays.create');
        Route::post('holidays', [HolidayController::class, 'store'])->middleware('throttle:authenticated-writes')->name('holidays.store');
        Route::get('holidays/{holiday}/edit', [HolidayController::class, 'edit'])->name('holidays.edit');
        Route::match(['put', 'patch'], 'holidays/{holiday}', [HolidayController::class, 'update'])->middleware('throttle:authenticated-writes')->name('holidays.update');
        Route::get('leave-settings', [LeaveSettingController::class, 'index'])->name('leave-settings.index');
        Route::patch('leave-settings', [LeaveSettingController::class, 'update'])->middleware('throttle:authenticated-writes')->name('leave-settings.update');
        Route::patch('projects/{project}/status', [ProjectController::class, 'status'])->middleware('throttle:authenticated-writes')->name('projects.status');
        Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
        Route::get('projects/create', [ProjectController::class, 'create'])->name('projects.create');
        Route::post('projects', [ProjectController::class, 'store'])->middleware('throttle:authenticated-writes')->name('projects.store');
        Route::get('projects/{project}/edit', [ProjectController::class, 'edit'])->name('projects.edit');
        Route::match(['put', 'patch'], 'projects/{project}', [ProjectController::class, 'update'])->middleware('throttle:authenticated-writes')->name('projects.update');
        Route::delete('projects/{project}', [ProjectController::class, 'destroy'])->middleware('role:super_admin', 'throttle:authenticated-writes')->name('projects.destroy');
    });

    Route::middleware('role:super_admin')->prefix('manage')->name('manage.')->group(function () {
        Route::get('users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('users', [UserController::class, 'store'])->middleware('throttle:authenticated-writes')->name('users.store');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->middleware('throttle:authenticated-writes')->name('users.destroy');
        Route::patch('departments/{department}/status', [DepartmentController::class, 'status'])->middleware('throttle:authenticated-writes')->name('departments.status');
        Route::get('departments', [DepartmentController::class, 'index'])->name('departments.index');
        Route::get('departments/create', [DepartmentController::class, 'create'])->name('departments.create');
        Route::post('departments', [DepartmentController::class, 'store'])->middleware('throttle:authenticated-writes')->name('departments.store');
        Route::get('departments/{department}/edit', [DepartmentController::class, 'edit'])->name('departments.edit');
        Route::match(['put', 'patch'], 'departments/{department}', [DepartmentController::class, 'update'])->middleware('throttle:authenticated-writes')->name('departments.update');
        Route::delete('departments/{department}', [DepartmentController::class, 'destroy'])->middleware('throttle:authenticated-writes')->name('departments.destroy');
        Route::get('periods', [TimesheetPeriodController::class, 'index'])->name('periods.index');
        Route::get('periods/create', [TimesheetPeriodController::class, 'create'])->name('periods.create');
        Route::post('periods', [TimesheetPeriodController::class, 'store'])->middleware('throttle:authenticated-writes')->name('periods.store');
        Route::get('periods/{period}/edit', [TimesheetPeriodController::class, 'edit'])->name('periods.edit');
        Route::match(['put', 'patch'], 'periods/{period}', [TimesheetPeriodController::class, 'update'])->middleware('throttle:authenticated-writes')->name('periods.update');
        Route::get('leave-plan-approvers', [LeavePlanApproverController::class, 'index'])->name('leave-plan-approvers.index');
        Route::patch('leave-plan-approvers', [LeavePlanApproverController::class, 'update'])->middleware('throttle:authenticated-writes')->name('leave-plan-approvers.update');
        Route::get('automations', [AutomationSettingController::class, 'index'])->name('automations.index');
        Route::patch('automations/{automation}/toggle', [AutomationSettingController::class, 'toggle'])->middleware('throttle:authenticated-writes')->name('automations.toggle');
        Route::get('system-settings', [SystemSettingController::class, 'index'])->name('system-settings.index');
        Route::patch('system-settings/setup-mode', [SystemSettingController::class, 'setupMode'])->middleware('throttle:authenticated-writes')->name('system-settings.setup-mode');
        Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
        Route::get('audit-logs/export', [AuditLogController::class, 'export'])->middleware('throttle:exports')->name('audit-logs.export');
        Route::delete('audit-logs/selected', [AuditLogController::class, 'destroySelected'])->middleware('throttle:authenticated-writes')->name('audit-logs.destroy-selected');
        Route::delete('audit-logs/matching', [AuditLogController::class, 'destroyMatching'])->middleware('throttle:authenticated-writes')->name('audit-logs.destroy-matching');
    });

    Route::middleware('role:admin,super_admin')->prefix('manage')->name('manage.')->group(function () {
        Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
    });
});
