<?php

use App\Http\Controllers\Projects\ProjectController;
use App\Http\Controllers\Projects\ProjectDetailController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:projects.view')->group(function () {
    Route::resource('projects', ProjectController::class)->except('destroy');
    Route::get('/projects/{project}/report', [ProjectController::class, 'report'])->name('projects.report');
    Route::post('/projects/{project}/archive', [ProjectController::class, 'archive'])->name('projects.archive');
    Route::post('/projects/{project}/restore', [ProjectController::class, 'restore'])->name('projects.restore');

    Route::scopeBindings()->prefix('/projects/{project}')->name('projects.')->controller(ProjectDetailController::class)->group(function () {
        Route::post('/milestones', 'storeMilestone')->name('milestones.store');
        Route::put('/milestones/{milestone}', 'updateMilestone')->name('milestones.update');
        Route::delete('/milestones/{milestone}', 'destroyMilestone')->name('milestones.destroy');

        Route::post('/stakeholders', 'storeStakeholder')->name('stakeholders.store');
        Route::put('/stakeholders/{stakeholder}', 'updateStakeholder')->name('stakeholders.update');
        Route::delete('/stakeholders/{stakeholder}', 'destroyStakeholder')->name('stakeholders.destroy');

        Route::post('/risks', 'storeRisk')->name('risks.store');
        Route::put('/risks/{risk}', 'updateRisk')->name('risks.update');
        Route::delete('/risks/{risk}', 'destroyRisk')->name('risks.destroy');
    });
});
