<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Models;

class DeprecateModelRequest extends ModelChangeRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'replacement_slug' => ['required', 'string', 'max:80'],
            'retires_at' => ['required', 'date', 'after:now'],
        ];
    }
}
