<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Pricing;

use Illuminate\Foundation\Http\FormRequest;

class ReorderPlansRequest extends FormRequest
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
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'uuid', 'distinct'],
        ];
    }
}
