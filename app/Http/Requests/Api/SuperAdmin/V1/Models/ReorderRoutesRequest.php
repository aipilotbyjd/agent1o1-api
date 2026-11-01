<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Models;

class ReorderRoutesRequest extends ModelChangeRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'route_ids' => ['required', 'array', 'min:1'],
            'route_ids.*' => ['uuid', 'distinct'],
        ];
    }
}
