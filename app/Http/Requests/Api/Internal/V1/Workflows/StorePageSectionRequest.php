<?php

namespace App\Http\Requests\Api\Internal\V1\Workflows;

use App\Enums\Workflows\PageBlockType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePageSectionRequest extends FormRequest
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
            'name' => [$this->isMethod('POST') ? 'required' : 'sometimes', 'string', 'max:120'],
            'block' => [$this->isMethod('POST') ? 'required' : 'prohibited', 'array'],
            'block.type' => ['required_with:block', Rule::enum(PageBlockType::class), Rule::notIn([PageBlockType::Column->value])],
        ];
    }
}
