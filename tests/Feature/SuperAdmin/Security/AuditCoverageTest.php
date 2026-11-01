<?php

use App\Enums\SuperAdmin\SuperAdminRole;
use App\Models\SuperAdmin\SuperAdminAuditLog;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;

/**
 * The only state-changing routes allowed to skip the audit middleware: sign-in
 * and session endpoints, which write to the sign-in history
 * (`super_admin_login_events`) instead.
 */
const SUPER_ADMIN_UNAUDITED_ROUTES = [
    'super-admin.auth.login',
    'super-admin.auth.2fa.verify',
    'super-admin.auth.refresh',
    'super-admin.auth.logout',
    'super-admin.auth.logout-all',
    'super-admin.auth.change-password',
    'super-admin.auth.2fa.enable',
    'super-admin.auth.2fa.confirm',
];

it('puts the audit middleware on every state-changing Super Admin route', function () {
    $writes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (LaravelRoute $route): bool => str_starts_with((string) $route->getName(), 'super-admin.'))
        ->filter(fn (LaravelRoute $route): bool => array_diff($route->methods(), ['GET', 'HEAD', 'OPTIONS']) !== []);

    expect($writes)->not->toBeEmpty();

    $unaudited = $writes
        ->reject(fn (LaravelRoute $route): bool => in_array('super-admin.audit', $route->gatherMiddleware(), true))
        ->map(fn (LaravelRoute $route): string => $route->getName())
        ->values()
        ->all();

    expect($unaudited)->toEqualCanonicalizing(SUPER_ADMIN_UNAUDITED_ROUTES);
});

it('puts the authentication, two-factor and IP guards on every route but the sign-in ones', function () {
    $open = ['super-admin.auth.login', 'super-admin.auth.2fa.verify', 'super-admin.auth.refresh'];

    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (LaravelRoute $route): bool => str_starts_with((string) $route->getName(), 'super-admin.'));

    expect($routes)->not->toBeEmpty();

    foreach ($routes as $route) {
        expect($route->gatherMiddleware())->toContain('super-admin.ip');

        if (! in_array($route->getName(), $open, true)) {
            expect($route->gatherMiddleware())->toContain('auth:super-admin');
        }
    }
});

it('writes a fallback audit row for a state change whose service recorded nothing', function () {
    $admin = superAdmin();

    $this->withHeaders(superAdminHeaders($admin))
        ->postJson(saUrl('auth/2fa/recovery-codes/regenerate'), stepUp($admin))
        ->assertOk();

    $log = SuperAdminAuditLog::query()->where('action', 'request.super-admin.auth.2fa.recovery-codes.regenerate')->firstOrFail();

    expect($log->super_admin_id)->toBe($admin->id)
        ->and($log->after)->toMatchArray(['method' => 'POST', 'status' => 200])
        ->and($log->request_id)->not->toBeNull();
});

it('records a denied attempt too', function () {
    $viewer = superAdmin(SuperAdminRole::Viewer);

    $this->withHeaders(superAdminHeaders($viewer))
        ->postJson(saUrl('accounts'), stepUp($viewer, ['name' => 'X', 'email' => 'x@example.test', 'role' => 'owner']))
        ->assertForbidden();

    $log = SuperAdminAuditLog::query()->where('action', 'request.super-admin.accounts.store')->firstOrFail();

    expect($log->super_admin_id)->toBe($viewer->id)
        ->and($log->after['status'])->toBe(403);
});

it('does not double-record when the service already wrote a precise entry', function () {
    $owner = superAdmin();
    $target = superAdmin(SuperAdminRole::Support);

    $this->withHeaders(superAdminHeaders($owner))
        ->postJson(saUrl("accounts/{$target->id}/role"), stepUp($owner, ['role' => 'viewer']))
        ->assertOk();

    expect(SuperAdminAuditLog::query()->where('action', 'accounts.role_changed')->count())->toBe(1)
        ->and(SuperAdminAuditLog::query()->where('action', 'like', 'request.%')->count())->toBe(0);
});

arch('the user and public APIs know nothing about Super Admin code')
    ->expect([
        'App\Http\Controllers\Api\Internal',
        'App\Http\Controllers\Api\Public',
        'App\Http\Resources\Api\Internal',
        'App\Http\Resources\Api\Public',
    ])
    ->not->toUse([
        'App\Http\Controllers\Api\SuperAdmin',
        'App\Http\Middleware\SuperAdmin',
        'App\Models\SuperAdmin',
        'App\Services\SuperAdmin',
        'App\Enums\SuperAdmin',
    ]);
