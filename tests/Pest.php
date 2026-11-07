<?php

use App\Actions\Referrals\AttributeReferralAction;
use App\Actions\Workflows\StartWorkflowRunAction;
use App\Enums\Billing\BillingInterval;
use App\Enums\SuperAdmin\SuperAdminRole;
use App\Jobs\Triggers\PollTrigger;
use App\Models\Billing\Plan;
use App\Models\Billing\Subscription;
use App\Models\Referrals\Referral;
use App\Models\Referrals\ReferralProgram;
use App\Models\Runs\NodeRun;
use App\Models\Runs\Run;
use App\Models\SuperAdmin\SuperAdmin;
use App\Models\SuperAdmin\SuperAdminToken;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Services\Billing\StripeCustomerBalance;
use App\Services\Referrals\ReferralCodes;
use App\Services\SuperAdmin\Auth\SuperAdminTokenService;
use App\Services\SuperAdmin\Pricing\PricingStripe;
use App\Services\Workflows\NodeRegistry;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Passport\Client;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        Client::query()->create([
            'id' => config('passport.password_client_id'),
            'name' => 'Testing Password Grant Client',
            'secret' => config('passport.password_client_secret'),
            'redirect_uris' => [],
            'grant_types' => ['password', 'refresh_token', 'social_exchange'],
            'revoked' => false,
        ]);
    })
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/*
|--------------------------------------------------------------------------
| Referral program helpers
|--------------------------------------------------------------------------
*/

/**
 * A user who owns a workspace, the way a real signup leaves them.
 *
 * @param  array<string, mixed>  $attributes
 */
function referralUser(array $attributes = []): User
{
    $user = User::factory()->create($attributes);

    app(WorkspaceService::class)->create($user, ['name' => "{$user->name}'s Workspace"]);

    return $user->fresh();
}

/**
 * Signs `$referred` (a fresh unverified user by default) up through
 * `$referrer`'s code under `$program` (a new default program by default).
 */
function referUser(User $referrer, ?User $referred = null, ?ReferralProgram $program = null): ?Referral
{
    $program ??= ReferralProgram::factory()->asDefault()->create();
    $code = app(ReferralCodes::class)->forUser($referrer);

    if (! $program->is_default) {
        $code->update(['program_id' => $program->id]);
    }

    return app(AttributeReferralAction::class)->execute($referred ?? referralUser(['email_verified_at' => null]), $code->code);
}

/**
 * Runs one poll in this process, even while `PollTrigger` is faked.
 *
 * Tests fake `PollTrigger` so publishing a flow doesn't start a poll of its
 * own. The installed Laravel's `dispatch_sync` sends a queueable job through
 * the (faked) queue, so it would never run; the bus runs it directly instead.
 */
function pollNow(PollTrigger $job): void
{
    app(Dispatcher::class)->dispatchNow($job);
}

/**
 * Swaps Stripe customer-balance calls for an in-memory record.
 */
function fakeStripeBalance(): StripeCustomerBalance
{
    $fake = new class extends StripeCustomerBalance
    {
        /** @var list<array{workspace: string, cents: int}> */
        public array $credits = [];

        /** @var list<array{workspace: string, cents: int}> */
        public array $debits = [];

        public function credit(Workspace $workspace, int $amountCents, string $description, ?string $operationKey = null): string
        {
            $this->credits[] = ['workspace' => $workspace->id, 'cents' => $amountCents];

            return 'cbtxn_'.count($this->credits);
        }

        public function debit(Workspace $workspace, int $amountCents, string $description, ?string $operationKey = null): string
        {
            $this->debits[] = ['workspace' => $workspace->id, 'cents' => $amountCents];

            return 'cbtxn_debit_'.count($this->debits);
        }
    };

    app()->instance(StripeCustomerBalance::class, $fake);

    return $fake;
}

/**
 * A `invoice.payment_succeeded` event for `$customerId`.
 *
 * @param  array<string, mixed>  $invoice
 * @return array<string, mixed>
 */
