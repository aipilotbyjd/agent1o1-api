<?php

namespace App\Http\Resources\V1;

use App\Models\CreditPack;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CreditPack
 */
class CreditPackResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pack_key' => $this->pack_key,
            'credits_amount' => $this->credits_amount,
            // Pack credits are pooled into the workspace's usage-period balance on
            // deposit rather than tracked per pack, so per-pack depletion isn't
            // recorded anywhere — a pack is only ever fully available or refunded.
            'credits_remaining' => $this->status->value === 'refunded' ? 0 : $this->credits_amount,
            'price_cents' => $this->price_cents,
            'currency' => $this->currency,
            'status' => $this->status,
            'purchased_at' => $this->purchased_at,
            // Credit packs never expire in the current billing model.
            'expires_at' => null,
        ];
    }
}
