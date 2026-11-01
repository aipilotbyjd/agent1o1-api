<?php

namespace App\Http\Requests\Api\Internal\V1\Agents;

use Illuminate\Foundation\Http\FormRequest;

class ImproveAgentInstructionsRequest extends FormRequest
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
            'instructions' => ['sometimes', 'nullable', 'string'],
            'request' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