function referralInvoicePaidPayload(string $eventId, string $customerId, int $amountPaid, array $invoice = []): array
{
    return [
        'id' => $eventId,
        'type' => 'invoice.payment_succeeded',
        'data' => [
            'object' => array_merge([
                'id' => 'in_'.$eventId,
                'customer' => $customerId,
                'amount_paid' => $amountPaid,
                'total' => $amountPaid,
                'total_excluding_tax' => $amountPaid,
                'payment_intent' => 'pi_'.$eventId,
                'currency' => 'usd',
                'billing_reason' => 'subscription_create',
                'lines' => ['data' => []],
            ], $invoice),
        ],
    ];
}

/**
 * The text inside an `UntrustedContent::wrap()` block: agent tools wrap what
 * they return from outside the conversation, and most assertions care about
 * what was returned, not the wrapper.
 */
function unwrapUntrusted(string $wrapped): string
{
    return preg_replace('#^<untrusted_content[^>]*>\n|\n</untrusted_content>$#', '', $wrapped);
}

/**
 * Runs one node as the only step of a real, published workflow and returns
 * its run: the closest a test gets to what a user's run does, without
 * reaching an outside service (fake those first).
 *
 * @param  array<string, mixed>  $config
 * @param  array<string, mixed>  $input  the run input, read with {{ input.x }}
 */
function runNodeStep(string $type, array $config, array $input = [], ?Workspace $workspace = null): NodeRun
{
    $owner = $workspace?->owner ?? User::factory()->create();
    $workspace ??= app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph(['nodes' => [['key' => 'step', 'type' => $type, 'config' => $config]], 'edges' => []]);
    $workflow->publishVersion(publisher: $owner);

    $run = app(StartWorkflowRunAction::class)->execute($workflow->fresh(), $input)->fresh(['nodeRuns']);

    return $run->nodeRuns->firstWhere('key', 'step');
}

/**
 * Runs a whole graph as a real, published workflow and returns the finished
 * run with its steps loaded, keyed by step key where a test reads them.
 *
 * @param  array{nodes: array<int, array<string, mixed>>, edges?: array<int, array<string, mixed>>}  $graph
 * @param  array<string, mixed>  $input
 */
function runGraph(array $graph, array $input = [], ?Workspace $workspace = null): Run
{
    $owner = $workspace?->owner ?? User::factory()->create();
    $workspace ??= app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph(['edges' => [], ...$graph]);
    $workflow->publishVersion(publisher: $owner);

    return app(StartWorkflowRunAction::class)->execute($workflow->fresh(), $input)->fresh(['nodeRuns']);
}

/**
 * Calls a node's `execute()` directly, skipping the engine. The engine checks
 * a step's settings before it runs (a blank required value never reaches the
 * node, and a list setting is coerced), so a node's own guards can only be
 * tested this way. Throws whatever the node throws.
 *
 * @param  array<string, mixed>  $config
 * @return array<string, mixed>
 */
function executeNode(string $type, array $config, ?Workspace $workspace = null): array
{
    $run = (new Run)->forceFill(['id' => (string) Str::uuid(), 'workspace_id' => $workspace?->id ?? (string) Str::uuid()]);

    return app(NodeRegistry::class)->resolve($type)->execute($run, $config, []);
}

/*
|--------------------------------------------------------------------------
| Super Admin helpers
|--------------------------------------------------------------------------
*/

/**
 * A URL under the Super Admin API.
 */
function saUrl(string $path): string
{
    return '/api/'.config('super_admin.prefix').'/'.ltrim($path, '/');
}

/**
 * A fully provisioned super admin: password already changed, two-factor
 * authentication confirmed (an owner unless a role is given).
 *
 * @param  array<string, mixed>  $attributes
 */
function superAdmin(SuperAdminRole $role = SuperAdminRole::Owner, array $attributes = []): SuperAdmin
{
    return SuperAdmin::factory()->role($role)->withTwoFactor()->create($attributes);
}

/**
 * An `Authorization` header carrying a fresh token for `$admin`.
 *
 * @return array<string, string>
 */
function superAdminHeaders(SuperAdmin $admin, string $scope = SuperAdminToken::SCOPE_FULL): array
{
    $issued = app(SuperAdminTokenService::class)->issue($admin, $scope);

    return ['Authorization' => 'Bearer '.$issued['access_token']];
}

