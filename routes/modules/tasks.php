<?php

use App\Http\Controllers\Tasks\TaskCommentController;
use App\Http\Controllers\Tasks\TaskController;
use App\Http\Controllers\Tasks\TaskWorkflowController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:tasks.create')->group(function () {
    Route::resource('tasks', TaskController::class)->only(['create', 'store']);
});

Route::middleware('permission:tasks.view|tasks.view-all')->group(function () {
    Route::resource('tasks', TaskController::class)->only(['index', 'show']);

    // Work on a task: the policy limits these to the assignee or tasks.update holders.
    Route::prefix('tasks/{task}')->name('tasks.')->controller(TaskWorkflowController::class)->group(function () {
        Route::post('start', 'start')->name('start');
        Route::post('hand-in', 'handIn')->name('hand-in');
        Route::post('resume', 'resume')->name('resume');
        Route::patch('progress', 'progress')->name('progress');
    });
    Route::post('tasks/{task}/comments', [TaskCommentController::class, 'store'])->name('tasks.comments.store');
});

Route::middleware('permission:tasks.review')->controller(TaskWorkflowController::class)->group(function () {
    Route::post('tasks/{task}/approve', 'approve')->name('tasks.approve');
    Route::post('tasks/{task}/reject', 'reject')->name('tasks.reject');
});

Route::middleware('permission:tasks.update')->group(function () {
    Route::resource('tasks', TaskController::class)->only(['edit', 'update']);
    Route::controller(TaskWorkflowController::class)->prefix('tasks/{task}')->name('tasks.')->group(function () {
        Route::post('reassign', 'reassign')->name('reassign');
        Route::post('cancel', 'cancel')->name('cancel');
        Route::post('reopen', 'reopen')->name('reopen');
    });
});

Route::delete('tasks/{task}', [TaskController::class, 'destroy'])->middleware('permission:tasks.delete')->name('tasks.destroy');
