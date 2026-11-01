<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Super Admin API
|--------------------------------------------------------------------------
|
| Behind the one master flag, `SUPER_ADMIN_ENABLED`: with it off this file
| registers nothing, so the URLs do not exist at all.
|
| `super-admin.ip` runs first, before any authentication, so a global IP
| allowlist also protects the sign-in endpoints.
|
*/

if (! config('super_admin.enabled')) {
    return;
}

$attributes = [
    'prefix' => config('super_admin.prefix'),
    'as' => 'super-admin.',
    'middleware' => ['super-admin.ip'],
];

if (filled(config('super_admin.host'))) {
    $attributes['domain'] = config('super_admin.host');
}

Route::group($attributes, function () {
    require __DIR__.'/auth.php';
    require __DIR__.'/accounts.php';
    require __DIR__.'/audit_log.php';
    require __DIR__.'/referrals.php';
    require __DIR__.'/users.php';
    require __DIR__.'/customers.php';
    require __DIR__.'/workspaces.php';
    require __DIR__.'/billing.php';
    require __DIR__.'/runs.php';
    require __DIR__.'/catalog.php';
    require __DIR__.'/stats.php';
    require __DIR__.'/models.php';
    require __DIR__.'/broadcasts.php';
    require __DIR__.'/system.php';
    require __DIR__.'/alerts.php';
    require __DIR__.'/revenue.php';
    require __DIR__.'/pricing.php';
});
