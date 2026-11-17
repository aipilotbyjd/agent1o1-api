<?php

use App\Enums\Billing\CancellationOutcome;
use App\Enums\Workspaces\AuditAction;
use App\Enums\Workspaces\Role;
use App\Models\Billing\Plan;
use App\Models\Billing\Subscription;
use App\Models\Billing\SubscriptionCancellation;
use App\Models\User;
use App\Models\Workspaces\AuditLog;
use App\Models\Workspaces\Workspace;
use App\Services\Notifications\AdminAlerts;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;
use Stripe\Coupon as StripeCoupon;
use Stripe\Exception\InvalidRequestException;
use Stripe\StripeClient;
use Stripe\Subscription as StripeSubscription;

/**
 * The cancellation flow: a reason, at most one offer that fits it, and a
 * record of how it ended. `StripeClient` is stubbed the way
 * `BillingPortalTest` does, recording what the app asked Stripe to do.
 *
 * @param  list<string>  $existingCoupons  Coupon ids Stripe already has.
 * @param  list<string>  $existingDiscounts  Discount ids already on the subscription.
 */
function stubStripeForCancellation(array $existingCoupons = [], array $existingDiscounts = []): object
{
    $recorder = new class
    {
        /** @var list<array<string, mixed>> */
        public array $subscriptionUpdates = [];

        /** @var list<array<string, mixed>> */
        public array $couponsCreated = [];
    };

    $subscriptions = new class($recorder, $existingDiscounts)
    {
        /**
         * @param  list<string>  $existingDiscounts
         */
        public function __construct(private object $recorder, private array $existingDiscounts) {}

        public function retrieve(string $id, $params = null): StripeSubscription
        {
            return StripeSubscription::constructFrom(['id' => $id, 'status' => 'active', 'discounts' => $this->existingDiscounts]);
        }

        public function update(string $id, array $params = []): StripeSubscription
        {
            $this->recorder->subscriptionUpdates[] = $params;

            return StripeSubscription::constructFrom(['id' => $id, 'status' => 'active']);
        }
    };

    $coupons = new class($recorder, $existingCoupons)
    {
        /**
         * @param  list<string>  $existing
         */
        public function __construct(private object $recorder, private array $existing) {}

        public function retrieve(string $id): StripeCoupon
        {
            if (! in_array($id, $this->existing, true)) {
                throw InvalidRequestException::factory("No such coupon: {$id}");
            }

            return StripeCoupon::constructFrom(['id' => $id]);
        }

        public function create(array $params = []): StripeCoupon
        {
            $this->recorder->couponsCreated[] = $params;

            return StripeCoupon::constructFrom(['id' => $params['id']]);
        }
    };

    app()->bind(StripeClient::class, fn (): object => new class($subscriptions, $coupons)
    {
        public function __construct(public object $subscriptions, public object $coupons) {}
    });

    return $recorder;
}

/**
 * A workspace on a paid plan, signed in as its owner.
 *
 * @param  array<string, mixed>  $subscription  Overrides for the subscription row.
 */
function cancellationWorkspace(string $interval = 'monthly', array $subscription = []): Workspace
{
    $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
    $workspace->forceFill(['stripe_id' => 'cus_cancellation'])->save();

    $plan = Plan::factory()->create([
        'price_monthly' => 4900,
        'price_yearly' => 49000,
        'stripe_price_id_monthly' => 'price_pro_monthly',
        'stripe_price_id_yearly' => 'price_pro_yearly',
    ]);

    Subscription::query()->create(array_merge([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'type' => 'default',
        'stripe_id' => 'sub_cancellation',
        'stripe_status' => 'active',
        'stripe_price' => "price_pro_{$interval}",
    ], $subscription));

    Passport::actingAs($workspace->owner);

    return $workspace->fresh();
}

function cancellationUrl(Workspace $workspace, string $path = 'cancellation'): string
{
    return "/api/v1/workspaces/{$workspace->id}/billing/subscription/{$path}";
}

/**
 * @return array<string, mixed>|null
 */
