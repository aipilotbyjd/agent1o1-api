<?php

namespace App\Http\Resources\Api\SuperAdmin\V1\Referrals;

use App\Models\Referrals\ReferralBlockedDomain;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ReferralBlockedDomain
 */
class ReferralBlockedDomainResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'domain' => $this->domain,
            'reason' => $this->reason,
            'created_at' => $this->created_at,
        ];
    }
}
