<?php

use App\Http\Controllers\Correspondence\CorrespondenceActionController;
use App\Http\Controllers\Correspondence\CorrespondenceController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:correspondence.view')->group(function () {
    Route::resource('correspondence', CorrespondenceController::class);

    Route::post('/correspondence/{correspondence}/forward', [CorrespondenceActionController::class, 'forward'])->name('correspondence.forward');
    Route::post('/correspondence/{correspondence}/follow-up', [CorrespondenceActionController::class, 'followUp'])->name('correspondence.follow-up');
    Route::post('/correspondence/{correspondence}/status', [CorrespondenceActionController::class, 'changeStatus'])->name('correspondence.status');
});
