<?php

use App\Actions\Billing\OpenUsagePeriodForSubscriptionAction;
use App\Enums\SuperAdmin\SuperAdminRole;
use App\Models\Billing\ProcessedWebhookEvent;
use App\Models\Billing\StripeWebhookFailure;
use App\Models\SuperAdmin\SuperAdminAuditLog;
use App\Models\User;
use App\Services\SuperAdmin\Billing\StripeEvents;
use App\Services\Workspaces\WorkspaceService;
use Stripe\Exception\ApiConnectionException;
use Stripe\Exception\InvalidRequestException;

/**
 * @return array<string, mixed>
 */
function saWebhookPayload(string $eventId, string $customerId): array
{
    return [
        'id' => $eventId,
        'type' => 'customer.subscription.created',
        'data' => [
            'object' => [
                'id' => 'sub_'.$eventId,
                'customer' => $customerId,
                'status' => 'active',
                'items' => ['data' => [['id' => 'si_'.$eventId, 'price' => ['id' => 'price_replay', 'product' => 'prod_replay']]]],
            ],
        ],
    ];
}

/**
 * Makes Stripe "return" `$payload`, or throw `$throw`, for any event id.
 *
 * @param  array<string, mixed>  $payload
 */
function saFakeStripeEvents(array $payload = [], ?Throwable $throw = null): void
{
    app()->instance(StripeEvents::class, new class($payload, $throw) extends StripeEvents
    {
        /** @param array<string, mixed> $payload */
        public function __construct(private array $payload, private ?Throwable $throw) {}

        public function retrieve(string $eventId): array
        {
            if ($this->throw !== null) {
                throw $this->throw;
            }

            return $this->payload;
        }
    });
}

beforeEach(function () {
    $this->finance = superAdmin(SuperAdminRole::Finance);
    $this->headers = superAdminHeaders($this->finance);
    $this->workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
    $this->workspace->forceFill(['stripe_id' => 'cus_replay'])->save();
});

it('keeps a trace of an event the handler failed on, and clears it when the event goes through', function () {
    $payload = saWebhookPayload('evt_trace', 'cus_replay');
    $this->withoutExceptionHandling();

    $this->mock(OpenUsagePeriodForSubscriptionAction::class)->shouldReceive('execute')->once()->andThrow(new RuntimeException('database unavailable'));
    expect(fn () => $this->postJson('/api/stripe/webhook', $payload))->toThrow(RuntimeException::class);

    $failure = StripeWebhookFailure::query()->where('stripe_event_id', 'evt_trace')->sole();

    expect($failure->attempts)->toBe(1)
        ->and($failure->type)->toBe('customer.subscription.created')
        ->and($failure->error)->toContain('database unavailable')
        ->and($failure->resolved_at)->toBeNull();

    $this->mock(OpenUsagePeriodForSubscriptionAction::class)->shouldReceive('execute')->once()->andThrow(new RuntimeException('still down'));
    expect(fn () => $this->postJson('/api/stripe/webhook', $payload))->toThrow(RuntimeException::class);

    expect($failure->fresh()->attempts)->toBe(2)->and($failure->fresh()->error)->toContain('still down');

    $this->mock(OpenUsagePeriodForSubscriptionAction::class)->shouldReceive('execute')->once();
    $this->postJson('/api/stripe/webhook', $payload)->assertOk();

    expect($failure->fresh()->resolved_at)->not->toBeNull()
        ->and(StripeWebhookFailure::query()->count())->toBe(1);
});

it('lists failures, open ones first, and filters them', function () {
    StripeWebhookFailure::factory()->create(['stripe_event_id' => 'evt_old_resolved', 'last_failed_at' => now()->subDay()])->forceFill(['resolved_at' => now()])->save();
    StripeWebhookFailure::factory()->create(['stripe_event_id' => 'evt_open_a', 'last_failed_at' => now()->subHours(3)]);
    StripeWebhookFailure::factory()->create(['stripe_event_id' => 'evt_open_b', 'last_failed_at' => now()->subHour()]);

    $this->withHeaders($this->headers)->getJson(saUrl('billing/webhooks/failures'))
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.event_id', 'evt_open_b')
        ->assertJsonPath('data.1.event_id', 'evt_open_a')
        ->assertJsonPath('data.2.event_id', 'evt_old_resolved');

    $this->withHeaders($this->headers)->getJson(saUrl('billing/webhooks/failures?status=open'))->assertJsonCount(2, 'data');
    $this->withHeaders($this->headers)->getJson(saUrl('billing/webhooks/failures?status=resolved'))->assertJsonCount(1, 'data');
    $this->withHeaders($this->headers)->getJson(saUrl('billing/webhooks/failures?status=maybe'))->assertUnprocessable();
});

it('lets any role who can see billing read the failures', function (SuperAdminRole $role) {
    $this->withHeaders(superAdminHeaders(superAdmin($role)))->getJson(saUrl('billing/webhooks/failures'))->assertOk();
})->with(array_map(fn (SuperAdminRole $role) => [$role], SuperAdminRole::cases()));

