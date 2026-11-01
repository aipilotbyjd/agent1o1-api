<?php

namespace App\Http\Requests\Api\Internal\V1\KnowledgeBase;

use Illuminate\Foundation\Http\FormRequest;

class SearchKnowledgeRequest extends FormRequest
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
            'query' => ['required', 'string', 'max:2000'],
            'collection' => ['nullable', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
