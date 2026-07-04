<?php

namespace App\Http\Requests\Api\V1\Node;

use Illuminate\Foundation\Http\FormRequest;

class ShowNodeCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'workspace_id' => ['nullable', 'uuid'],
        ];
    }
}
