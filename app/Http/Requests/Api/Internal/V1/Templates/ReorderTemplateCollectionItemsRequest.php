<?php

namespace App\Http\Requests\Api\Internal\V1\Templates;

use Illuminate\Foundation\Http\FormRequest;

class ReorderTemplateCollectionItemsRequest extends FormRequest
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
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'uuid'],
            'items.*.position' => ['required', 'integer', 'min:0'],
        ];
    }
}
