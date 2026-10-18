<?php

use App\Http\Controllers\ApprovalController;
use Illuminate\Support\Facades\Route;

Route::get('/approvals', [ApprovalController::class, 'index'])->name('approvals.index');
