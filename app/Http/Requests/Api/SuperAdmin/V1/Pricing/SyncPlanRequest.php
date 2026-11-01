<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Pricing;

use App\Http\Requests\Api\SuperAdmin\V1\StepUpRequest;

/**
 * A dry run only reads from Stripe, so it needs nothing extra; a real sync
 * creates Stripe objects and so asks for the usual reason and fresh code.
 */
class SyncPlanRequest extends StepUpRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        if ($this->boolean('dry_run')) {
            return ['dry_run' => ['boolean']];
        }

        return [...parent::rules(), 'dry_run' => ['boolean']];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return $this->boolean('dry_run') ? [] : parent::after();
    }
}
