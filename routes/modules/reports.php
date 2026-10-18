<?php

use App\Http\Controllers\Reports\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:reports.view')->prefix('reports')->name('reports.')->group(function () {
    Route::get('/', [ReportController::class, 'index'])->name('index');
    Route::get('/{report}', [ReportController::class, 'show'])->where('report', '[a-z0-9-]+')->name('show');
    Route::get('/{report}/export', [ReportController::class, 'export'])->where('report', '[a-z0-9-]+')
        ->middleware('throttle:exports')->name('export');
});
