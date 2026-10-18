<?php

use App\Http\Controllers\Organisation\DepartmentController;
use App\Http\Controllers\Organisation\StaffController;
use Illuminate\Support\Facades\Route;

Route::resource('departments', DepartmentController::class)->middleware('can:departments.view');

Route::resource('staff', StaffController::class)->parameters(['staff' => 'staff'])->middleware('can:staff.view');
