<?php

use App\Actions\Billing\ActivateCreditPackAction;
use App\Actions\Billing\DeductCreditsAction;
use App\Enums\Billing\CreditTransactionType;
use App\Models\Billing\CreditPack;
use App\Models\Billing\Plan;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;

it('takes back unspent pack credits and records the spent part as debt when the payment is disputed', function (string $type) {
    Plan::factory()->create(['slug' => 'free', 'credits_monthly' => 0]);
    config(['billing.default_plan' => 'free']);
    $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
    $pack = CreditPack::factory()->forWorkspace($workspace)->create(['credits_amount' => 500, 'stripe_payment_intent_id' => 'pi_refund_1']);
    app(ActivateCreditPackAction::class)->execute($pack);
    app(DeductCreditsAction::class)->execute($workspace, CreditTransactionType::NodeRun, 'run-1', 120);

    $this->postJson('/api/stripe/webhook', [
        'id' => "evt_{$type}",
        'type' => $type,
        'data' => ['object' => ['id' => 'ch_1', 'payment_intent' => 'pi_refund_1', 'amount' => 900, 'amount_refunded' => 900, 'refunded' => true]],
    ])->assertOk();

    expect($workspace->fresh()->topup_credits)->toBe(0)
        ->and($workspace->fresh()->credit_debt)->toBe(120)
        ->and($workspace->fresh()->availableCredits())->toBe(0);
})->with(['charge.refunded', 'charge.dispute.created']);
