<?php

use App\Http\Controllers\AdminTimesheetController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeTimesheetController;
use App\Http\Controllers\GuideController;
use App\Http\Controllers\HodTimesheetController;
use App\Http\Controllers\Manage\DepartmentController;
use App\Http\Controllers\Manage\AuditLogController;
use App\Http\Controllers\Manage\AutomationSettingController;
use App\Http\Controllers\Manage\ProjectController;
use App\Http\Controllers\Manage\TimesheetPeriodController;
use App\Http\Controllers\Manage\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendPasswordResetLink'])->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/guide', GuideController::class)->name('guide');

    Route::middleware('role:employee,hod,admin,super_admin')->prefix('my-timesheets')->name('employee.timesheets.')->group(function () {
        Route::get('/', [EmployeeTimesheetController::class, 'index'])->name('index');
        Route::get('/create', [EmployeeTimesheetController::class, 'create'])->name('create');
        Route::post('/', [EmployeeTimesheetController::class, 'store'])->name('store');
        Route::get('/{timesheet}', [EmployeeTimesheetController::class, 'show'])->name('show');
        Route::get('/{timesheet}/edit', [EmployeeTimesheetController::class, 'edit'])->name('edit');
        Route::put('/{timesheet}', [EmployeeTimesheetController::class, 'update'])->name('update');
        Route::post('/{timesheet}/recall', [EmployeeTimesheetController::class, 'recall'])->name('recall');
        Route::delete('/{timesheet}', [EmployeeTimesheetController::class, 'destroy'])->name('destroy');
    });

    Route::middleware('role:hod')->prefix('department')->name('hod.')->group(function () {
        Route::get('/timesheets', [HodTimesheetController::class, 'index'])->name('timesheets.index');
        Route::get('/timesheets/{timesheet}', [HodTimesheetController::class, 'show'])->name('timesheets.show');
        Route::post('/timesheets/{timesheet}/approve', [HodTimesheetController::class, 'approve'])->name('timesheets.approve');
        Route::post('/timesheets/{timesheet}/reject', [HodTimesheetController::class, 'reject'])->name('timesheets.reject');
        Route::get('/tracker', [HodTimesheetController::class, 'tracker'])->name('tracker');
        Route::post('/tracker/reminders', [HodTimesheetController::class, 'remindMissing'])->name('tracker.reminders');
    });

    Route::middleware('role:admin,super_admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/timesheets', [AdminTimesheetController::class, 'index'])->name('timesheets.index');
        Route::get('/timesheets/export', [AdminTimesheetController::class, 'export'])->name('timesheets.export');
        Route::get('/timesheets/{timesheet}', [AdminTimesheetController::class, 'show'])->name('timesheets.show');
        Route::middleware('role:admin,super_admin')->group(function () {
            Route::post('/timesheets/{timesheet}/approve', [HodTimesheetController::class, 'approve'])->name('timesheets.approve');
            Route::post('/timesheets/{timesheet}/reject', [HodTimesheetController::class, 'reject'])->name('timesheets.reject');
        });
    });

    Route::middleware('role:super_admin')->prefix('manage')->name('manage.')->group(function () {
        Route::resource('users', UserController::class)->except(['show']);
        Route::patch('departments/{department}/status', [DepartmentController::class, 'status'])->name('departments.status');
        Route::resource('departments', DepartmentController::class)->except(['show']);
        Route::patch('projects/{project}/status', [ProjectController::class, 'status'])->name('projects.status');
        Route::resource('projects', ProjectController::class)->except(['show']);
        Route::resource('periods', TimesheetPeriodController::class)->except(['show', 'destroy'])->parameters(['periods' => 'period']);
        Route::get('automations', [AutomationSettingController::class, 'index'])->name('automations.index');
        Route::patch('automations/{automation}/toggle', [AutomationSettingController::class, 'toggle'])->name('automations.toggle');
        Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
        Route::get('audit-logs/export', [AuditLogController::class, 'export'])->name('audit-logs.export');
        Route::delete('audit-logs/selected', [AuditLogController::class, 'destroySelected'])->name('audit-logs.destroy-selected');
        Route::delete('audit-logs/matching', [AuditLogController::class, 'destroyMatching'])->name('audit-logs.destroy-matching');
    });
});
