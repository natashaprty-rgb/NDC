<?php

use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LocaleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web routes
|--------------------------------------------------------------------------
| Every authenticated route passes through:
|   auth              - must be signed in
|   active            - account must not be suspended / archived
|   password.changed  - must not have a pending forced password change
| Module routes live in routes/modules/*.php and declare `can:` permission
| middleware; record-level checks are done in controllers via policies.
*/

Route::redirect('/', '/dashboard');

Route::post('/locale', LocaleController::class)->name('locale.switch');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:login');
    Route::get('/login/verify', [LoginController::class, 'showMfaChallenge'])->name('mfa.challenge');
    Route::post('/login/verify', [LoginController::class, 'verifyMfa'])->middleware('throttle:login')->name('mfa.verify');

    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:sensitive')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:sensitive')->name('password.update');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/account/password', [ProfileController::class, 'editPassword'])->name('password.change');
    Route::put('/account/password', [ProfileController::class, 'updatePassword'])->middleware('throttle:sensitive')->name('password.change.update');

    Route::middleware('password.changed')->group(function () {
        Route::get('/dashboard', DashboardController::class)->middleware('can:dashboard.view')->name('dashboard');

        Route::get('/account/profile', [ProfileController::class, 'show'])->name('profile.show');
        Route::put('/account/preferences', [ProfileController::class, 'updatePreferences'])->name('profile.preferences');
        Route::post('/account/mfa/start', [ProfileController::class, 'startMfa'])->name('profile.mfa.start');
        Route::post('/account/mfa', [ProfileController::class, 'enableMfa'])->middleware('throttle:sensitive')->name('profile.mfa.enable');
        Route::delete('/account/mfa', [ProfileController::class, 'disableMfa'])->middleware('throttle:sensitive')->name('profile.mfa.disable');

        Route::post('/attachments/{type}/{id}', [AttachmentController::class, 'store'])
            ->whereNumber('id')->middleware('throttle:uploads')->name('attachments.store');
        Route::get('/attachments/{attachment}', [AttachmentController::class, 'download'])->name('attachments.download');
        Route::delete('/attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');

        foreach (glob(__DIR__.'/modules/*.php') as $moduleRoutes) {
            require $moduleRoutes;
        }
    });
});
