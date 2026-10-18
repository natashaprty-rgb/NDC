<?php

use App\Http\Controllers\Meetings\MeetingController;
use App\Http\Controllers\Meetings\MeetingMinutesController;
use Illuminate\Support\Facades\Route;

// No route-level permission: MeetingPolicy lets participants open their own
// meetings even without meetings.view; every action authorises via the policy.
Route::get('/meetings/calendar', [MeetingController::class, 'calendar'])->name('meetings.calendar');
Route::post('/meetings/{meeting}/status', [MeetingController::class, 'changeStatus'])->name('meetings.status');
Route::get('/meetings/{meeting}/minutes', [MeetingMinutesController::class, 'edit'])->name('meetings.minutes.edit');
Route::put('/meetings/{meeting}/minutes', [MeetingMinutesController::class, 'update'])->name('meetings.minutes.update');
Route::post('/meetings/{meeting}/items', [MeetingMinutesController::class, 'storeItem'])->name('meetings.items.store');
Route::put('/meetings/{meeting}/items/{item}', [MeetingMinutesController::class, 'updateItem'])->name('meetings.items.update');
Route::post('/meetings/{meeting}/items/{item}/task', [MeetingMinutesController::class, 'convertToTask'])->name('meetings.items.task');
Route::resource('meetings', MeetingController::class);
