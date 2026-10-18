<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\PermissionReviewController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('can:users.view')->group(function () {
        Route::resource('users', UserController::class)->except('destroy');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        Route::post('users/{user}/suspend', [UserController::class, 'suspend'])->name('users.suspend');
        Route::post('users/{user}/activate', [UserController::class, 'activate'])->name('users.activate');
        Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])->middleware('throttle:sensitive')->name('users.reset-password');
    });

    Route::resource('roles', RoleController::class);

    Route::middleware('can:permissions.review')->group(function () {
        Route::get('permission-reviews', [PermissionReviewController::class, 'index'])->name('permission-reviews.index');
        Route::get('permission-reviews/sheet', [PermissionReviewController::class, 'sheet'])->name('permission-reviews.sheet');
        Route::get('permission-reviews/sheet.csv', [PermissionReviewController::class, 'export'])->middleware('throttle:exports')->name('permission-reviews.export');
        Route::get('permission-reviews/create', [PermissionReviewController::class, 'create'])->name('permission-reviews.create');
        Route::post('permission-reviews', [PermissionReviewController::class, 'store'])->name('permission-reviews.store');
    });

    Route::middleware('can:audit.view')->group(function () {
        Route::get('audit', [AuditLogController::class, 'index'])->name('audit.index');
        Route::get('audit/logins', [AuditLogController::class, 'logins'])->name('audit.logins');
    });
});
