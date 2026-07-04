<?php

namespace App\Http\Requests\Api\V1\Node;

use Illuminate\Foundation\Http\FormRequest;

class IndexNodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['nullable', 'uuid'],
            'kind' => ['nullable', 'string', 'max:50'],
            'search' => ['nullable', 'string', 'max:100'],
            'workspace_id' => ['nullable', 'uuid'],
        ];
    }
}
