<?php

namespace App\Providers;

use App\Authorization\WorkspaceContext;
use App\Engine\Graph\ExpressionResolver;
use App\Enums\Permission;
use App\Events\WorkspaceCreated;
use App\Listeners\LogExecutionActivity;
use App\Models\Agent;
use App\Models\InAppNotification;
use App\Models\Workflow;
use App\Models\WorkflowBuilderSession;
use App\Observers\AgentObserver;
use App\Observers\InAppNotificationObserver;
use App\Observers\WorkflowObserver;
use App\Policies\WorkflowBuilderSessionPolicy;
use App\Services\Billing\SubscriptionService;
use Carbon\CarbonInterval;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(WorkspaceContext::class);
        $this->app->singleton(ExpressionResolver::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configurePassport();
        $this->configureRateLimiting();
        $this->configureAuthNotificationUrls();
        $this->configureGate();
        $this->configurePulse();
        $this->configureBillingEvents();
        $this->configureExecutionLogging();
        $this->configureModelObservers();

        Password::defaults(fn (): Password => Password::min(8)->mixedCase()->numbers()->symbols());
    }

    private function configurePassport(): void
    {
        Passport::tokensExpireIn(CarbonInterval::days(15));
        Passport::refreshTokensExpireIn(CarbonInterval::days(30));
        Passport::personalAccessTokensExpireIn(CarbonInterval::months(6));
        Passport::enablePasswordGrant();
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('auth', function (Request $request): Limit {
            return Limit::perMinute(10)->by($request->ip());
        });
    }

    /**
     * Point auth notification links at the API verification route and the
     * frontend's password reset page, since this application has no web UI.
     */
    private function configureAuthNotificationUrls(): void
    {
        VerifyEmail::createUrlUsing(function (object $notifiable): string {
            return URL::temporarySignedRoute('v1.verification.verify', now()->addMinutes(60), [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]);
        });

        ResetPassword::createUrlUsing(function (object $notifiable, string $token): string {
            return config('app.frontend_url').'/reset-password?'.http_build_query([
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]);
        });
    }

    private function configureGate(): void
    {
        // Route all workspace permission checks through WorkspaceContext so every
        // caller ($user->can(), FormRequest::authorize, can: middleware) shares one
        // consistent path. Returns null for non-workspace abilities to fall through.
        Gate::before(function ($user, string $ability) {
            $permission = Permission::tryFrom($ability);

            if ($permission === null) {
                return null;
            }

            return app(WorkspaceContext::class)->allows($permission);
        });

        // Platform-admin abilities (global template catalogs, platform settings).
        // Backed by the `is_admin` flag on the user.
        Gate::define('platformAdmin', fn ($user) => (bool) ($user->is_admin ?? false));
    }

    private function configurePulse(): void
    {
        // Access is guarded by App\Http\Middleware\DashboardBasicAuth
        // (HTTP Basic Auth in non-local envs), so the gate allows through.
        Gate::define('viewPulse', fn () => true);
    }

    private function configureBillingEvents(): void
    {
        Event::listen(WorkspaceCreated::class, function (WorkspaceCreated $event) {
            app(SubscriptionService::class)->bootstrapFree($event->workspace);
        });
    }

    private function configureExecutionLogging(): void
    {
        // LogExecutionActivity and DispatchExecutionNotifications are event subscribers.
        // Laravel 11 auto-discovers them via the Listeners directory, so explicit
        // Event::subscribe() calls would register every handler twice.
        // Auto-discovery is the single source of truth.
    }

    private function configureModelObservers(): void
    {
        Workflow::observe(WorkflowObserver::class);
        Agent::observe(AgentObserver::class);
        InAppNotification::observe(InAppNotificationObserver::class);
    }

    protected function policies(): array
    {
        return [
            WorkflowBuilderSession::class => WorkflowBuilderSessionPolicy::class,
        ];
    }
}
