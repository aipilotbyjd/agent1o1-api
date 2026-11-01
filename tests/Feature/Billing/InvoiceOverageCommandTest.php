<?php

use App\Actions\Billing\BillOverageCreditsAction;
use App\Enums\Billing\OverageInvoiceAttemptStatus;
use App\Exceptions\BillingAccountNotFoundException;
use App\Models\Billing\OverageInvoiceAttempt;
use App\Models\Billing\UsagePeriod;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Billing\CreditOverage;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Log;

/**
 * Overage is metered as it is spent and invoiced afterwards, so what matters
 * here is that every accrued credit is billed exactly once, never twice on
 * a re-run, and never written off when Stripe refuses the charge.
 *
 * The action's single Stripe call is replaced with a recorder, so these
 * exercise the reserve/release bookkeeping around it rather than Cashier.
 */
class RecordingOverageBiller extends BillOverageCreditsAction
{
    /** @var list<array{description: string, amount_cents: int, metadata: array<string, string>}> */
    public array $invoiced = [];

    public ?Throwable $failWith = null;

    /**
     * @param  array<string, string>  $metadata
     */
    protected function invoice(Workspace $workspace, string $description, int $amountCents, array $metadata): ?string
    {
        $this->invoiced[] = [
            'description' => $description,
            'amount_cents' => $amountCents,
            'metadata' => $metadata,
        ];

        if ($this->failWith !== null) {
            throw $this->failWith;
        }

        return 'in_test_'.count($this->invoiced);
    }

    /** What Stripe reports for an interrupted attempt: an invoice id, or null for none. */
    public ?string $stripeInvoiceForAttempt = null;

    protected function findInvoiceForAttempt(Workspace $workspace, string $attemptId): ?string
    {
        return $this->stripeInvoiceForAttempt;
    }

    protected function discardOrphanedInvoiceItems(Workspace $workspace, string $attemptId): void {}

    protected function releaseUnpaidInvoices(Workspace $workspace, string $attemptId): void {}
}

/**
 * Keeps the real `invoice()` settlement logic and fakes only the Stripe calls
 * beneath it.
 */
class StripeFakedOverageBiller extends BillOverageCreditsAction
{
    /** @var array{id: string|null, status: string|null}|null */
    public ?array $created = ['id' => 'in_paid', 'status' => 'paid'];

    public ?Throwable $failWith = null;

    public ?string $paidInvoiceOnStripe = null;

    public int $releases = 0;

    protected function createAndPayInvoice(Workspace $workspace, string $description, int $amountCents, array $metadata): ?array
    {
        if ($this->failWith !== null) {
            throw $this->failWith;
        }

        return $this->created;
    }

    protected function findInvoiceForAttempt(Workspace $workspace, string $attemptId): ?string
    {
        return $this->paidInvoiceOnStripe;
    }

    protected function releaseUnpaidInvoices(Workspace $workspace, string $attemptId): void
    {
        $this->releases++;
    }
}

function stripeFakedBiller(): StripeFakedOverageBiller
{
    return new StripeFakedOverageBiller(app(CreditOverage::class));
}

function recordingBiller(?Throwable $failWith = null): RecordingOverageBiller
{
    $biller = new RecordingOverageBiller(app(CreditOverage::class));
    $biller->failWith = $failWith;

    app()->instance(BillOverageCreditsAction::class, $biller);

    return $biller;
}

function workspaceWithUnbilledOverage(int $credits, bool $withStripeCustomer = true): Workspace
{
    $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);

    $workspace->currentUsagePeriod()->forceFill([
        'ends_at' => now()->subDay(),
        'overage_credits_used' => $credits,
    ])->save();

    if ($withStripeCustomer) {
        $workspace->forceFill(['stripe_id' => 'cus_test_overage'])->save();
    }

    return $workspace->fresh();
}

function currentOveragePeriod(): UsagePeriod
{
    return UsagePeriod::query()->firstOrFail();
}

beforeEach(function () {
    config()->set('billing.credit_value_usd', 0.005);
    config()->set('billing.overage.minimum_invoice_cents', 50);
});

it('invoices a closed period for the overage it accrued', function () {
    $workspace = workspaceWithUnbilledOverage(1_000);
    $biller = recordingBiller();

    $result = $biller->execute($workspace, currentOveragePeriod());

    // 1,000 credits at $0.005 each is $5.00.
    expect($result['credits'])->toBe(1_000)
        ->and($result['amount_cents'])->toBe(500)
        ->and($result['invoice_id'])->toBe('in_test_1')
        ->and($biller->invoiced)->toHaveCount(1)
        ->and($biller->invoiced[0]['amount_cents'])->toBe(500)
        ->and($biller->invoiced[0]['metadata']['type'])->toBe('credit_overage');
});

