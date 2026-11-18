<?php

use App\Enums\RunStatus;
use App\Events\Runs\RunCompleted;
use App\Events\Runs\RunFailed;
use App\Jobs\Analytics\CapturePostHogEvent;
use App\Models\Ai\AiProviderCredential;
use App\Models\Billing\Plan;
use App\Models\Runs\Run;
use App\Models\User;
use App\Services\Analytics\ProductAnalytics;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Laravel\Passport\Passport;

function enablePostHog(): void
{
    config([
        'posthog.enabled' => true,
        'posthog.project_token' => 'phc_test_token',
    ]);
}

it('queues nothing while PostHog has no project token', function () {
    Bus::fake();
    config(['posthog.enabled' => true, 'posthog.project_token' => null]);

    app(ProductAnalytics::class)->capture('user-1', 'user_signed_up');

    Bus::assertNothingDispatched();
});

it('queues nothing while PostHog is switched off', function () {
    Bus::fake();
    config(['posthog.enabled' => false, 'posthog.project_token' => 'phc_test_token']);

    app(ProductAnalytics::class)->capture('user-1', 'user_signed_up');

    Bus::assertNothingDispatched();
});

it('queues an event for the user with its workspace group', function () {
    Bus::fake();
    enablePostHog();

    app(ProductAnalytics::class)->capture('user-1', 'agent_created', ['agent_id' => 'a-1'], 'ws-1');

    Bus::assertDispatched(
        CapturePostHogEvent::class,
        fn (CapturePostHogEvent $job) => $job->distinctId === 'user-1'
            && $job->event === 'agent_created'
            && $job->properties === ['agent_id' => 'a-1']
            && $job->workspaceId === 'ws-1',
    );
});

it('records a system event without a person profile when no user is known', function () {
    Bus::fake();
    enablePostHog();

    app(ProductAnalytics::class)->capture(null, 'run_completed', [], 'ws-1');

    Bus::assertDispatched(
        CapturePostHogEvent::class,
        fn (CapturePostHogEvent $job) => $job->distinctId === 'workspace:ws-1'
            && $job->properties['$process_person_profile'] === false,
    );
});

it('captures a verified email', function () {
    Bus::fake();
    enablePostHog();

    $user = User::factory()->create();

    event(new Verified($user));

    Bus::assertDispatched(
        CapturePostHogEvent::class,
        fn (CapturePostHogEvent $job) => $job->distinctId === (string) $user->getKey() && $job->event === 'email_verified',
    );
});

it('captures a signup through the register endpoint', function () {
    Bus::fake([CapturePostHogEvent::class]);
    enablePostHog();

    $this->postJson('/api/v1/auth/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
    ]);

    Bus::assertDispatched(
        CapturePostHogEvent::class,
        fn (CapturePostHogEvent $job) => $job->event === 'user_signed_up' && $job->properties === ['method' => 'email'],
    );
});

it('captures finished runs by who started them', function () {
    Bus::fake();
    enablePostHog();

    $user = User::factory()->create();
    $run = Run::factory()->create(['triggered_by' => $user->getKey()]);

    Event::dispatch(new RunCompleted($run));
    Event::dispatch(new RunFailed($run));

    foreach (['run_completed', 'run_failed'] as $name) {
        Bus::assertDispatched(
            CapturePostHogEvent::class,
            fn (CapturePostHogEvent $job) => $job->event === $name
                && $job->distinctId === (string) $user->getKey()
                && $job->workspaceId === (string) $run->workspace_id
                && $job->properties['run_id'] === (string) $run->getKey(),
        );
    }
});

it('marks the first run a person started that succeeded, and only that one', function () {
    Bus::fake();
    enablePostHog();

    $user = User::factory()->create(['created_at' => now()->subMinutes(5)]);
    $first = Run::factory()->create(['triggered_by' => $user->getKey(), 'status' => RunStatus::Completed]);

    Event::dispatch(new RunCompleted($first));

    Bus::assertDispatched(
        CapturePostHogEvent::class,
        fn (CapturePostHogEvent $job) => $job->event === 'first_run_succeeded'
            && $job->distinctId === (string) $user->getKey()
            && $job->properties['run_id'] === (string) $first->getKey()
            && $job->properties['seconds_since_signup'] >= 300,
    );

    $second = Run::factory()->create(['triggered_by' => $user->getKey(), 'status' => RunStatus::Completed]);
    Event::dispatch(new RunCompleted($second));

    Bus::assertDispatchedTimes(
        fn (CapturePostHogEvent $job) => $job->event === 'first_run_succeeded',
        1,
    );
});

it('does not count runs nobody started as a first success', function () {
    Bus::fake();
    enablePostHog();

    Event::dispatch(new RunCompleted(Run::factory()->create(['triggered_by' => null, 'status' => RunStatus::Completed])));

    Bus::assertNotDispatched(CapturePostHogEvent::class, fn (CapturePostHogEvent $job) => $job->event === 'first_run_succeeded');
});

it('records each onboarding step once, as completed or skipped', function () {
    Bus::fake();
    enablePostHog();
    Plan::factory()->create(['slug' => 'free', 'is_active' => true]);

    $user = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($user, ['name' => 'Acme']);
    AiProviderCredential::factory()->forWorkspace($workspace)->provider('openrouter')->create();
    Passport::actingAs($user);

    $this->postJson('/api/v1/onboarding/role', ['job_role' => 'engineering'])->assertOk();
    $this->postJson('/api/v1/onboarding/continue', ['step' => 'connect_ai'])->assertOk();
    $this->postJson('/api/v1/onboarding/continue', ['step' => 'role_selection'])->assertOk();
    $this->postJson('/api/v1/onboarding/skip', ['step' => 'choose_template'])->assertOk();

    $stepEvents = fn (string $event, string $step) => fn (CapturePostHogEvent $job) => $job->event === $event
        && $job->properties['step'] === $step
        && $job->distinctId === (string) $user->getKey();

    Bus::assertDispatchedTimes($stepEvents('onboarding_step_completed', 'role_selection'), 1);
    Bus::assertDispatchedTimes($stepEvents('onboarding_step_completed', 'connect_ai'), 1);
    Bus::assertDispatchedTimes($stepEvents('onboarding_step_skipped', 'choose_template'), 1);
    Bus::assertDispatched(CapturePostHogEvent::class, fn (CapturePostHogEvent $job) => $job->event === 'onboarding_step_skipped'
        && $job->properties['step_number'] === 3
        && $job->workspaceId === (string) $user->fresh()->current_workspace_id);
});

it('records finishing onboarding once', function () {
    Bus::fake();
    enablePostHog();
    Plan::factory()->create(['slug' => 'free', 'is_active' => true]);

    $user = User::factory()->create();
    app(WorkspaceService::class)->create($user, ['name' => 'Acme']);
    Passport::actingAs($user);

    $this->postJson('/api/v1/onboarding/complete')->assertOk();
    $this->postJson('/api/v1/onboarding/complete')->assertOk();

    Bus::assertDispatchedTimes(fn (CapturePostHogEvent $job) => $job->event === 'onboarding_completed', 1);
});
