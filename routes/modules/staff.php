<?php

use App\Http\Controllers\Staff\StaffController;
use App\Http\Controllers\Staff\StaffTrainingController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:staff.view')->group(function () {
    // Printable staff directory grouped by department (registered before the
    // resource so "directory" is not taken for a staff id).
    Route::get('staff/directory', [StaffController::class, 'directory'])->name('staff.directory');

    Route::resource('staff', StaffController::class);

    Route::scopeBindings()->middleware('permission:staff.update')->group(function () {
        Route::post('staff/{staff}/trainings', [StaffTrainingController::class, 'store'])->name('staff.trainings.store');
        Route::get('staff/{staff}/trainings/{training}/edit', [StaffTrainingController::class, 'edit'])->name('staff.trainings.edit');
        Route::put('staff/{staff}/trainings/{training}', [StaffTrainingController::class, 'update'])->name('staff.trainings.update');
        Route::delete('staff/{staff}/trainings/{training}', [StaffTrainingController::class, 'destroy'])->name('staff.trainings.destroy');
    });
});