it('marks the credits billed so a second run charges nothing', function () {
    $workspace = workspaceWithUnbilledOverage(1_000);
    $biller = recordingBiller();

    $biller->execute($workspace, currentOveragePeriod());
    $second = $biller->execute($workspace, currentOveragePeriod());

    expect($second)->toBeNull()
        ->and($biller->invoiced)->toHaveCount(1)
        ->and(currentOveragePeriod()->overage_credits_billed)->toBe(1_000);
});

it('bills only what accrued since the last invoice', function () {
    $workspace = workspaceWithUnbilledOverage(1_000);
    $biller = recordingBiller();

    $biller->execute($workspace, currentOveragePeriod());
    currentOveragePeriod()->increment('overage_credits_used', 400);

    $second = $biller->execute($workspace, currentOveragePeriod());

    expect($second['credits'])->toBe(400)
        ->and($second['amount_cents'])->toBe(200)
        ->and(currentOveragePeriod()->overage_credits_billed)->toBe(1_400);
});

it('leaves the credits unbilled when Stripe refuses the charge', function () {
    $workspace = workspaceWithUnbilledOverage(1_000);
    $biller = recordingBiller(new RuntimeException('card_declined'));

    expect(fn () => $biller->execute($workspace, currentOveragePeriod()))
        ->toThrow(RuntimeException::class);

    expect(currentOveragePeriod()->overage_credits_billed)->toBe(0);
});

it('does not open an invoice for less than the minimum worth billing', function () {
    $workspace = workspaceWithUnbilledOverage(20);
    $biller = recordingBiller();

    // 20 credits is $0.10: held back until it is worth an invoice, not
    // written off, so the credits stay unbilled.
    expect($biller->execute($workspace, currentOveragePeriod()))->toBeNull()
        ->and($biller->invoiced)->toBeEmpty()
        ->and(currentOveragePeriod()->overage_credits_billed)->toBe(0);
});

it('refuses to bill a workspace that never became a Stripe customer', function () {
    $workspace = workspaceWithUnbilledOverage(1_000, withStripeCustomer: false);
    $biller = recordingBiller();

    expect(fn () => $biller->execute($workspace, currentOveragePeriod()))
        ->toThrow(BillingAccountNotFoundException::class);

    expect(currentOveragePeriod()->overage_credits_billed)->toBe(0);
});

it('invoices every closed period the command finds', function () {
    workspaceWithUnbilledOverage(1_000);
    $biller = recordingBiller();

    $this->artisan('billing:invoice-overage')
        ->expectsOutputToContain('Invoiced overage for 1 billing period(s).')
        ->assertSuccessful();

    expect($biller->invoiced)->toHaveCount(1)
        ->and(currentOveragePeriod()->overage_credits_billed)->toBe(1_000);
});

it('skips periods that are still accruing', function () {
    workspaceWithUnbilledOverage(1_000);
    currentOveragePeriod()->forceFill(['ends_at' => now()->addDay()])->save();
    $biller = recordingBiller();

    $this->artisan('billing:invoice-overage')
        ->expectsOutputToContain('Invoiced overage for 0 billing period(s).')
        ->assertSuccessful();

    expect($biller->invoiced)->toBeEmpty()
        ->and(currentOveragePeriod()->overage_credits_billed)->toBe(0);
});

it('logs and carries on when one workspace cannot be invoiced', function () {
    workspaceWithUnbilledOverage(1_000, withStripeCustomer: false);
    recordingBiller();

    Log::spy();

    $this->artisan('billing:invoice-overage')->assertSuccessful();

    Log::shouldHaveReceived('error')->once()->withArgs(
        fn (string $message): bool => $message === 'Failed to invoice credit overage.',
    );
});

it('carries a remainder under the minimum into the next closed period instead of stranding it', function () {
    $workspace = workspaceWithUnbilledOverage(60);
    $biller = recordingBiller();

    // 60 credits is $0.30, under the $0.50 minimum on its own.
    expect($biller->execute($workspace, currentOveragePeriod()))->toBeNull();

    $earlier = currentOveragePeriod();
    $later = UsagePeriod::create([
        'workspace_id' => $workspace->id,
        'starts_at' => now()->subMonths(2)->startOfMonth(),
        'ends_at' => now()->subMonths(2)->endOfMonth()->addSecond(),
    ]);
    $later->forceFill(['overage_credits_used' => 60])->save();

    $result = $biller->executeForPeriods($workspace, collect([$earlier, $later]));

    expect($result['credits'])->toBe(120)
        ->and($result['periods'])->toBe(2)
        ->and($biller->invoiced)->toHaveCount(1)
        ->and(UsagePeriod::query()->sum('overage_credits_billed'))->toBe(120);
});

