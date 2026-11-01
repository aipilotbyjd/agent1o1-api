<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Catalog;

use App\Http\Requests\Api\SuperAdmin\V1\ReasonRequest;
use Illuminate\Validation\Rule;

/**
 * Flags one built-in node as beta, deprecated or hidden, or clears the flag (a null status).
 */
class SetNodeStatusRequest extends ReasonRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'status' => ['present', 'nullable', Rule::in(['beta', 'deprecated', 'hidden'])],
            'note' => ['nullable', 'string', 'max:300'],
        ];
    }
}
