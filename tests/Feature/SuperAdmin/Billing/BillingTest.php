<?php

use App\Enums\Billing\CreditGrantSource;
use App\Enums\Billing\PlanGrantSource;
use App\Enums\Billing\PlanGrantStatus;
use App\Enums\SuperAdmin\SuperAdminRole;
use App\Models\Billing\CreditGrant;
use App\Models\Billing\CreditTransaction;
use App\Models\Billing\Plan;
use App\Models\Billing\PlanGrant;
use App\Models\SuperAdmin\SuperAdminAuditLog;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\SuperAdmin\Billing\SuperAdminRefunds;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->finance = superAdmin(SuperAdminRole::Finance);
    $this->headers = superAdminHeaders($this->finance);
    $this->workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
});

it('shows a workspace\'s billing state without writing anything', function () {
    $this->workspace->forceFill(['topup_credits' => 250])->save();

    $this->withHeaders($this->headers)->getJson(saUrl("billing/workspaces/{$this->workspace->id}"))
        ->assertOk()
        ->assertJsonPath('data.topup_credits', 250)
        ->assertJsonPath('data.stripe_customer', false)
        ->assertJsonPath('data.usage_period', null);

    // Looking must not create the usage period `currentUsagePeriod()` would.
    expect($this->workspace->usagePeriods()->count())->toBe(0);
});

it('lists plans, plan grants and the credit ledger', function () {
    $plan = Plan::factory()->create(['name' => 'Pro', 'slug' => 'pro']);
    PlanGrant::factory()->forWorkspace($this->workspace)->forPlan($plan)->active()->create();
    CreditTransaction::query()->create([
        'workspace_id' => $this->workspace->id,
        'source_type' => 'node_run',
        'source_id' => (string) Str::uuid(),
        'credits' => 7,
    ]);

    $this->withHeaders($this->headers)->getJson(saUrl('billing/plans'))->assertOk()->assertJsonFragment(['slug' => 'pro']);
    $this->withHeaders($this->headers)->getJson(saUrl("billing/workspaces/{$this->workspace->id}/plan-grants"))
        ->assertOk()->assertJsonCount(1, 'data.grants')->assertJsonPath('data.grants.0.plan.slug', 'pro');
    $this->withHeaders($this->headers)->getJson(saUrl("billing/workspaces/{$this->workspace->id}/credit-transactions"))
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.credits', 7);
});

it('grants top-up credits with a reason and a fresh code, recording before and after', function () {
    $this->withHeaders($this->headers)
        ->postJson(saUrl("billing/workspaces/{$this->workspace->id}/credits/grant"), stepUp($this->finance, ['credits' => 500]))
        ->assertOk()
        ->assertJsonPath('data.topup_credits', 500);

    $log = SuperAdminAuditLog::query()->where('action', 'billing.credits.granted')->firstOrFail();

    $grant = CreditGrant::query()->where('workspace_id', $this->workspace->id)->sole();
    expect($this->workspace->fresh()->topup_credits)->toBe(500)
        ->and($grant->source)->toBe(CreditGrantSource::Admin)
        ->and($log->before)->toBe(['topup_credits' => 0])
        ->and($log->after)->toBe(['topup_credits' => 500, 'credit_grant_id' => $grant->id, 'expires_at' => null])
        ->and($log->reason)->toBe('Verified request from the account owner');
});

it('rejects a credit grant without step-up, over the cap, or from the wrong role', function () {
    $url = saUrl("billing/workspaces/{$this->workspace->id}/credits/grant");

    $this->withHeaders($this->headers)->postJson($url, ['credits' => 10])->assertUnprocessable()->assertJsonValidationErrors(['reason', 'totp_code']);
    $this->withHeaders($this->headers)->postJson($url, stepUp($this->finance, ['credits' => 0]))->assertUnprocessable()->assertJsonValidationErrors('credits');
    $this->withHeaders($this->headers)->postJson($url, stepUp($this->finance, ['credits' => 100001]))->assertUnprocessable()->assertJsonValidationErrors('credits');

    foreach ([SuperAdminRole::Support, SuperAdminRole::Operator, SuperAdminRole::Viewer] as $role) {
        $admin = superAdmin($role);

        $this->withHeaders(superAdminHeaders($admin))->postJson($url, stepUp($admin, ['credits' => 10]))->assertForbidden();
    }

    expect($this->workspace->fresh()->topup_credits)->toBe(0);
});

