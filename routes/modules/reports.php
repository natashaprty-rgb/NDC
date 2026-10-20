<?php

use App\Http\Controllers\Reports\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:reports.view')->prefix('reports')->name('reports.')->group(function () {
    Route::get('/', [ReportController::class, 'index'])->name('index');
    Route::get('{key}', [ReportController::class, 'show'])->name('show');
    Route::get('{key}/csv', [ReportController::class, 'csv'])->name('csv');
    Route::get('{key}/pdf', [ReportController::class, 'pdf'])->name('pdf');
});