it('replays a failed event through the live handler and records why', function () {
    StripeWebhookFailure::factory()->create(['stripe_event_id' => 'evt_replay_me', 'type' => 'customer.subscription.created']);
    saFakeStripeEvents(saWebhookPayload('evt_replay_me', 'cus_replay'));
    $this->mock(OpenUsagePeriodForSubscriptionAction::class)->shouldReceive('execute')->once();

    $this->withHeaders($this->headers)
        ->postJson(saUrl('billing/webhooks/evt_replay_me/replay'), ['reason' => 'Stripe retries ran out overnight'])
        ->assertOk()
        ->assertJsonPath('data.event_id', 'evt_replay_me')
        ->assertJsonPath('data.type', 'customer.subscription.created');

    expect(ProcessedWebhookEvent::query()->where('stripe_event_id', 'evt_replay_me')->exists())->toBeTrue()
        ->and(StripeWebhookFailure::query()->sole()->resolved_at)->not->toBeNull();

    $log = SuperAdminAuditLog::query()->where('action', 'billing.webhook_replayed')->firstOrFail();

    expect($log->reason)->toBe('Stripe retries ran out overnight')
        ->and($log->super_admin_id)->toBe($this->finance->id)
        ->and($log->after)->toBe(['event_id' => 'evt_replay_me', 'type' => 'customer.subscription.created']);
});

it('replays an event that never left a failure row too', function () {
    saFakeStripeEvents(saWebhookPayload('evt_unrecorded', 'cus_replay'));
    $this->mock(OpenUsagePeriodForSubscriptionAction::class)->shouldReceive('execute')->once();

    $this->withHeaders($this->headers)
        ->postJson(saUrl('billing/webhooks/evt_unrecorded/replay'), ['reason' => 'Customer paid, plan never arrived'])
        ->assertOk();

    expect(ProcessedWebhookEvent::query()->where('stripe_event_id', 'evt_unrecorded')->exists())->toBeTrue();
});

it('refuses to replay an event that was already processed', function () {
    ProcessedWebhookEvent::query()->create(['stripe_event_id' => 'evt_done', 'type' => 'x', 'processed_at' => now()]);
    saFakeStripeEvents(saWebhookPayload('evt_done', 'cus_replay'));

    $this->withHeaders($this->headers)
        ->postJson(saUrl('billing/webhooks/evt_done/replay'), ['reason' => 'Trying anyway, just in case'])
        ->assertStatus(409);
});

it('says so when Stripe does not know the event', function () {
    saFakeStripeEvents(throw: new InvalidRequestException('No such event'));

    $this->withHeaders($this->headers)
        ->postJson(saUrl('billing/webhooks/evt_gone/replay'), ['reason' => 'Looking for an old event'])
        ->assertNotFound();
});

it('says so when Stripe cannot be reached', function () {
    saFakeStripeEvents(throw: new ApiConnectionException('timeout'));

    $this->withHeaders($this->headers)
        ->postJson(saUrl('billing/webhooks/evt_gone/replay'), ['reason' => 'Looking for an old event'])
        ->assertStatus(502);
});

it('reports a handler that fails again and keeps the failure open', function () {
    StripeWebhookFailure::factory()->create(['stripe_event_id' => 'evt_stubborn']);
    saFakeStripeEvents(saWebhookPayload('evt_stubborn', 'cus_replay'));
    $this->mock(OpenUsagePeriodForSubscriptionAction::class)->shouldReceive('execute')->once()->andThrow(new RuntimeException('database unavailable'));

    $this->withHeaders($this->headers)
        ->postJson(saUrl('billing/webhooks/evt_stubborn/replay'), ['reason' => 'Database is back, trying again'])
        ->assertStatus(502)
        ->assertJsonPath('message', 'The handler failed again: database unavailable');

    $failure = StripeWebhookFailure::query()->sole();

    expect($failure->attempts)->toBe(2)
        ->and($failure->resolved_at)->toBeNull()
        ->and(ProcessedWebhookEvent::query()->where('stripe_event_id', 'evt_stubborn')->exists())->toBeFalse()
        ->and(SuperAdminAuditLog::query()->where('action', 'billing.webhook_replayed')->exists())->toBeFalse();
});

it('needs a reason, a well-formed event id and the right role', function () {
    saFakeStripeEvents(saWebhookPayload('evt_x', 'cus_replay'));

    $this->withHeaders($this->headers)->postJson(saUrl('billing/webhooks/evt_x/replay'))
        ->assertUnprocessable()->assertJsonValidationErrors('reason');

    $this->withHeaders($this->headers)->postJson(saUrl('billing/webhooks/not-an-event/replay'), ['reason' => 'Some good reason'])
        ->assertNotFound();

    foreach ([SuperAdminRole::Operator, SuperAdminRole::Support, SuperAdminRole::Viewer] as $role) {
        $this->withHeaders(superAdminHeaders(superAdmin($role)))
            ->postJson(saUrl('billing/webhooks/evt_x/replay'), ['reason' => 'Some good reason'])
            ->assertForbidden();
    }
});
