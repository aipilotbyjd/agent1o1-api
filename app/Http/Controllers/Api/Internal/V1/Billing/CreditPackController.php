<?php

namespace App\Http\Controllers\Api\Internal\V1\Billing;

use App\Actions\Billing\CheckoutCreditPackAction;
use App\Enums\Billing\Feature;
use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Billing\CheckoutCreditPackRequest;
use App\Http\Resources\Api\Internal\V1\Billing\CreditPackResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workspaces\Workspace;
use App\Services\Billing\PackCatalog;

class CreditPackController extends Controller
{
    /**
     * Lists the purchasable packs from `PackCatalog`, each
     * flagged `available` based on whether the workspace's current plan has
     * the `credit_packs` feature.
     */
    public function index(Workspace $workspace, PackCatalog $catalog)
    {
        $this->requirePermission(Permission::BillingView);

        $plan = $workspace->currentPlan();
        $available = $plan?->hasFeature(Feature::CreditPacks) ?? false;

        $packs = collect($catalog->sellable())
            ->map(fn (array $pack, string $key): array => [
                'key' => $key,
                'label' => $pack['label'],
                'credits' => $pack['credits'],
                'price_cents' => $pack['price_cents'],
                'badge' => $pack['badge'],
                'available' => $available,
            ])
            ->values();

        return ApiResponse::success(['packs' => $packs]);
    }

    /**
     * Lists the workspace's purchase history (pending, active, and refunded
     * packs), newest first.
     */
    public function purchased(Workspace $workspace)
    {
        $this->requirePermission(Permission::BillingView);

        $packs = $workspace->creditPacks()->latest()->get();

        return ApiResponse::success(['credit_packs' => CreditPackResource::collection($packs)]);
    }

    public function checkout(CheckoutCreditPackRequest $request, Workspace $workspace, CheckoutCreditPackAction $checkout)
    {
        $this->requirePermission(Permission::BillingManage);

        $result = $checkout->execute($workspace, $request->validated('pack_key'), $request->user());

        return ApiResponse::created([
            'credit_pack' => CreditPackResource::make($result['pack']),
            'checkout_url' => $result['checkout_url'],
        ], 'Checkout session created.');
    }
}
