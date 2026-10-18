<?php

use App\Http\Controllers\Projects\TaskController;
use App\Http\Controllers\Projects\TaskWorkflowController;
use Illuminate\Support\Facades\Route;

// No route-level permission: every user can see their own tasks. TaskPolicy decides per record.
Route::resource('tasks', TaskController::class);

Route::prefix('/tasks/{task}')->name('tasks.')->controller(TaskWorkflowController::class)->group(function () {
    Route::post('/progress', 'progress')->name('progress');
    Route::post('/submit', 'submit')->middleware('throttle:uploads')->name('submit');
    Route::post('/approve', 'approve')->name('approve');
    Route::post('/reject', 'reject')->name('reject');
    Route::post('/cancel', 'cancel')->name('cancel');
    Route::post('/comments', 'comment')->name('comments.store');
});
