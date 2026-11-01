<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Pricing;

use App\Enums\Billing\BillingInterval;
use App\Http\Requests\Api\SuperAdmin\V1\StepUpRequest;

class UpdateIntervalsRequest extends StepUpRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'intervals' => ['required', 'array:'.implode(',', array_map(fn (BillingInterval $interval): string => $interval->value, BillingInterval::cases())), 'min:1'],
            'intervals.*' => ['required', 'boolean'],
        ];
    }
}