/**
 * The code an authenticator app would show right now.
 */
function superAdminTotp(SuperAdmin $admin): string
{
    return app(Google2FA::class)->getCurrentOtp($admin->two_factor_secret);
}

/**
 * The reason and fresh authenticator code every sensitive write requires.
 *
 * @param  array<string, mixed>  $payload
 * @return array<string, mixed>
 */
function stepUp(SuperAdmin $admin, array $payload = []): array
{
    return ['reason' => 'Verified request from the account owner', 'totp_code' => superAdminTotp($admin), ...$payload];
}

/**
 * Swaps the pricing manager's Stripe gateway for one that records its calls
 * and hands back predictable ids, so nothing reaches the network. Set
 * `$fake->failPriceAt` to the 1-based price call that should throw.
 */
function fakePricingStripe(): PricingStripe
{
    $fake = new class extends PricingStripe
    {
        /** @var list<array<string, mixed>> */
        public array $calls = [];

        public ?int $failPriceAt = null;

        /** @var array<string, array{exists: bool, active: bool, amount_cents: int|null}> */
        public array $inspections = [];

        private int $sequence = 0;

        public function createProduct(string $name, string $planSlug, string $idempotencyKey): string
        {
            $this->calls[] = ['createProduct', $name];

            return 'prod_fake_'.++$this->sequence;
        }

        public function renameProduct(string $productId, string $name): void
        {
            $this->calls[] = ['renameProduct', $productId, $name];
        }

        public function createPrice(string $productId, BillingInterval $interval, int $amountCents, string $idempotencyKey): string
        {
            $this->calls[] = ['createPrice', $interval->value, $amountCents, $idempotencyKey];

            if ($this->failPriceAt !== null && count($this->prices()) === $this->failPriceAt) {
                throw new RuntimeException('Stripe is down.');
            }

            return 'price_fake_'.++$this->sequence;
        }

        public function archivePrice(string $priceId): void
        {
            $this->calls[] = ['archivePrice', $priceId];
        }

        public function inspectPrice(string $priceId): array
        {
            return $this->inspections[$priceId] ?? ['exists' => true, 'active' => true, 'amount_cents' => null];
        }

        public function createCoupon(array $params, string $idempotencyKey): string
        {
            $this->calls[] = ['createCoupon', $params];

            return 'coupon_fake_'.++$this->sequence;
        }

        public function createPromotionCode(array $params, string $idempotencyKey): string
        {
            $this->calls[] = ['createPromotionCode', $params];

            return 'promo_fake_'.++$this->sequence;
        }

        public function setPromotionCodeActive(string $promotionCodeId, bool $active): void
        {
            $this->calls[] = ['setPromotionCodeActive', $promotionCodeId, $active];
        }

        public function timesRedeemed(string $promotionCodeId): int
        {
            return 7;
        }

        /**
         * @return list<array<string, mixed>>
         */
        public function prices(): array
        {
            return array_values(array_filter($this->calls, fn (array $call): bool => $call[0] === 'createPrice'));
        }

        /**
         * @return list<array<string, mixed>>
         */
        public function callsTo(string $method): array
        {
            return array_values(array_filter($this->calls, fn (array $call): bool => $call[0] === $method));
        }
    };

    app()->instance(PricingStripe::class, $fake);

    return $fake;
}

/**
 * A fresh workspace with a live subscription on `$plan`, optionally at a given Stripe price.
 */
function subscribeWorkspaceTo(Plan $plan, string $status = 'active', ?string $price = null): Workspace
{
    $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme '.fake()->unique()->word()]);

    Subscription::query()->create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'type' => 'default',
        'stripe_id' => 'sub_'.fake()->unique()->uuid(),
        'stripe_status' => $status,
        'stripe_price' => $price ?? $plan->stripe_price_id_monthly,
    ]);

    return $workspace;
}

/**
 * A fresh owner and their workspace, for connector tests.
 *
 * @return array{0: Workspace, 1: User}
 */
function ownerWorkspaceForConnectors(): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    return [$workspace, $owner];
}
