<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Models;

/**
 * Re-orders, switches on or off, or tunes a route. Its provider and model id
 * never change: that would be a different route, so add one instead.
 */
class UpdateRouteRequest extends ModelChangeRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'priority' => ['sometimes', 'integer', 'between:0,1000'],
            'is_enabled' => ['sometimes', 'boolean'],
            'options' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
