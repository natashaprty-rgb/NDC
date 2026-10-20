<?php

use App\Http\Controllers\Api\ApiController;
use Illuminate\Support\Facades\Route;

Route::get('me', [ApiController::class, 'me'])->name('me');
Route::get('projects', [ApiController::class, 'projects'])->name('projects.index');
Route::get('projects/{project}', [ApiController::class, 'showProject'])->name('projects.show');
Route::get('tasks', [ApiController::class, 'tasks'])->name('tasks.index');
Route::get('documents', [ApiController::class, 'documents'])->name('documents.index');
Route::get('assets', [ApiController::class, 'assets'])->name('assets.index');
Route::get('staff', [ApiController::class, 'staff'])->name('staff.index');
Route::get('meetings', [ApiController::class, 'meetings'])->name('meetings.index');
