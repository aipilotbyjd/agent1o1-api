<?php

use App\Events\ExecutionCompletedEvent;
use App\Events\ExecutionFailedEvent;
use App\Events\InAppNotificationCreated;
use App\Models\InAppNotification;
use App\Models\NotificationChannel;
use App\Models\NotificationPreference;
use App\Models\Plan;
use App\Models\Run;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workflow;
use App\Models\Workspace;
use App\Notifications\ExecutionCompletedNotification;
use App\Notifications\ExecutionFailedNotification;
use App\Notifications\PaymentFailedNotification;
use App\Services\Billing\StripeService;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Stripe\Event as StripeEvent;

beforeEach(function () {
    $this->seed(PlanSeeder::class);

    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['owner_id' => $this->user->id]);
    $this->workspace->members()->attach($this->user->id, [
        'id' => Str::uuid()->toString(),
        'role' => 'owner',
        'joined_at' => now(),
    ]);
    $this->workflow = Workflow::factory()->create(['workspace_id' => $this->workspace->id]);
});

// ── InAppNotification broadcast ───────────────────────────────────────────────

test('creating an in-app notification broadcasts InAppNotificationCreated', function () {
    Event::fake([InAppNotificationCreated::class]);

    InAppNotification::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'type' => 'execution.failed',
        'title' => 'Test',
    ]);

    Event::assertDispatched(InAppNotificationCreated::class, function ($event) {
        return $event->notification->workspace_id === $this->workspace->id;
    });
});

test('in-app notification without workspace_id does not broadcast', function () {
    Event::fake([InAppNotificationCreated::class]);

    InAppNotification::create([
        'workspace_id' => null,
        'user_id' => $this->user->id,
        'type' => 'system.info',
        'title' => 'Hello',
    ]);

    Event::assertNotDispatched(InAppNotificationCreated::class);
});

// ── DispatchExecutionNotifications — in-app ───────────────────────────────────

test('execution failure creates in-app notification for members with preference', function () {
    NotificationPreference::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'event_key' => 'execution.failed',
        'in_app' => true,
        'email' => false,
    ]);

    $execution = Run::factory()->create([
        'workspace_id' => $this->workspace->id,
        'workflow_id' => $this->workflow->id,
        'error' => ['message' => 'Something went wrong'],
    ]);

    event(new ExecutionFailedEvent($execution, 'Something went wrong'));

    expect(
        InAppNotification::where('user_id', $this->user->id)
            ->where('type', 'execution.failed')
            ->exists()
    )->toBeTrue();
});

test('execution failure falls back to notifying triggered_by user when no preferences exist', function () {
    $execution = Run::factory()->create([
        'workspace_id' => $this->workspace->id,
        'workflow_id' => $this->workflow->id,
        'triggered_by' => $this->user->id,
        'error' => ['message' => 'Oops'],
    ]);

    event(new ExecutionFailedEvent($execution, 'Oops'));

    expect(
        InAppNotification::where('user_id', $this->user->id)
            ->where('type', 'execution.failed')
            ->exists()
    )->toBeTrue();
});

test('execution completion does not fall back to triggered_by when no preferences exist', function () {
    $execution = Run::factory()->completed()->create([
        'workspace_id' => $this->workspace->id,
        'workflow_id' => $this->workflow->id,
        'triggered_by' => $this->user->id,
    ]);

    event(new ExecutionCompletedEvent($execution));

    expect(InAppNotification::where('user_id', $this->user->id)->exists())->toBeFalse();
});

// ── DispatchExecutionNotifications — email ────────────────────────────────────

test('execution failure sends email when email preference is enabled', function () {
    Notification::fake();

    NotificationPreference::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'event_key' => 'execution.failed',
        'in_app' => false,
        'email' => true,
    ]);

    $execution = Run::factory()->create([
        'workspace_id' => $this->workspace->id,
        'workflow_id' => $this->workflow->id,
        'error' => ['message' => 'Node failed'],
    ]);

    event(new ExecutionFailedEvent($execution, 'Node failed'));

    Notification::assertSentTo($this->user, ExecutionFailedNotification::class);
});

test('execution completion sends email when email preference is enabled', function () {
    Notification::fake();

    NotificationPreference::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'event_key' => 'execution.completed',
        'in_app' => false,
        'email' => true,
    ]);

    $execution = Run::factory()->completed()->create([
        'workspace_id' => $this->workspace->id,
        'workflow_id' => $this->workflow->id,
    ]);

    event(new ExecutionCompletedEvent($execution));

    Notification::assertSentTo($this->user, ExecutionCompletedNotification::class);
});

// ── DispatchExecutionNotifications — channels ─────────────────────────────────

test('execution failure delivers to configured notification channel', function () {
    Http::fake(['hooks.slack.test/*' => Http::response('ok')]);

    $channel = NotificationChannel::factory()->create([
        'workspace_id' => $this->workspace->id,
        'type' => 'slack',
        'config' => ['url' => 'https://hooks.slack.test/abc'],
        'is_active' => true,
    ]);

    NotificationPreference::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'event_key' => 'execution.failed',
        'in_app' => false,
        'email' => false,
        'channel_ids' => [$channel->id],
    ]);

    $execution = Run::factory()->create([
        'workspace_id' => $this->workspace->id,
        'workflow_id' => $this->workflow->id,
        'error' => ['message' => 'Failed'],
    ]);

    event(new ExecutionFailedEvent($execution, 'Failed'));

    Http::assertSent(fn ($req) => str_contains($req->url(), 'hooks.slack.test'));
});

// ── Stripe payment failure ────────────────────────────────────────────────────

test('stripe payment_failed notifies workspace owner with in-app and email', function () {
    Notification::fake();

    $plan = Plan::where('slug', 'starter')->first();
    Subscription::factory()->create([
        'workspace_id' => $this->workspace->id,
        'plan_id' => $plan->id,
        'stripe_customer_id' => 'cus_test123',
        'status' => 'active',
    ]);

    $invoiceData = ['object' => 'invoice', 'customer' => 'cus_test123'];

    $fakeEvent = StripeEvent::constructFrom([
        'id' => 'evt_test_failed',
        'type' => 'invoice.payment_failed',
        'data' => ['object' => $invoiceData],
    ]);

    $stripe = $this->mock(StripeService::class);
    $stripe->shouldReceive('constructWebhookEvent')->once()->andReturn($fakeEvent);

    $this->postJson('/api/v1/webhooks/stripe', $invoiceData, ['Stripe-Signature' => 'sig'])
        ->assertOk();

    Notification::assertSentTo($this->user, PaymentFailedNotification::class);

    expect(
        InAppNotification::where('user_id', $this->user->id)
            ->where('type', 'billing.payment_failed')
            ->exists()
    )->toBeTrue();
});
