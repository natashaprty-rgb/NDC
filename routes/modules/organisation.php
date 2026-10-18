<?php

use App\Http\Controllers\Organisation\DepartmentController;
use Illuminate\Support\Facades\Route;

Route::resource('departments', DepartmentController::class)->middleware('can:departments.view');