function offerShownFor(Workspace $workspace, string $reason): ?array
{
    $reasons = test()->getJson(cancellationUrl($workspace))->assertOk()->json('data.reasons');

    return collect($reasons)->firstWhere('value', $reason)['offer'];
}

it('offers 5% off for three months to a monthly customer who says it costs too much', function () {
    $workspace = cancellationWorkspace();

    expect(offerShownFor($workspace, 'too_expensive'))
        ->toBe(['type' => 'discount', 'percent_off' => 5, 'months' => 3]);
});

it('lists every reason with its label and makes no offer where none fits', function () {
    $workspace = cancellationWorkspace();

    $reasons = $this->getJson(cancellationUrl($workspace))->assertOk()->json('data.reasons');

    expect(array_column($reasons, 'value'))->toBe([
        'too_expensive', 'not_using_enough', 'missing_feature', 'too_hard', 'switching_tool', 'project_ended', 'other',
    ]);
    expect(collect($reasons)->firstWhere('value', 'missing_feature'))->toMatchArray([
        'label' => 'It is missing something I need',
        'comment_prompt' => 'What is missing?',
        'offer' => null,
    ]);
    expect(offerShownFor($workspace, 'too_hard'))->toMatchArray(['type' => 'help']);
});

it('never offers a discount when the percent is set to zero', function () {
    config(['billing.cancellation.discount_percent' => 0]);

    $workspace = cancellationWorkspace();

    expect(offerShownFor($workspace, 'too_expensive'))->toBeNull();
});

it('does not offer the discount on a yearly subscription', function () {
    $workspace = cancellationWorkspace(interval: 'yearly');

    expect(offerShownFor($workspace, 'too_expensive'))->toBeNull();
});

it('does not offer the discount again inside the cooldown, and does after it', function () {
    $workspace = cancellationWorkspace();
    $earlier = SubscriptionCancellation::factory()->forWorkspace($workspace)->discountAccepted()->create();

    expect(offerShownFor($workspace, 'too_expensive'))->toBeNull();

    $earlier->forceFill(['created_at' => now()->subMonths(13)])->save();

    expect(offerShownFor($workspace, 'too_expensive'))->toMatchArray(['type' => 'discount']);
});

it('makes no offer to a subscription that is on trial, failing payment, or already ending', function (array $state) {
    $workspace = cancellationWorkspace(subscription: $state);

    expect(offerShownFor($workspace, 'too_expensive'))->toBeNull();
    expect(offerShownFor($workspace, 'too_hard'))->toBeNull();
})->with([
    'on trial' => [['stripe_status' => 'trialing', 'trial_ends_at' => '2099-01-01 00:00:00']],
    'failing payment' => [['dunning_started_at' => '2026-11-01 00:00:00']],
    'already ending' => [['ends_at' => '2099-01-01 00:00:00']],
]);

it('offers a downgrade only when a cheaper paid plan is on sale for the same interval', function () {
    $workspace = cancellationWorkspace();

    expect(offerShownFor($workspace, 'not_using_enough'))->toBeNull();

    Plan::factory()->create(['price_monthly' => 0]);
    expect(offerShownFor($workspace, 'not_using_enough'))->toBeNull();

    Plan::factory()->create(['price_monthly' => 1900]);
    expect(offerShownFor($workspace, 'not_using_enough'))->toMatchArray(['type' => 'downgrade']);
});