it('applies a credit grant once however often the same Idempotency-Key is replayed', function () {
    $url = saUrl("billing/workspaces/{$this->workspace->id}/credits/grant");

    $first = $this->withHeaders([...$this->headers, 'Idempotency-Key' => 'grant-1'])
        ->postJson($url, stepUp($this->finance, ['credits' => 100]))
        ->assertOk();

    // A retry carries a new authenticator code, which must not matter.
    $replay = $this->withHeaders([...$this->headers, 'Idempotency-Key' => 'grant-1'])
        ->postJson($url, ['credits' => 100, 'reason' => 'Verified request from the account owner', 'totp_code' => 'whatever'])
        ->assertOk()
        ->assertHeader('Idempotent-Replayed', 'true');

    expect($replay->json())->toBe($first->json())
        ->and($this->workspace->fresh()->topup_credits)->toBe(100)
        ->and(SuperAdminAuditLog::query()->where('action', 'billing.credits.granted')->count())->toBe(1);

    // The same request with its fields in another order is still the same request.
    $this->withHeaders([...$this->headers, 'Idempotency-Key' => 'grant-1'])
        ->postJson($url, ['totp_code' => 'x', 'reason' => 'Verified request from the account owner', 'credits' => 100])
        ->assertOk()
        ->assertHeader('Idempotent-Replayed', 'true');

    $this->withHeaders([...$this->headers, 'Idempotency-Key' => 'grant-1'])
        ->postJson($url, stepUp($this->finance, ['credits' => 999]))
        ->assertUnprocessable();
});

it('takes back top-up credits, stopping at what is left', function () {
    $this->workspace->forceFill(['topup_credits' => 40])->save();
    $url = saUrl("billing/workspaces/{$this->workspace->id}/credits/revoke");

    $this->withHeaders($this->headers)->postJson($url, stepUp($this->finance, ['credits' => 100]))
        ->assertOk()
        ->assertJsonPath('data.recovered', 40)
        ->assertJsonPath('data.topup_credits', 0);

    $this->withHeaders($this->headers)->postJson($url, stepUp($this->finance, ['credits' => 5]))->assertStatus(409);
});

it('comps plan time and withdraws it again', function () {
    $plan = Plan::factory()->create(['slug' => 'pro']);

    $created = $this->withHeaders($this->headers)
        ->postJson(saUrl("billing/workspaces/{$this->workspace->id}/plan-grants"), stepUp($this->finance, [
            'plan_id' => $plan->id,
            'expires_at' => now()->addDays(30)->toIso8601String(),
        ]))
        ->assertCreated()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.source', 'manual')
        ->assertJsonPath('data.plan.slug', 'pro');

    expect($this->workspace->fresh()->currentPlan()->id)->toBe($plan->id);

    $this->withHeaders($this->headers)
        ->deleteJson(saUrl('billing/plan-grants/'.$created->json('data.id')), stepUp($this->finance))
        ->assertOk()
        ->assertJsonPath('data.status', 'revoked');

    expect(PlanGrant::query()->sole()->status)->toBe(PlanGrantStatus::Revoked)
        ->and(SuperAdminAuditLog::query()->whereIn('action', ['billing.plan_grant.created', 'billing.plan_grant.revoked'])->count())->toBe(2);
});

