<?php

use App\Http\Controllers\Projects\ProjectController;
use App\Http\Controllers\Projects\ProjectMemberController;
use App\Http\Controllers\Projects\ProjectMilestoneController;
use App\Http\Controllers\Projects\ProjectRiskController;
use App\Http\Controllers\Projects\ProjectStakeholderController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:projects.create')->group(function () {
    Route::resource('projects', ProjectController::class)->only(['create', 'store']);
});

Route::middleware('permission:projects.view')->group(function () {
    Route::resource('projects', ProjectController::class)->only(['index', 'show']);
    Route::get('projects/{project}/report', [ProjectController::class, 'report'])->name('projects.report');
});

Route::middleware('permission:projects.update')->group(function () {
    Route::resource('projects', ProjectController::class)->only(['edit', 'update']);

    Route::scopeBindings()->prefix('projects/{project}')->name('projects.')->group(function () {
        Route::resource('milestones', ProjectMilestoneController::class)->only(['store', 'edit', 'update', 'destroy']);
        Route::post('milestones/{milestone}/complete', [ProjectMilestoneController::class, 'complete'])->name('milestones.complete');
        Route::resource('risks', ProjectRiskController::class)->only(['store', 'edit', 'update', 'destroy']);
        Route::resource('stakeholders', ProjectStakeholderController::class)->only(['store', 'edit', 'update', 'destroy']);
    });
    Route::post('projects/{project}/members', [ProjectMemberController::class, 'store'])->name('projects.members.store');
    Route::delete('projects/{project}/members/{user}', [ProjectMemberController::class, 'destroy'])->name('projects.members.destroy');
});

Route::middleware('permission:projects.archive')->group(function () {
    Route::post('projects/{project}/archive', [ProjectController::class, 'archive'])->name('projects.archive');
    Route::post('projects/{project}/unarchive', [ProjectController::class, 'unarchive'])->name('projects.unarchive');
    Route::delete('projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');
});
