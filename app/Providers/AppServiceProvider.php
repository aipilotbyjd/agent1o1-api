<?php

namespace App\Providers;

use App\Ai\Observability\AiActivityCard;
use App\Ai\Observability\AiCallContext;
use App\Ai\Observability\AiCallRecorder;
use App\Ai\Support\RetriesProviderConnections;
use App\Authorization\WorkspaceContext;
use App\Contracts\Triggers\RunStarter;
use App\Enums\Triggers\TriggerTargetType;
use App\Enums\Workspaces\Permission;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentEvalRun;
use App\Models\Agents\AgentMessage;
use App\Models\Agents\AgentSession;
use App\Models\Agents\AgentSessionEvaluation;
use App\Models\Agents\ReflectionRun;
use App\Models\Agents\WorkspaceAgentPolicy;
use App\Models\Ai\AiProviderCredential;
use App\Models\Ai\WorkspaceAiKeyPolicy;
use App\Models\Ai\WorkspaceModelSettings;
use App\Models\Auth\ApiKey;
use App\Models\Auth\PassportToken;
use App\Models\Billing\CreditPack;
use App\Models\Billing\Subscription as BillingSubscription;
use App\Models\Billing\SubscriptionItem as BillingSubscriptionItem;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Notifications\NotificationChannel;
use App\Models\Referrals\ReferralReward;
use App\Models\Runs\NodeRun;
use App\Models\Runs\Run;
use App\Models\Secrets\Secret;
use App\Models\Templates\AgentTemplate;
use App\Models\Templates\WorkflowTemplate;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceInvitation;
use App\Models\Workspaces\WorkspaceMember;
use App\Observers\AgentMessageObserver;
use App\Observers\AgentObserver;
use App\Observers\AuditObserver;
use App\Observers\NodeRunObserver;
use App\Observers\RunObserver;
use App\Observers\WorkflowObserver;
use App\Observers\WorkspaceMemberObserver;
use App\Services\Ai\OperatorEndpoints;
use App\Services\Assistant\Branding\BrandRepository;
use App\Services\Assistant\Branding\ConfigBrandRepository;
use App\Services\Assistant\Computer\ComputerToolProvider;
use App\Services\Assistant\Computer\E2BSandboxDriver;
use App\Services\Assistant\Computer\SandboxDriver;
use App\Services\Assistant\Tools\ConnectorToolProvider;
use App\Services\Assistant\Tools\KnowledgeToolProvider;
use App\Services\Assistant\Tools\ToolCatalog;
use App\Services\Auth\Grants\SocialExchangeGrant;
use App\Services\SuperAdmin\System\ScheduledCommandRecorder;
use App\Services\Triggers\TargetRunStarter;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Cashier\Cashier;
use Laravel\Passport\Bridge\RefreshTokenRepository;
use Laravel\Passport\Passport;
use League\OAuth2\Server\AuthorizationServer;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(RunStarter::class, TargetRunStarter::class);
        $this->app->singleton(OperatorEndpoints::class);
        $this->app->scoped(AiCallContext::class);
        $this->app->bind(BrandRepository::class, ConfigBrandRepository::class);
        $this->app->bind(SandboxDriver::class, E2BSandboxDriver::class);
        $this->app->tag([ConnectorToolProvider::class, KnowledgeToolProvider::class, ComputerToolProvider::class], ToolCatalog::TAG);

        // Must run in register(), not boot(): CashierServiceProvider's own
        // boot() (which registers its default routes unless this flag is
        // already set) runs before this provider's boot() does.
        Cashier::ignoreRoutes();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configurePassport();
        $this->configureAuthNotificationUrls();
        $this->configurePasswordDefaults();
        $this->configureRateLimiting();
        $this->configureGate();
        $this->configureObservers();
        $this->configurePulseCards();
        $this->configureMorphMap();
        $this->configureCashier();
        $this->configureSchedulerRecording();
        $this->configureAiCallLedger();
        $this->configureAiProviderRetries();
    }

    /**
     * A dropped connection to an AI provider is retried a couple of times
     * before the turn fails: see `RetriesProviderConnections`.
     */
    private function configureAiProviderRetries(): void
    {
        Http::globalMiddleware(RetriesProviderConnections::middleware());
    }

    /**
     * Writes every request sent to an AI provider to `ai_call_events`, from
     * Laravel AI's own events: see `AiCallRecorder`.
     */
    private function configureAiCallLedger(): void
    {
        if (! config('model_operations.call_ledger.enabled', true)) {
            return;
        }

        foreach (AiCallRecorder::listeners() as $event => $method) {
            Event::listen($event, [AiCallRecorder::class, $method]);
        }
    }

    /**
     * Records every scheduled command's runs for System Health. A singleton,
     * because it matches each run's start to its finish by task object.
     */
    private function configureSchedulerRecording(): void
    {
        $this->app->singleton(ScheduledCommandRecorder::class);

        $this->app->make(ScheduledCommandRecorder::class)->subscribe($this->app->make('events'));
    }

    private function configurePassport(): void
    {
        Passport::enablePasswordGrant();
        Passport::useTokenModel(PassportToken::class);

        Passport::tokensExpireIn(now()->addMinutes(60));
        Passport::refreshTokensExpireIn(now()->addDays(30));
        Passport::personalAccessTokensExpireIn(now()->addMonths(6));

        $this->enableSocialExchangeGrant();
    }

    /**
     * Registers the custom grant that turns a completed social sign-in into a
     * token pair (see `SocialExchangeGrant`).
     *
     * `enableGrantType()` wires the access token, client and scope repositories
     * but not the refresh token one, so that is injected here, without it the
     * grant would issue an access token with no way to renew it, which is the
     * whole reason the old implementation resorted to rotating passwords.
     */
    private function enableSocialExchangeGrant(): void
    {
        $this->app->resolving(AuthorizationServer::class, function (AuthorizationServer $server): void {
            $grant = new SocialExchangeGrant;
            $grant->setRefreshTokenRepository($this->app->make(RefreshTokenRepository::class));
            $grant->setRefreshTokenTTL(Passport::refreshTokensExpireIn());

            $server->enableGrantType($grant, Passport::tokensExpireIn());
        });
    }

    private function configureAuthNotificationUrls(): void
    {
        VerifyEmail::createUrlUsing(function (User $user) {
            return URL::temporarySignedRoute(
                'auth.verify-email',
                now()->addMinutes(60),
                [
                    'id' => $user->getKey(),
                    'hash' => sha1($user->getEmailForVerification()),
                ],
            );
        });

        ResetPassword::createUrlUsing(function (User $user, string $token) {
            return config('app.frontend_url').'/reset-password?token='.$token.'&email='.urlencode($user->getEmailForPasswordReset());
        });
    }

    private function configurePasswordDefaults(): void
    {
        Password::defaults(fn () => Password::min(8)->mixedCase()->numbers()->symbols());
    }

    private function configureRateLimiting(): void
    {
        // Two limits, not one: the IP limit caps how fast a single source can
        // work, and the email limit caps how fast one account can be worked on
        // no matter how many sources are spread across it. `LoginThrottle`
        // then locks the account outright once failures accumulate.
        RateLimiter::for('auth', fn (Request $request): array => array_filter([
            Limit::perMinute(10)->by($request->ip()),
            $this->authEmailLimit($request),
        ]));

        RateLimiter::for('public-api', function ($request) {
            $key = $request->attributes->get('api_key');

            return Limit::perMinute(60)->by($key?->id ?? $request->ip());
        });

        // Keyed by token rather than IP: one noisy provider must not rate-limit
        // every other trigger sharing its egress IPs.
        // Personal-assistant webhook triggers: per trigger URL.
        RateLimiter::for('assistant-hooks', fn (Request $request): Limit => Limit::perMinute(
            (int) config('assistant.triggers.webhook_rate_per_minute'),
        )->by('assistant-hook:'.($request->route('token') ?? $request->ip())));

        RateLimiter::for('trigger-hooks', fn (Request $request): Limit => Limit::perMinute(
            (int) config('triggers.hook_rate_limit_per_minute'),
        )->by($request->route('token') ?? $request->ip()));

        // Every builder message and assist call is a paid model call: these
        // cap how fast one member can spend a workspace's credits by
        // accident (a stuck retry loop in the client, a held-down Enter).
        RateLimiter::for('workflow-builder-messages', fn (Request $request): Limit => Limit::perMinute(10)
            ->by($request->user()?->id ?? $request->ip()));

        // Each check calls the provider's API, so a member can't hammer it.
        RateLimiter::for('connector-tests', fn (Request $request): Limit => Limit::perMinute(10)
            ->by($request->user()?->id ?? $request->ip()));

        // Each trial runs a real (billed) model call.
        RateLimiter::for('model-trials', fn (Request $request): Limit => Limit::perMinute(6)
            ->by($request->user()?->id ?? $request->ip()));

        // Adding, replacing or re-checking an AI provider key calls the provider.
        RateLimiter::for('ai-credential-checks', fn (Request $request): Limit => Limit::perMinute(10)
            ->by($request->user()?->id ?? $request->ip()));

        RateLimiter::for('workflow-builder-assist', fn (Request $request): Limit => Limit::perMinute(20)
            ->by($request->user()?->id ?? $request->ip()));

        // Super Admin email campaigns: one platform-wide budget for the mail provider.
        RateLimiter::for('broadcast-emails', fn (): Limit => Limit::perSecond(max(1, (int) config('broadcasts.rate_per_second'))));

        // Unauthenticated, so keyed by IP: a landing page may legitimately
        // record a few visits, but nothing should be spraying them.
        RateLimiter::for('referral-visits', fn (Request $request): Limit => Limit::perMinute(30)->by($request->ip()));

        RateLimiter::for('referral-claims', fn (Request $request): Limit => Limit::perMinute(5)
            ->by($request->user()?->id ?? $request->ip()));

        // Workflow pages at their public link. Every submission is a paid
        // run on the owner's credits, so one visitor gets a handful a minute
        // per page; wrong passwords are slowed down harder still. Result
        // polls are cheap reads but still capped.
        RateLimiter::for('page-submissions', fn (Request $request): Limit => Limit::perMinute(10)
            ->by('page-submit:'.$request->route('token').'|'.$request->ip()));

        RateLimiter::for('page-unlock', fn (Request $request): Limit => Limit::perMinute(5)
            ->by('page-unlock:'.$request->route('token').'|'.$request->ip()));

        RateLimiter::for('page-reads', fn (Request $request): Limit => Limit::perMinute(120)
            ->by('page-read:'.$request->ip()));
    }

    /**
     * Keyed on the address being signed in as, so credential stuffing spread
     * across many IPs still collides on the account it is targeting. Returns
     * null for requests that carry no email: password resets and refreshes
     * share this limiter and have nothing to key on.
     */
    private function authEmailLimit(Request $request): ?Limit
    {
        $email = $request->input('email');

        if (! is_string($email) || $email === '') {
            return null;
        }

        return Limit::perMinute(5)->by('auth-email:'.hash('sha256', mb_strtolower($email)));
    }

    private function configureGate(): void
    {
        Gate::before(function (User $user, string $ability) {
            $permission = Permission::tryFrom($ability);

            if ($permission === null) {
                return null;
            }

            if (! app()->bound(WorkspaceContext::class)) {
                return false;
            }

            return app(WorkspaceContext::class)->allows($permission) ?: null;
        });
    }

    /**
     * Pulse dashboard cards that live outside `App\Livewire`.
     */
    private function configurePulseCards(): void
    {
        Livewire::component('pulse.ai-activity', AiActivityCard::class);
    }

    private function configureObservers(): void
    {
        WorkspaceMember::observe(WorkspaceMemberObserver::class);

        // Workspace audit trail: see `AuditLogger`.
        foreach ([
            Workspace::class,
            WorkspaceMember::class,
            WorkspaceInvitation::class,
            ApiKey::class,
            Secret::class,
            ConnectorCredential::class,
            AiProviderCredential::class,
            NotificationChannel::class,
            WorkspaceAgentPolicy::class,
            WorkspaceAiKeyPolicy::class,
            WorkspaceModelSettings::class,
        ] as $audited) {
            $audited::observe(AuditObserver::class);
        }

        // Behavioral history for agents: see `AgentVersioner`.
        Agent::observe(AgentObserver::class);

        // Triggers follow their workflow into and out of the trash.
        Workflow::observe(WorkflowObserver::class);

        // Live run/chat streaming: see `App\Broadcasting\Channels`.
        Run::observe(RunObserver::class);
        NodeRun::observe(NodeRunObserver::class);
        AgentMessage::observe(AgentMessageObserver::class);
    }

    /**
     * Short aliases instead of fully-qualified class names, shared by
     * `triggers.target_type` (`Workflow`/`Agent`) and `runs.runnable_type`
     * (`Workflow`/`AgentSession`: a run's target and a trigger's target
     * happen to overlap on `Workflow`, and both need every runnable/
     * targetable model registered once `enforceMorphMap()` is on, or any
     * relation touching an unregistered one throws).
     *
     * `workflow_template`/`agent_template` back `TemplateCollectionItem`'s
     * `templatable` morph: the alias, not the FQCN, is what's stored in
     * `template_collection_items.templatable_type`.
     */
    private function configureMorphMap(): void
    {
        Relation::enforceMorphMap([
            TriggerTargetType::Workflow->value => Workflow::class,
            TriggerTargetType::Agent->value => Agent::class,
            'agent_session' => AgentSession::class,
            'agent_eval_run' => AgentEvalRun::class,
            'reflection_run' => ReflectionRun::class,
            'agent_session_evaluation' => AgentSessionEvaluation::class,
            'workflow_template' => WorkflowTemplate::class,
            'agent_template' => AgentTemplate::class,
            // Needed for Laravel\Ai\Concerns\RemembersConversations'
            // forUser()/HasConversations participant polymorphism
            // (WorkflowBuilderAgent): the conversations table's
            // participant_type column stores this alias.
            'user' => User::class,
            // `credit_grants.reference_type`: what paid for or earned a grant.
            'credit_pack' => CreditPack::class,
            'referral_reward' => ReferralReward::class,
        ]);
    }

    private function configureCashier(): void
    {
        Cashier::useCustomerModel(Workspace::class);
        Cashier::useSubscriptionModel(BillingSubscription::class);
        Cashier::useSubscriptionItemModel(BillingSubscriptionItem::class);
    }
}
