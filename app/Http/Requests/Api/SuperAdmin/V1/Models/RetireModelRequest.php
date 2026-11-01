<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Models;

class RetireModelRequest extends ModelChangeRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'replacement_slug' => ['nullable', 'string', 'max:80'],
        ];
    }
}
