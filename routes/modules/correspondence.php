<?php

use App\Http\Controllers\Correspondence\CorrespondenceActionController;
use App\Http\Controllers\Correspondence\CorrespondenceController;
use Illuminate\Support\Facades\Route;

/*
| Official Correspondence Management (SRS 3.6). Record-level access, including
| confidentiality, is enforced by CorrespondencePolicy.
*/
Route::middleware('permission:correspondence.view')->group(function () {
    Route::get('correspondence/register', [CorrespondenceController::class, 'register'])->name('correspondence.register');

    Route::resource('correspondence', CorrespondenceController::class)
        ->only(['create', 'store'])
        ->middleware('permission:correspondence.create');

    Route::resource('correspondence', CorrespondenceController::class)
        ->only(['edit', 'update'])
        ->parameters(['correspondence' => 'correspondence'])
        ->middleware('permission:correspondence.update');

    Route::resource('correspondence', CorrespondenceController::class)
        ->only(['destroy'])
        ->parameters(['correspondence' => 'correspondence'])
        ->middleware('permission:correspondence.delete');

    Route::resource('correspondence', CorrespondenceController::class)
        ->only(['index', 'show'])
        ->parameters(['correspondence' => 'correspondence']);

    // Follow-up workflow; each action is authorised by the policy.
    Route::prefix('correspondence/{correspondence}')->name('correspondence.')->controller(CorrespondenceActionController::class)->group(function () {
        Route::post('assign', 'assign')->name('assign');
        Route::post('forward', 'forward')->name('forward');
        Route::post('respond', 'respond')->name('respond');
        Route::post('send', 'markSent')->name('send');
        Route::post('close', 'close')->name('close');
        Route::post('note', 'note')->name('note');
    });
});
