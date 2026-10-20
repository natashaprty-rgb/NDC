<?php

use App\Http\Controllers\Meetings\MeetingActionItemController;
use App\Http\Controllers\Meetings\MeetingCalendarController;
use App\Http\Controllers\Meetings\MeetingController;
use App\Http\Controllers\Meetings\MeetingMinutesController;
use Illuminate\Support\Facades\Route;

/*
| Meetings & Calendar (SRS 3.7).
*/
Route::middleware('permission:meetings.view')->group(function () {
    Route::get('meetings/calendar', MeetingCalendarController::class)->name('meetings.calendar');
    Route::get('meetings/action-items', [MeetingActionItemController::class, 'index'])->name('meetings.action-items.index');

    // The assignee may update the status of their own action point; the policy decides.
    Route::patch('meetings/action-items/{actionItem}/status', [MeetingActionItemController::class, 'updateStatus'])
        ->name('meetings.action-items.status');

    Route::resource('meetings', MeetingController::class)->only(['create', 'store'])->middleware('permission:meetings.create');

    Route::middleware('permission:meetings.update')->group(function () {
        Route::resource('meetings', MeetingController::class)->only(['edit', 'update']);
        Route::patch('meetings/{meeting}/status', [MeetingController::class, 'status'])->name('meetings.status');
        Route::put('meetings/{meeting}/attendance', [MeetingMinutesController::class, 'attendance'])->name('meetings.attendance');
        Route::put('meetings/{meeting}/minutes', [MeetingMinutesController::class, 'update'])->name('meetings.minutes.update');
        Route::post('meetings/{meeting}/action-items', [MeetingActionItemController::class, 'store'])->name('meetings.action-items.store');
        Route::get('meetings/action-items/{actionItem}/edit', [MeetingActionItemController::class, 'edit'])->name('meetings.action-items.edit');
        Route::put('meetings/action-items/{actionItem}', [MeetingActionItemController::class, 'update'])->name('meetings.action-items.update');
        Route::delete('meetings/action-items/{actionItem}', [MeetingActionItemController::class, 'destroy'])->name('meetings.action-items.destroy');
    });

    Route::resource('meetings', MeetingController::class)->only(['destroy'])->middleware('permission:meetings.delete');

    Route::get('meetings/{meeting}/minutes', [MeetingMinutesController::class, 'show'])->name('meetings.minutes');
    Route::resource('meetings', MeetingController::class)->only(['index', 'show']);
});