it('records a pending attempt before calling Stripe and settles it afterwards', function () {
    $workspace = workspaceWithUnbilledOverage(1_000);
    $biller = recordingBiller();

    $biller->execute($workspace, currentOveragePeriod());

    $attempt = OverageInvoiceAttempt::query()->sole();

    expect($attempt->status)->toBe(OverageInvoiceAttemptStatus::Succeeded)
        ->and($attempt->stripe_invoice_id)->toBe('in_test_1')
        ->and($biller->invoiced[0]['metadata']['overage_attempt_id'])->toBe($attempt->id);
});

it('keeps an interrupted attempt billed when Stripe did create its invoice', function () {
    $workspace = workspaceWithUnbilledOverage(1_000);
    $biller = recordingBiller();
    $biller->stripeInvoiceForAttempt = 'in_found';

    $period = currentOveragePeriod();
    $period->forceFill(['overage_credits_billed' => 1_000])->save();
    $attempt = OverageInvoiceAttempt::factory()->create([
        'workspace_id' => $workspace->id,
        'allocations' => [$period->id => 1_000],
        'created_at' => now()->subHour(),
    ]);

    $biller->execute($workspace, currentOveragePeriod());

    expect($attempt->fresh()->status)->toBe(OverageInvoiceAttemptStatus::Succeeded)
        ->and($attempt->fresh()->stripe_invoice_id)->toBe('in_found')
        ->and(currentOveragePeriod()->overage_credits_billed)->toBe(1_000)
        ->and($biller->invoiced)->toBeEmpty();
});

it('puts an interrupted attempt\'s credits back and re-bills them when Stripe never saw it', function () {
    $workspace = workspaceWithUnbilledOverage(1_000);
    $biller = recordingBiller();

    $period = currentOveragePeriod();
    $period->forceFill(['overage_credits_billed' => 1_000])->save();
    $attempt = OverageInvoiceAttempt::factory()->create([
        'workspace_id' => $workspace->id,
        'allocations' => [$period->id => 1_000],
        'created_at' => now()->subHour(),
    ]);

    $result = $biller->execute($workspace, currentOveragePeriod());

    expect($attempt->fresh()->status)->toBe(OverageInvoiceAttemptStatus::Failed)
        ->and($result['credits'])->toBe(1_000)
        ->and($biller->invoiced)->toHaveCount(1)
        ->and(currentOveragePeriod()->overage_credits_billed)->toBe(1_000);
});

it('records the paid invoice when Stripe collects the overage', function () {
    $workspace = workspaceWithUnbilledOverage(1_000);
    $biller = stripeFakedBiller();

    $result = $biller->execute($workspace, currentOveragePeriod());

    expect($result['invoice_id'])->toBe('in_paid')
        ->and($biller->releases)->toBe(0)
        ->and(currentOveragePeriod()->overage_credits_billed)->toBe(1_000);
});

it('voids the open invoice and returns the credits when the charge is declined', function () {
    $workspace = workspaceWithUnbilledOverage(1_000);
    $biller = stripeFakedBiller();
    $biller->failWith = new RuntimeException('card_declined');

    expect(fn () => $biller->execute($workspace, currentOveragePeriod()))
        ->toThrow(RuntimeException::class, 'card_declined');

    expect($biller->releases)->toBe(1)
        ->and(currentOveragePeriod()->overage_credits_billed)->toBe(0)
        ->and(OverageInvoiceAttempt::query()->sole()->status)->toBe(OverageInvoiceAttemptStatus::Failed);
});

it('keeps the credits billed when the call failed after Stripe collected the money', function () {
    $workspace = workspaceWithUnbilledOverage(1_000);
    $biller = stripeFakedBiller();
    $biller->failWith = new RuntimeException('connection reset');
    $biller->paidInvoiceOnStripe = 'in_paid_late';

    $result = $biller->execute($workspace, currentOveragePeriod());

    expect($result['invoice_id'])->toBe('in_paid_late')
        ->and($biller->releases)->toBe(0)
        ->and(currentOveragePeriod()->overage_credits_billed)->toBe(1_000);
});

it('treats a missing or unpaid invoice as not billed', function (?array $created) {
    $workspace = workspaceWithUnbilledOverage(1_000);
    $biller = stripeFakedBiller();
    $biller->created = $created;

    expect(fn () => $biller->execute($workspace, currentOveragePeriod()))
        ->toThrow(RuntimeException::class, 'The overage invoice was not paid.');

    expect($biller->releases)->toBe(1)
        ->and(currentOveragePeriod()->overage_credits_billed)->toBe(0);
})->with([
    'Cashier returned false' => [null],
    'invoice left open' => [['id' => 'in_open', 'status' => 'open']],
]);