it('applies the discount to the stripe subscription and records the customer as saved', function () {
    $workspace = cancellationWorkspace();
    $stripe = stubStripeForCancellation();

    $this->postJson(cancellationUrl($workspace, 'cancellation/accept-offer'), [
        'offer' => 'discount',
        'reason' => 'too_expensive',
        'comment' => 'Budget got cut.',
    ])
        ->assertOk()
        ->assertJsonPath('data.outcome', 'discount_accepted')
        ->assertJsonPath('data.percent_off', 5)
        ->assertJsonPath('data.months', 3);

    expect($stripe->couponsCreated)->toHaveCount(1);
    expect($stripe->couponsCreated[0])->toMatchArray([
        'id' => 'retention-5pct-3mo',
        'percent_off' => 5,
        'duration' => 'repeating',
        'duration_in_months' => 3,
    ]);
    expect($stripe->subscriptionUpdates)->toBe([['discounts' => [['coupon' => 'retention-5pct-3mo']]]]);

    $record = SubscriptionCancellation::query()->sole();
    expect($record->outcome)->toBe(CancellationOutcome::DiscountAccepted);
    expect($record->only(['workspace_id', 'user_id', 'comment', 'discount_percent', 'discount_months']))->toBe([
        'workspace_id' => $workspace->id,
        'user_id' => $workspace->owner_id,
        'comment' => 'Budget got cut.',
        'discount_percent' => 5,
        'discount_months' => 3,
    ]);
    expect($workspace->subscription('default')->ends_at)->toBeNull();
    expect(AuditLog::query()->where('action', AuditAction::SubscriptionCancellationOfferAccepted->value)->sole()->metadata)
        ->toBe(['offer' => 'discount', 'reason' => 'too_expensive']);
});

it('reuses the stripe coupon and keeps a discount the customer already holds', function () {
    $workspace = cancellationWorkspace();
    $stripe = stubStripeForCancellation(existingCoupons: ['retention-5pct-3mo'], existingDiscounts: ['di_launch']);

    $this->postJson(cancellationUrl($workspace, 'cancellation/accept-offer'), ['offer' => 'discount', 'reason' => 'too_expensive'])
        ->assertOk();

    expect($stripe->couponsCreated)->toBe([]);
    expect($stripe->subscriptionUpdates)->toBe([
        ['discounts' => [['discount' => 'di_launch'], ['coupon' => 'retention-5pct-3mo']]],
    ]);
});

it('refuses a second discount for the same workspace', function () {
    $workspace = cancellationWorkspace();
    $stripe = stubStripeForCancellation();
    $payload = ['offer' => 'discount', 'reason' => 'too_expensive'];

    $this->postJson(cancellationUrl($workspace, 'cancellation/accept-offer'), $payload)->assertOk();
    $this->postJson(cancellationUrl($workspace, 'cancellation/accept-offer'), $payload)
        ->assertStatus(422)
        ->assertJsonPath('message', 'That offer is not available for this subscription.');

    expect($stripe->subscriptionUpdates)->toHaveCount(1);
});

it('refuses a discount for a reason that does not earn one', function () {
    $workspace = cancellationWorkspace();
    $stripe = stubStripeForCancellation();

    $this->postJson(cancellationUrl($workspace, 'cancellation/accept-offer'), ['offer' => 'discount', 'reason' => 'other'])
        ->assertStatus(422);

    expect($stripe->subscriptionUpdates)->toBe([]);
    expect(SubscriptionCancellation::query()->count())->toBe(0);
});

it('refuses the downgrade offer, which is taken on the plans screen instead', function () {
    $workspace = cancellationWorkspace();
    Plan::factory()->create(['price_monthly' => 1900]);

    $this->postJson(cancellationUrl($workspace, 'cancellation/accept-offer'), ['offer' => 'downgrade', 'reason' => 'not_using_enough'])
        ->assertStatus(422);
});

it('alerts the team and records a help request without touching stripe', function () {
    $workspace = cancellationWorkspace();
    $stripe = stubStripeForCancellation();

    $this->mock(AdminAlerts::class)
        ->shouldReceive('raise')
        ->once()
        ->withArgs(fn (string $key, string $title, ?string $body, array $context): bool => $key === 'billing.cancellation_help_requested'
            && $body === 'Runs keep timing out.'
            && $context['workspace_id'] === $workspace->id)
        ->andReturn(true);

    $this->postJson(cancellationUrl($workspace, 'cancellation/accept-offer'), [
        'offer' => 'help',
        'reason' => 'too_hard',
        'comment' => 'Runs keep timing out.',
    ])->assertOk()->assertJsonPath('data.outcome', 'help_requested');

    expect($stripe->subscriptionUpdates)->toBe([]);
    expect(SubscriptionCancellation::query()->sole()->outcome)->toBe(CancellationOutcome::HelpRequested);
});

