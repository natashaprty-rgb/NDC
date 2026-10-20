<?php

use App\Http\Controllers\Documents\DocumentAccessController;
use App\Http\Controllers\Documents\DocumentController;
use App\Http\Controllers\Documents\DocumentVersionController;
use App\Http\Controllers\Documents\DocumentWorkflowController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:documents.view')->group(function () {
    // Printable document register (registered before the resource).
    Route::get('documents/register', [DocumentController::class, 'register'])->name('documents.register');

    Route::resource('documents', DocumentController::class)->except('destroy');

    Route::scopeBindings()->group(function () {
        Route::post('documents/{document}/versions', [DocumentVersionController::class, 'store'])
            ->middleware('permission:documents.update')->name('documents.versions.store');
        Route::get('documents/{document}/versions/{version}/download', [DocumentVersionController::class, 'download'])
            ->name('documents.versions.download');
    });

    Route::post('documents/{document}/submit', [DocumentWorkflowController::class, 'submit'])->name('documents.submit');
    Route::post('documents/{document}/publish', [DocumentWorkflowController::class, 'publish'])->name('documents.publish');
    Route::middleware('permission:documents.delete')->group(function () {
        Route::post('documents/{document}/archive', [DocumentWorkflowController::class, 'archive'])->name('documents.archive');
        Route::post('documents/{document}/unarchive', [DocumentWorkflowController::class, 'unarchive'])->name('documents.unarchive');
    });

    Route::post('documents/{document}/access', [DocumentAccessController::class, 'store'])->name('documents.access.store');
    Route::delete('documents/{document}/access/{user}', [DocumentAccessController::class, 'destroy'])->name('documents.access.destroy');
});
