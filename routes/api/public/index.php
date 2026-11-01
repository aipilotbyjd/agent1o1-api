<?php

use Illuminate\Support\Facades\Route;

Route::prefix('public/v1')->middleware(['api-key', 'throttle:public-api'])->group(function () {
    require __DIR__.'/me.php';
    require __DIR__.'/workflows.php';
    require __DIR__.'/runs.php';
    require __DIR__.'/agents.php';
    require __DIR__.'/connectors.php';
});
