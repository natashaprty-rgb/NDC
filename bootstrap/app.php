<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SetLocale::class,
            SecurityHeaders::class,
        ]);
        $middleware->alias([
            'active' => EnsureAccountIsActive::class,
            'password.changed' => EnsurePasswordIsChanged::class,
        ]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Never show stack traces or internal details to users outside debug mode.
        $exceptions->dontFlash(['current_password', 'password', 'password_confirmation', 'code']);
    })->create();
