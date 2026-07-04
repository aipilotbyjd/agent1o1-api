<?php

namespace App\Http\Resources\V1;

use App\Models\UsagePeriod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UsagePeriod
 */
class CreditBalanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $unlimited = $this->isUnlimited();

        return [
            'available' => $unlimited ? null : $this->creditsRemaining(),
            'limit' => $unlimited ? null : $this->credits_limit,
            'used' => $this->credits_used,
            'from_packs' => $this->credits_from_packs,
            'rolled_over' => $this->credits_rolled_over,
            'period_start' => $this->period_start,
            'period_end' => $this->period_end,
            'unlimited' => $unlimited,
        ];
    }
}