it('validates a plan grant', function (array $input, string $error) {
    $plan = Plan::factory()->create();
    $base = ['plan_id' => $plan->id, 'expires_at' => now()->addDays(30)->toIso8601String()];

    $this->withHeaders($this->headers)
        ->postJson(saUrl("billing/workspaces/{$this->workspace->id}/plan-grants"), stepUp($this->finance, [...$base, ...$input]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($error);
})->with([
    'unknown plan' => [['plan_id' => '01a169d4-4d83-7380-9987-4bd93a3f1481'], 'plan_id'],
    'already over' => [['expires_at' => '2020-01-01'], 'expires_at'],
    'too far away' => [['expires_at' => '2099-01-01'], 'expires_at'],
    'a source that is not allowed' => [['source' => 'lifetime_purchase'], 'source'],
]);

it('promo is an allowed source', function () {
    $plan = Plan::factory()->create();

    $this->withHeaders($this->headers)
        ->postJson(saUrl("billing/workspaces/{$this->workspace->id}/plan-grants"), stepUp($this->finance, [
            'plan_id' => $plan->id,
            'expires_at' => now()->addDays(10)->toIso8601String(),
            'source' => PlanGrantSource::Promo->value,
        ]))
        ->assertCreated()
        ->assertJsonPath('data.source', 'promo');
});

it('refuses a refund for a workspace with no Stripe customer', function () {
    $this->withHeaders($this->headers)
        ->postJson(saUrl("billing/workspaces/{$this->workspace->id}/refunds"), stepUp($this->finance, ['payment_intent' => 'pi_123']))
        ->assertStatus(409);
});

it('refunds through Stripe, and withdraws the lifetime grant a full refund pays back', function () {
    $fake = new class extends SuperAdminRefunds
    {
        /** @var list<array{pi: string, amount: ?int}> */
        public array $calls = [];

        public function refund(Workspace $workspace, string $paymentIntentId, ?int $amountCents): string
        {
            $this->calls[] = ['pi' => $paymentIntentId, 'amount' => $amountCents];

            return 're_test_'.count($this->calls);
        }
    };
    app()->instance(SuperAdminRefunds::class, $fake);

    $plan = Plan::factory()->create();
    $grant = PlanGrant::factory()->forWorkspace($this->workspace)->forPlan($plan)->active()->create(['stripe_payment_intent_id' => 'pi_full']);
    $url = saUrl("billing/workspaces/{$this->workspace->id}/refunds");

    // A partial refund leaves the grant alone.
    $this->withHeaders($this->headers)->postJson($url, stepUp($this->finance, ['payment_intent' => 'pi_full', 'amount_cents' => 500]))
        ->assertOk()->assertJsonPath('data.plan_grant_revoked', false);
    expect($grant->fresh()->status)->toBe(PlanGrantStatus::Active);

    $this->withHeaders($this->headers)->postJson($url, stepUp($this->finance, ['payment_intent' => 'pi_full']))
        ->assertOk()->assertJsonPath('data.refund_id', 're_test_2')->assertJsonPath('data.plan_grant_revoked', true);

    expect($grant->fresh()->status)->toBe(PlanGrantStatus::Revoked)
        ->and($fake->calls)->toBe([['pi' => 'pi_full', 'amount' => 500], ['pi' => 'pi_full', 'amount' => null]])
        ->and(SuperAdminAuditLog::query()->where('action', 'billing.refunded')->count())->toBe(2);
});

it('validates a refund and keeps it to finance and owners', function () {
    $url = saUrl("billing/workspaces/{$this->workspace->id}/refunds");

    $this->withHeaders($this->headers)->postJson($url, stepUp($this->finance, ['payment_intent' => 'not-a-payment']))
        ->assertUnprocessable()->assertJsonValidationErrors('payment_intent');
    $this->withHeaders($this->headers)->postJson($url, stepUp($this->finance, ['payment_intent' => 'pi_1', 'amount_cents' => 0]))
        ->assertUnprocessable()->assertJsonValidationErrors('amount_cents');

    $operator = superAdmin(SuperAdminRole::Operator);
    $this->withHeaders(superAdminHeaders($operator))->postJson($url, stepUp($operator, ['payment_intent' => 'pi_1']))->assertForbidden();
});

it('grants admin credits that expire on the given date', function () {
    $expiresAt = now()->addMonth()->startOfSecond();

    $this->withHeaders($this->headers)
        ->postJson(saUrl("billing/workspaces/{$this->workspace->id}/credits/grant"), stepUp($this->finance, ['credits' => 300, 'expires_at' => $expiresAt->toIso8601String()]))
        ->assertOk();

    expect(CreditGrant::query()->where('workspace_id', $this->workspace->id)->sole()->expires_at->equalTo($expiresAt))->toBeTrue();
});
