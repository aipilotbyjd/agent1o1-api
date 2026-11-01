<?php

namespace App\Http\Requests\Api\Internal\V1\Nodes;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNodeCategoryPinRequest extends FormRequest
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
            'is_pinned' => ['required', 'boolean'],
        ];
    }
}
