<?php

use App\Http\Controllers\Api\Internal\V1\Timezones\TimezoneController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->group(function () {
    Route::get('timezones', [TimezoneController::class, 'index'])->name('timezones.index');
});
