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
            'price_cents' => $this->price_cents,
            'currency' => $this->currency,
            'status' => $this->status,
            'purchased_at' => $this->purchased_at,
        ];
    }
}
