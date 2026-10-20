<?php

use Illuminate\Support\Facades\Route;

/*
| Integration API (v1). Authenticated with Sanctum personal access tokens
| issued to users holding the api.access permission. Every endpoint also
| applies the same permission and record-level checks as the web interface.
*/

Route::prefix('v1')->name('api.')->middleware(['auth:sanctum', 'permission:api.access', 'throttle:api'])->group(function () {
    foreach (glob(__DIR__.'/api/*.php') as $apiRoutes) {
        require $apiRoutes;
    }
});
