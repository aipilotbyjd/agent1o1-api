<?php

namespace App\Http\Requests\Api\Internal\V1\Templates;

use App\Enums\Templates\TemplateType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListTemplatesRequest extends FormRequest
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
            'type' => ['sometimes', 'array'],
            'type.*' => ['string', Rule::enum(TemplateType::class)],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }

    /**
     * @return array<int, TemplateType>
     */
    public function types(): array
    {
        return array_map(
            fn (string $type): TemplateType => TemplateType::from($type),
            array_values(array_unique($this->validated('type', []))),
        );
    }
}
