<?php

use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\LookupController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SystemController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('can:settings.manage')->group(function () {
        Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('/settings/{group}', [SettingsController::class, 'update'])->whereAlpha('group')->name('settings.update');

        $types = implode('|', array_keys(LookupController::types()));
        Route::get('/lookups/{type}', [LookupController::class, 'index'])->where('type', $types)->name('lookups.index');
        Route::get('/lookups/{type}/create', [LookupController::class, 'create'])->where('type', $types)->name('lookups.create');
        Route::post('/lookups/{type}', [LookupController::class, 'store'])->where('type', $types)->name('lookups.store');
        Route::get('/lookups/{type}/{id}/edit', [LookupController::class, 'edit'])->where('type', $types)->whereNumber('id')->name('lookups.edit');
        Route::put('/lookups/{type}/{id}', [LookupController::class, 'update'])->where('type', $types)->whereNumber('id')->name('lookups.update');
    });

    Route::get('/system', [SystemController::class, 'index'])->middleware('can:system.health')->name('system.index');

    Route::middleware('can:backups.manage')->group(function () {
        Route::get('/backups', [BackupController::class, 'index'])->name('backups.index');
        Route::post('/backups', [BackupController::class, 'store'])->name('backups.store');
        Route::put('/backups/{backup}/restore-test', [BackupController::class, 'restoreTest'])->name('backups.restore-test');
    });
});
