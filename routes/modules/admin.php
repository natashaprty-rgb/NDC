<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SystemController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('permission:users.manage')->group(function () {
        Route::resource('users', UserController::class)->except('destroy');
        Route::patch('users/{user}/status', [UserController::class, 'status'])->name('users.status');
        Route::post('users/{user}/send-reset', [UserController::class, 'sendReset'])->name('users.send-reset');
        Route::delete('users/{user}/two-factor', [UserController::class, 'resetTwoFactor'])->name('users.two-factor.reset');
    });

    Route::resource('roles', RoleController::class)->except('show')->middleware('permission:roles.manage');
    Route::resource('departments', DepartmentController::class)->except('show')->middleware('permission:departments.manage');

    Route::middleware('permission:settings.manage')->group(function () {
        Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
        Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
        Route::put('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
        Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
    });

    Route::middleware('permission:audit.view')->group(function () {
        Route::get('audit', [AuditLogController::class, 'index'])->name('audit.index');
        Route::get('audit/export', [AuditLogController::class, 'export'])->name('audit.export');
        Route::get('audit/{log}', [AuditLogController::class, 'show'])->name('audit.show');
    });

    Route::middleware('permission:system.manage')->group(function () {
        Route::get('system', [SystemController::class, 'index'])->name('system.index');
        Route::get('backups', [SystemController::class, 'backups'])->name('backups.index');
        Route::post('backups', [SystemController::class, 'runBackup'])->name('backups.store');
        Route::post('backups/{run}/verify', [SystemController::class, 'verify'])->name('backups.verify');
        Route::get('backups/{run}/download', [SystemController::class, 'download'])->name('backups.download');
    });
});
