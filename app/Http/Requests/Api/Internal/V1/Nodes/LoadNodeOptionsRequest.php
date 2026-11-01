<?php

namespace App\Http\Requests\Api\Internal\V1\Nodes;

use Illuminate\Foundation\Http\FormRequest;

class LoadNodeOptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'max:255'],
            'field' => ['required', 'string', 'max:255'],
            // Only the account and the fields a list depends on are read;
            // the cap keeps a whole oversized config from being posted.
            'config' => ['nullable', 'array', 'max:100'],
            'search' => ['nullable', 'string', 'max:200'],
            'cursor' => ['nullable', 'string', 'max:500'],
        ];
    }
}
