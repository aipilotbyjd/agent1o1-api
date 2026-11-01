<?php

namespace App\Providers;

use App\Services\Ai\Operations\ModelRouteDescriber;
use App\Services\SuperAdmin\Auth\SuperAdminGuard;
use App\Services\SuperAdmin\Auth\SuperAdminTokenService;
use App\Services\SuperAdmin\Dashboards\DashboardAccess;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the Super Admin surface into the framework: the token guard that backs
 * `auth:super-admin` and the three rate limiters its routes use. Everything
 * else (routes, middleware aliases) is registered in `bootstrap/app.php`.
 */
class SuperAdminServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Describing a whole catalog reads the endpoints and prices once per request.
        $this->app->scoped(ModelRouteDescriber::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerGuard();
        $this->registerDashboardGate();
        $this->configureRateLimiting();
    }

    /**
     * Super admins authenticate with their own opaque tokens, not Passport:
     * see `SuperAdminTokenService`.
     */
    private function registerGuard(): void
    {
        Auth::extend('super-admin-token', function ($app, string $name, array $config): SuperAdminGuard {
            $guard = new SuperAdminGuard(
                fn (Request $request) => $app->make(SuperAdminTokenService::class)->authenticate($request),
                $app['request'],
                Auth::createUserProvider($config['provider'] ?? null),
            );

            $app->refresh('request', $guard, 'setRequest');

            return $guard;
        });
    }

    /**
     * Pulse's own gate: the same network allowlist as Horizon's (`DashboardAccess`).
     */
    private function registerDashboardGate(): void
    {
        Gate::define('viewPulse', fn ($user = null): bool => $this->app->make(DashboardAccess::class)->allows(request()));
    }

    private function configureRateLimiting(): void
    {
        // Two limits, like the user API's `auth` limiter: one source can only
        // go so fast, and one account can only be worked on so fast however
        // many sources are spread across it. The account lock (see
        // `SuperAdminLoginThrottle`) then stops sustained guessing outright.
        RateLimiter::for('super-admin-auth', function (Request $request): array {
            $perMinute = (int) config('super_admin_security.rate_limits.auth');
            $email = $request->input('email');

            return array_filter([
                Limit::perMinute($perMinute)->by('super-admin-auth-ip:'.$request->ip()),
                is_string($email) && $email !== ''
                    ? Limit::perMinute($perMinute)->by('super-admin-auth-email:'.hash('sha256', mb_strtolower($email)))
                    : null,
            ]);
        });

        RateLimiter::for('super-admin', fn (Request $request): Limit => Limit::perMinute((int) config('super_admin_security.rate_limits.api'))
            ->by('super-admin:'.($request->user('super-admin')?->id ?? $request->ip())));

        // Refunds, credit grants, account changes: slow enough to notice a
        // stolen session being used to drain them.
        RateLimiter::for('super-admin-sensitive', fn (Request $request): Limit => Limit::perMinute((int) config('super_admin_security.rate_limits.sensitive'))
            ->by('super-admin-sensitive:'.($request->user('super-admin')?->id ?? $request->ip())));
    }
}
