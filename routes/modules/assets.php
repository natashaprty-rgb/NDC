<?php

use App\Http\Controllers\Assets\AssetAssignmentController;
use App\Http\Controllers\Assets\AssetController;
use App\Http\Controllers\Assets\AssetDisposalController;
use App\Http\Controllers\Assets\AssetMaintenanceController;
use App\Http\Controllers\Assets\AssetReportController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:assets.view')->group(function () {
    Route::get('assets/reports/inventory', [AssetReportController::class, 'inventory'])->name('assets.reports.inventory');
    Route::get('assets/reports/allocation', [AssetReportController::class, 'allocation'])->name('assets.reports.allocation');

    Route::resource('assets', AssetController::class);

    Route::post('assets/{asset}/assign', [AssetAssignmentController::class, 'assign'])->name('assets.assign');
    Route::post('assets/{asset}/return', [AssetAssignmentController::class, 'return'])->name('assets.return');
    Route::post('assets/{asset}/transfer', [AssetAssignmentController::class, 'transfer'])->name('assets.transfer');

    Route::scopeBindings()->group(function () {
        Route::post('assets/{asset}/maintenance', [AssetMaintenanceController::class, 'store'])->name('assets.maintenance.store');
        Route::post('assets/{asset}/maintenance/{maintenance}/start', [AssetMaintenanceController::class, 'start'])->name('assets.maintenance.start');
        Route::post('assets/{asset}/maintenance/{maintenance}/complete', [AssetMaintenanceController::class, 'complete'])->name('assets.maintenance.complete');
        Route::post('assets/{asset}/maintenance/{maintenance}/cancel', [AssetMaintenanceController::class, 'cancel'])->name('assets.maintenance.cancel');

        Route::post('assets/{asset}/disposals', [AssetDisposalController::class, 'store'])->name('assets.disposals.store');
        Route::post('assets/{asset}/disposals/{disposal}/complete', [AssetDisposalController::class, 'complete'])->name('assets.disposals.complete');
    });
});
