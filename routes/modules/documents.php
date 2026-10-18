<?php

use App\Http\Controllers\Documents\DocumentAccessController;
use App\Http\Controllers\Documents\DocumentController;
use App\Http\Controllers\Documents\DocumentVersionController;
use App\Http\Controllers\Documents\DocumentWorkflowController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:documents.view')->group(function () {
    Route::resource('documents', DocumentController::class)->except(['destroy']);

    Route::post('/documents/{document}/versions', [DocumentVersionController::class, 'store'])
        ->middleware('throttle:uploads')->name('documents.versions.store');
    Route::get('/documents/{document}/versions/{version}/download', [DocumentVersionController::class, 'download'])
        ->scopeBindings()->name('documents.versions.download');

    Route::post('/documents/{document}/submit', [DocumentWorkflowController::class, 'submit'])->name('documents.submit');
    Route::post('/documents/{document}/approve', [DocumentWorkflowController::class, 'approve'])->name('documents.approve');
    Route::post('/documents/{document}/reject', [DocumentWorkflowController::class, 'reject'])->name('documents.reject');
    Route::post('/documents/{document}/archive', [DocumentWorkflowController::class, 'archive'])->name('documents.archive');
    Route::post('/documents/{document}/restore', [DocumentWorkflowController::class, 'restore'])->name('documents.restore');

    Route::post('/documents/{document}/access', [DocumentAccessController::class, 'store'])->name('documents.access.store');
    Route::delete('/documents/{document}/access/{accessGrant}', [DocumentAccessController::class, 'destroy'])
        ->scopeBindings()->name('documents.access.destroy');
});
