<?php

namespace App\Http\Resources\Api\Internal\V1\Billing;

use App\Models\Billing\CreditGrant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One block of credits in the workspace's wallet, for the billing page's
 * breakdown ("80 free, expires Nov 30 · 500 top-up, never expires"). Listed
 * in the order the credits will be spent.
 *
 * @mixin CreditGrant
 */
class CreditGrantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'source' => $this->source,
            'amount' => $this->amount,
            'remaining' => $this->remaining,
            'expires_at' => $this->expires_at,
            'reason' => $this->reason,
            'created_at' => $this->created_at,
        ];
    }
}
