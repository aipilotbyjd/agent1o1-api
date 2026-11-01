<?php

namespace App\Http\Requests\Api\Internal\V1\KnowledgeBase;

use Illuminate\Foundation\Http\FormRequest;

class ReadKnowledgeDocumentRequest extends FormRequest
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
            'source' => ['required', 'string', 'max:255'],
            'collection' => ['nullable', 'string', 'max:255'],
            'private' => ['sometimes', 'boolean'],
        ];
    }
}
