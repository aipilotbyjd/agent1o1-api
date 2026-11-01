<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Models;

/**
 * Edits what the picker shows about a model, and switches it on or off. The
 * slug never changes: it is what agents, workflows and settings refer to.
 */
class UpdateModelRequest extends ModelChangeRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            ...ModelFields::rules(required: false),
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
