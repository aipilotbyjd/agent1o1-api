<?php

namespace App\Services\SuperAdmin\Billing;

use App\Models\Workspaces\Workspace;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The one place the Super Admin API talks to Stripe to give money back, kept
 * apart from `SuperAdminBillingService` so tests can swap it for a fake.
 */
class SuperAdminRefunds
{
    /**
     * Refunds a payment of the workspace's own Stripe customer, in full or
     * for `$amountCents`, and returns the Stripe refund id. A payment that
     * belongs to a different customer is refused.
     */
    public function refund(Workspace $workspace, string $paymentIntentId, ?int $amountCents): string
    {
        if (! $workspace->hasStripeId()) {
            throw new HttpException(409, 'This workspace has no Stripe customer, so there is nothing to refund.');
        }

        $intent = $workspace->stripe()->paymentIntents->retrieve($paymentIntentId);

        if ((string) $intent->customer !== $workspace->stripe_id) {
            throw new HttpException(422, 'That payment does not belong to this workspace.');
        }

        $refund = $workspace->refund($paymentIntentId, $amountCents === null ? [] : ['amount' => $amountCents]);

        return $refund->id;
    }
}
