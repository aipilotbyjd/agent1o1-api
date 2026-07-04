<?php

namespace App\Services\Billing;

use App\Enums\CreditPackStatus;
use App\Enums\Feature;
use App\Exceptions\Billing\FeatureNotAvailableException;
use App\Models\CreditPack;
use App\Models\User;
use App\Models\Workspace;

class PackService
{
    public function __construct(
        private CreditService $creditService,
        private StripeService $stripeService,
    ) {}

    public function catalog(Workspace $workspace): array
    {
        $plan = $workspace->currentPlan();
        $available = $plan?->hasFeature(Feature::CreditPacks) ?? false;

        return collect(config('billing.packs', []))
            ->map(fn ($pack, $key) => [
                'key' => $key,
                'label' => $pack['label'],
                'credits' => $pack['credits'],
                'price_cents' => $pack['price_cents'],
                'available' => $available,
            ])
            ->values()
            ->all();
    }

    public function checkout(Workspace $workspace, string $packKey, User $purchaser): array
    {
        $plan = $workspace->currentPlan();

        if (! $plan?->hasFeature(Feature::CreditPacks)) {
            throw new FeatureNotAvailableException(Feature::CreditPacks);
        }

        $packs = config('billing.packs', []);

        if (! isset($packs[$packKey])) {
            throw new \InvalidArgumentException("Unknown pack key: {$packKey}");
        }

        $packConfig = $packs[$packKey];

        $creditPack = CreditPack::create([
            'workspace_id' => $workspace->id,
            'purchased_by' => $purchaser->id,
            'pack_key' => $packKey,
            'credits_amount' => $packConfig['credits'],
            'price_cents' => $packConfig['price_cents'],
            'currency' => 'usd',
            'status' => CreditPackStatus::Pending,
        ]);

        $url = $this->buildCheckoutUrl($workspace, $creditPack, $packConfig);

        return [
            'url' => $url,
            'credit_pack_id' => $creditPack->id,
        ];
    }

    public function activate(CreditPack $pack): void
    {
        if ($pack->status === CreditPackStatus::Active) {
            return;
        }

        $pack->update([
            'status' => CreditPackStatus::Active,
            'purchased_at' => now(),
        ]);

        $this->creditService->depositPack($pack);
    }

    private function buildCheckoutUrl(Workspace $workspace, CreditPack $pack, array $packConfig): string
    {
        $params = [
            'mode' => 'payment',
            'line_items' => [['price' => $packConfig['stripe_price_id'], 'quantity' => 1]],
            'metadata' => [
                'type' => 'credit_pack',
                'credit_pack_id' => $pack->id,
            ],
            'success_url' => config('app.frontend_url').'/billing?pack_checkout=success',
            'cancel_url' => config('app.frontend_url').'/billing',
        ];

        if ($workspace->stripe_id) {
            $params['customer'] = $workspace->stripe_id;
        } else {
            $params['customer_email'] = $workspace->owner->email;
        }

        $session = $this->stripeService->createPackCheckoutSession($params);

        $pack->update(['stripe_checkout_session_id' => $session->id]);

        return $session->url;
    }
}
