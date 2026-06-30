<?php

use App\Http\Controllers\AdminTimesheetController;
use App\Http\Controllers\AdminHodTimesheetController;
use App\Http\Controllers\AdminLeavePlanController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeLeavePlanController;
use App\Http\Controllers\EmployeeTimesheetController;
use App\Http\Controllers\GuideController;
use App\Http\Controllers\HodLeavePlanController;
use App\Http\Controllers\HodTimesheetController;
use App\Http\Controllers\Manage\DepartmentController;
use App\Http\Controllers\Manage\AuditLogController;
use App\Http\Controllers\Manage\AutomationSettingController;
use App\Http\Controllers\Manage\HolidayController;
use App\Http\Controllers\Manage\LeaveSettingController;
use App\Http\Controllers\Manage\LeavePlanApproverController;
use App\Http\Controllers\Manage\ProjectController;
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

Route::middleware('auth')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/guide', GuideController::class)->name('guide');

    Route::middleware('role:employee,hod,admin,super_admin')->prefix('my-timesheets')->name('employee.timesheets.')->group(function () {
        Route::get('/', [EmployeeTimesheetController::class, 'index'])->name('index');
        Route::get('/create', [EmployeeTimesheetController::class, 'create'])->name('create');
        Route::post('/', [EmployeeTimesheetController::class, 'store'])->name('store');
        Route::get('/{timesheet}/history', [EmployeeTimesheetController::class, 'history'])->name('history');
        Route::get('/{timesheet}', [EmployeeTimesheetController::class, 'show'])->name('show');
        Route::get('/{timesheet}/edit', [EmployeeTimesheetController::class, 'edit'])->name('edit');
        Route::put('/{timesheet}', [EmployeeTimesheetController::class, 'update'])->name('update');
        Route::post('/{timesheet}/recall', [EmployeeTimesheetController::class, 'recall'])->name('recall');
        Route::delete('/{timesheet}', [EmployeeTimesheetController::class, 'destroy'])->name('destroy');
    });

    Route::middleware('role:employee,hod,admin,super_admin')->prefix('my-leave-plans')->name('employee.leave-plans.')->group(function () {
        Route::get('/', [EmployeeLeavePlanController::class, 'index'])->name('index');
        Route::get('/calendar', [EmployeeLeavePlanController::class, 'calendar'])->name('calendar');
        Route::get('/create', [EmployeeLeavePlanController::class, 'create'])->name('create');
        Route::post('/', [EmployeeLeavePlanController::class, 'store'])->name('store');
        Route::get('/{leavePlan}', [EmployeeLeavePlanController::class, 'show'])->name('show');
        Route::get('/{leavePlan}/edit', [EmployeeLeavePlanController::class, 'edit'])->name('edit');
        Route::put('/{leavePlan}', [EmployeeLeavePlanController::class, 'update'])->name('update');
        Route::post('/{leavePlan}/cancel-request', [EmployeeLeavePlanController::class, 'requestCancellation'])->name('cancel-request');
        Route::delete('/{leavePlan}', [EmployeeLeavePlanController::class, 'destroy'])->name('destroy');
    });

    Route::middleware('role:employee,hod,admin,super_admin')->prefix('assigned-leave-plans')->name('assigned.leave-plans.')->group(function () {
        Route::get('/', [HodLeavePlanController::class, 'assignedIndex'])->name('index');
        Route::get('/{leavePlan}', [HodLeavePlanController::class, 'assignedShow'])->name('show');
        Route::post('/{leavePlan}/approve', [HodLeavePlanController::class, 'approve'])->name('approve');
        Route::post('/{leavePlan}/reject', [HodLeavePlanController::class, 'reject'])->name('reject');
    });

    Route::middleware('role:hod')->prefix('department')->name('hod.')->group(function () {
        Route::get('/timesheets', [HodTimesheetController::class, 'index'])->name('timesheets.index');
        Route::get('/timesheets/{timesheet}/history', [HodTimesheetController::class, 'history'])->name('timesheets.history');
        Route::get('/timesheets/{timesheet}', [HodTimesheetController::class, 'show'])->name('timesheets.show');
        Route::post('/timesheets/{timesheet}/approve', [HodTimesheetController::class, 'approve'])->name('timesheets.approve');
        Route::post('/timesheets/{timesheet}/reject', [HodTimesheetController::class, 'reject'])->name('timesheets.reject');
        Route::post('/timesheets/{timesheet}/recall-approved', [HodTimesheetController::class, 'recallApproved'])->name('timesheets.recall-approved');
        Route::get('/tracker', [HodTimesheetController::class, 'tracker'])->name('tracker');
        Route::post('/tracker/reminders', [HodTimesheetController::class, 'remindMissing'])->name('tracker.reminders');
        Route::get('/leave-plans', [HodLeavePlanController::class, 'index'])->name('leave-plans.index');
        Route::get('/leave-plans/calendar', [HodLeavePlanController::class, 'calendar'])->name('leave-plans.calendar');
        Route::get('/leave-plans/{leavePlan}', [HodLeavePlanController::class, 'show'])->name('leave-plans.show');
        Route::post('/leave-plans/{leavePlan}/approve', [HodLeavePlanController::class, 'approve'])->name('leave-plans.approve');
        Route::post('/leave-plans/{leavePlan}/reject', [HodLeavePlanController::class, 'reject'])->name('leave-plans.reject');
        Route::post('/leave-plans/{leavePlan}/recall-approved', [HodLeavePlanController::class, 'recallApproved'])->name('leave-plans.recall-approved');
        Route::post('/leave-plans/{leavePlan}/approve-cancellation', [HodLeavePlanController::class, 'approveCancellation'])->name('leave-plans.approve-cancellation');
        Route::post('/leave-plans/{leavePlan}/reject-cancellation', [HodLeavePlanController::class, 'rejectCancellation'])->name('leave-plans.reject-cancellation');
    });

    Route::middleware('role:admin,super_admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/timesheets', [AdminTimesheetController::class, 'index'])->name('timesheets.index');
        Route::get('/timesheets/export', [AdminTimesheetController::class, 'export'])->middleware('throttle:exports')->name('timesheets.export');
        Route::get('/timesheets/{timesheet}/history', [AdminTimesheetController::class, 'history'])->name('timesheets.history');
        Route::get('/timesheets/{timesheet}', [AdminTimesheetController::class, 'show'])->name('timesheets.show');
        Route::post('/timesheets/{timesheet}/recall-approved', [AdminTimesheetController::class, 'recallApproved'])->name('timesheets.recall-approved');
        Route::post('/timesheets/{timesheet}/void', [AdminTimesheetController::class, 'voidTimesheet'])->middleware('role:super_admin')->name('timesheets.void');
        Route::get('/hod-timesheets', [AdminHodTimesheetController::class, 'index'])->name('hod-timesheets.index');
        Route::get('/hod-submission-tracker', [AdminHodTimesheetController::class, 'tracker'])->name('hod-tracker');
        Route::post('/hod-submission-tracker/reminders', [AdminHodTimesheetController::class, 'remindMissing'])->name('hod-tracker.reminders');
        Route::get('/leave-plans', [AdminLeavePlanController::class, 'index'])->name('leave-plans.index');
        Route::get('/leave-plans/calendar', [AdminLeavePlanController::class, 'calendar'])->name('leave-plans.calendar');
        Route::get('/leave-plans/{leavePlan}', [AdminLeavePlanController::class, 'show'])->name('leave-plans.show');
        Route::post('/leave-plans/{leavePlan}/approve', [HodLeavePlanController::class, 'approve'])->name('leave-plans.approve');
        Route::post('/leave-plans/{leavePlan}/reject', [HodLeavePlanController::class, 'reject'])->name('leave-plans.reject');
        Route::post('/leave-plans/{leavePlan}/recall-approved', [HodLeavePlanController::class, 'recallApproved'])->name('leave-plans.recall-approved');
        Route::post('/leave-plans/{leavePlan}/void', [HodLeavePlanController::class, 'voidApproved'])->middleware('role:super_admin')->name('leave-plans.void');
        Route::post('/leave-plans/{leavePlan}/approve-cancellation', [HodLeavePlanController::class, 'approveCancellation'])->name('leave-plans.approve-cancellation');
        Route::post('/leave-plans/{leavePlan}/reject-cancellation', [HodLeavePlanController::class, 'rejectCancellation'])->name('leave-plans.reject-cancellation');
        Route::middleware('role:admin,super_admin')->group(function () {
            Route::post('/timesheets/{timesheet}/approve', [HodTimesheetController::class, 'approve'])->name('timesheets.approve');
            Route::post('/timesheets/{timesheet}/reject', [HodTimesheetController::class, 'reject'])->name('timesheets.reject');
        });
    });

    Route::middleware('role:admin,super_admin')->prefix('manage')->name('manage.')->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::patch('holidays/{holiday}/status', [HolidayController::class, 'status'])->name('holidays.status');
        Route::resource('holidays', HolidayController::class)->except(['show', 'destroy']);
    });

    Route::middleware('role:super_admin')->prefix('manage')->name('manage.')->group(function () {
        Route::resource('users', UserController::class)->except(['index', 'show']);
        Route::patch('departments/{department}/status', [DepartmentController::class, 'status'])->name('departments.status');
        Route::resource('departments', DepartmentController::class)->except(['show']);
        Route::patch('projects/{project}/status', [ProjectController::class, 'status'])->name('projects.status');
        Route::resource('projects', ProjectController::class)->except(['show']);
        Route::resource('periods', TimesheetPeriodController::class)->except(['show', 'destroy'])->parameters(['periods' => 'period']);
        Route::get('leave-plan-approvers', [LeavePlanApproverController::class, 'index'])->name('leave-plan-approvers.index');
        Route::patch('leave-plan-approvers', [LeavePlanApproverController::class, 'update'])->name('leave-plan-approvers.update');
        Route::get('leave-settings', [LeaveSettingController::class, 'index'])->name('leave-settings.index');
        Route::patch('leave-settings', [LeaveSettingController::class, 'update'])->name('leave-settings.update');
        Route::get('automations', [AutomationSettingController::class, 'index'])->name('automations.index');
        Route::patch('automations/{automation}/toggle', [AutomationSettingController::class, 'toggle'])->name('automations.toggle');
        Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
        Route::get('audit-logs/export', [AuditLogController::class, 'export'])->middleware('throttle:exports')->name('audit-logs.export');
        Route::delete('audit-logs/selected', [AuditLogController::class, 'destroySelected'])->name('audit-logs.destroy-selected');
        Route::delete('audit-logs/matching', [AuditLogController::class, 'destroyMatching'])->name('audit-logs.destroy-matching');
    });

    Route::middleware('role:admin,super_admin')->prefix('manage')->name('manage.')->group(function () {
        Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
    });
});