it('rejects an accept request with no offer or reason, or with unknown ones', function () {
    $workspace = cancellationWorkspace();

    $this->postJson(cancellationUrl($workspace, 'cancellation/accept-offer'), [])
        ->assertJsonValidationErrors(['offer', 'reason']);

    $this->postJson(cancellationUrl($workspace, 'cancellation/accept-offer'), ['offer' => 'free_year', 'reason' => 'bored'])
        ->assertJsonValidationErrors(['offer', 'reason']);
});

it('cancels at period end and records the reason, comment and the offer that was shown', function () {
    $workspace = cancellationWorkspace(subscription: ['created_at' => now()->subMonths(7)]);
    $stripe = stubStripeForCancellation();

    $this->postJson(cancellationUrl($workspace, 'cancel'), ['reason' => 'too_expensive', 'comment' => 'Too pricey for us.'])
        ->assertOk();

    expect($stripe->subscriptionUpdates)->toBe([['cancel_at_period_end' => true]]);

    $record = SubscriptionCancellation::query()->sole();
    expect($record->outcome)->toBe(CancellationOutcome::Canceled);
    expect($record->only(['comment', 'months_subscribed', 'plan_id']))->toBe([
        'comment' => 'Too pricey for us.',
        'months_subscribed' => 7,
        'plan_id' => $workspace->subscription('default')->plan_id,
    ]);
    expect([$record->reason->value, $record->offer->value, $record->interval->value])->toBe(['too_expensive', 'discount', 'monthly']);
    expect(AuditLog::query()->where('action', AuditAction::SubscriptionCanceled->value)->sole()->metadata)
        ->toBe(['reason' => 'too_expensive']);
});

it('still cancels when no reason is sent', function () {
    $workspace = cancellationWorkspace();
    $stripe = stubStripeForCancellation();

    $this->postJson(cancellationUrl($workspace, 'cancel'))->assertOk();

    expect($stripe->subscriptionUpdates)->toBe([['cancel_at_period_end' => true]]);
    expect(SubscriptionCancellation::query()->sole()->reason)->toBeNull();
});

it('rejects an unknown cancellation reason without cancelling', function () {
    $workspace = cancellationWorkspace();
    $stripe = stubStripeForCancellation();

    $this->postJson(cancellationUrl($workspace, 'cancel'), ['reason' => 'bored'])
        ->assertJsonValidationErrors(['reason']);

    expect($stripe->subscriptionUpdates)->toBe([]);
});

it('404s the cancellation screen for a workspace with no subscription', function () {
    $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Free Co']);
    Passport::actingAs($workspace->owner);

    $this->getJson(cancellationUrl($workspace))->assertNotFound();
});

it('denies an editor, who can view billing but not manage it', function () {
    $workspace = cancellationWorkspace();
    $stripe = stubStripeForCancellation();

    $editor = User::factory()->create();
    $workspace->members()->create(['user_id' => $editor->id, 'role' => Role::Editor, 'joined_at' => now()]);
    Passport::actingAs($editor);

    $this->getJson(cancellationUrl($workspace))->assertForbidden();
    $this->postJson(cancellationUrl($workspace, 'cancellation/accept-offer'), ['offer' => 'discount', 'reason' => 'too_expensive'])
        ->assertForbidden();

    expect($stripe->subscriptionUpdates)->toBe([]);
});

it('denies someone outside the workspace', function () {
    $workspace = cancellationWorkspace();
    $stripe = stubStripeForCancellation();

    Passport::actingAs(User::factory()->create());

    $this->getJson(cancellationUrl($workspace))->assertForbidden();
    $this->postJson(cancellationUrl($workspace, 'cancellation/accept-offer'), ['offer' => 'discount', 'reason' => 'too_expensive'])
        ->assertForbidden();

    expect($stripe->subscriptionUpdates)->toBe([]);
});

it('requires sign-in', function () {
    $workspace = cancellationWorkspace();
    app('auth')->forgetGuards();

    $this->getJson(cancellationUrl($workspace))->assertUnauthorized();
});
