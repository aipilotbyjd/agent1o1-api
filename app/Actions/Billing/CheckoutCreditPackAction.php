<?php

namespace App\Actions\Billing;

use App\Enums\Billing\Feature;
use App\Exceptions\FeatureNotAvailableException;
use App\Models\Billing\CreditPack;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Billing\PackCatalog;
use InvalidArgumentException;
use Laravel\Cashier\Checkout;

/**
 * Builds a Stripe Checkout Session (mode=payment) for a one-time credit
 * top-up, priced straight from `PackCatalog` (admin-defined packs, else `config('billing.packs')`) via Cashier's
 * `checkoutCharge()`: no pre-created Stripe Price object needed per pack.
 * The pack itself is recorded `Pending` and only flips to `Active` (crediting
 * the workspace's non-expiring `topup_credits` pool) once
 * `checkout.session.completed` arrives: see `ActivateCreditPackAction`.
 *
 * Gated on `Workspace::currentPlan()` rather than the raw subscription, so a
 * lapsed (canceled/`past_due`) subscription no longer keeps the feature
 * unlocked.
 */
class CheckoutCreditPackAction
{
    /**
     * @return array{pack: CreditPack, checkout_url: string}
     */
    public function execute(Workspace $workspace, string $packKey, User $purchaser): array
    {
        $plan = $workspace->currentPlan();

        if (! $plan?->hasFeature(Feature::CreditPacks)) {
            throw new FeatureNotAvailableException(Feature::CreditPacks);
        }

        $packConfig = app(PackCatalog::class)->find($packKey);

        if ($packConfig === null) {
            throw new InvalidArgumentException("Unknown credit pack key: {$packKey}");
        }

        $creditPack = $workspace->creditPacks()->create([
            'purchased_by' => $purchaser->id,
            'pack_key' => $packKey,
            'credits_amount' => $packConfig['credits'],
            'price_cents' => $packConfig['price_cents'],
        ]);

        /** @var Checkout $checkout */
        $checkout = $workspace->checkoutCharge($packConfig['price_cents'], $packConfig['label'], 1, [
            'success_url' => $this->successUrl($workspace),
            'cancel_url' => $this->cancelUrl($workspace),
            'metadata' => [
                'type' => 'credit_pack',
                'credit_pack_id' => $creditPack->id,
            ],
        ]);

        $stripeSession = $checkout->asStripeCheckoutSession();

        $creditPack->update(['stripe_checkout_session_id' => $stripeSession->id]);

        return ['pack' => $creditPack, 'checkout_url' => $stripeSession->url];
    }

    private function successUrl(Workspace $workspace): string
    {
        return rtrim((string) config('app.frontend_url'), '/')."/workspaces/{$workspace->slug}/billing?pack_checkout=success";
    }

    private function cancelUrl(Workspace $workspace): string
    {
        return rtrim((string) config('app.frontend_url'), '/')."/workspaces/{$workspace->slug}/billing?pack_checkout=cancel";
    }
}
