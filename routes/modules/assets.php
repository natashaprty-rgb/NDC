<?php

use App\Http\Controllers\Assets\AssetActionController;
use App\Http\Controllers\Assets\AssetController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:assets.view')->group(function () {
    Route::post('/assets/{asset}/movements', [AssetActionController::class, 'move'])->name('assets.movements.store');
    Route::post('/assets/{asset}/maintenance', [AssetActionController::class, 'storeMaintenance'])->name('assets.maintenance.store');
    Route::put('/assets/{asset}/maintenance/{maintenance}', [AssetActionController::class, 'updateMaintenance'])->name('assets.maintenance.update');
    Route::post('/assets/{asset}/retire', [AssetActionController::class, 'retire'])->name('assets.retire');
    Route::resource('assets', AssetController::class);
});
