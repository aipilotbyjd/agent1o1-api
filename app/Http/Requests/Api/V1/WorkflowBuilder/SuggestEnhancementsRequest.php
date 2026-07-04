<?php

namespace App\Http\Requests\Api\V1\WorkflowBuilder;

use Illuminate\Foundation\Http\FormRequest;

class SuggestEnhancementsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nodes' => ['required', 'array'],
            'edges' => ['required', 'array'],
        ];
    }
}
