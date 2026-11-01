<?php

namespace App\Providers;

use App\Services\SuperAdmin\Dashboards\DashboardAccess;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments:
     * only the addresses in `super_admin_security.dashboard_ips` (see
     * `DashboardAccess`).
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', fn ($user = null): bool => app(DashboardAccess::class)->allows(request()));
    }
}
