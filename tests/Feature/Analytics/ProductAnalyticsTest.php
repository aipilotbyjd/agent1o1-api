<?php

use App\Events\Runs\RunCompleted;
use App\Events\Runs\RunFailed;
use App\Jobs\Analytics\CapturePostHogEvent;
use App\Models\Runs\Run;
use App\Models\User;
use App\Services\Analytics\ProductAnalytics;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;

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
