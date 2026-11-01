<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Billing;

use App\Enums\Billing\PlanGrantSource;
use App\Http\Requests\Api\SuperAdmin\V1\StepUpRequest;
use Illuminate\Validation\Rule;

class StorePlanGrantRequest extends StepUpRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'plan_id' => ['required', 'uuid', Rule::exists('plans', 'id')->where('is_active', true)],
            'expires_at' => ['required', 'date', 'after:now', 'before:'.now()->addYears(5)->toDateString()],
            'source' => ['nullable', Rule::in([PlanGrantSource::Manual->value, PlanGrantSource::Promo->value])],
        ];
    }
}
