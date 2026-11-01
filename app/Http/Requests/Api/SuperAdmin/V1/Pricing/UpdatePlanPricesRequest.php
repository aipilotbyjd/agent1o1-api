<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Pricing;

use App\Enums\Billing\BillingInterval;
use App\Http\Requests\Api\SuperAdmin\V1\StepUpRequest;

class UpdatePlanPricesRequest extends StepUpRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $intervals = implode(',', array_map(fn (BillingInterval $interval): string => $interval->value, BillingInterval::cases()));

        return [
            ...parent::rules(),
            'prices' => ['required', 'array:'.$intervals, 'min:1'],
            'prices.*' => ['required', 'integer', 'min:0', 'max:100000000'],
        ];
    }
}
