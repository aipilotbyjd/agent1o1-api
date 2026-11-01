<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Pricing;

/**
 * Edits that don't change what anyone is charged. The slug and the amounts
 * are deliberately not accepted here: the slug never changes, and amounts go
 * through the price endpoint.
 */
class UpdatePlanRequest extends PlanPayloadRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->sharedRules('sometimes'),
            'reason' => ['required', 'string', 'min:'.config('super_admin_security.step_up.reason_min_length'), 'max:500'],
        ];
    }
}
