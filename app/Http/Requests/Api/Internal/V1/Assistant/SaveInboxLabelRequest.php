<?php

namespace App\Http\Requests\Api\Internal\V1\Assistant;

use App\Enums\Assistant\AssistantInboxLabelGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveInboxLabelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Gmail rejects some characters in label names; "/" would nest the label.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creating = $this->isMethod('post');

        return [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:60', 'not_regex:/[\/\\\\"]/'],
            'definition' => [$creating ? 'required' : 'sometimes', 'string', 'max:500'],
            'color' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'group' => ['sometimes', Rule::enum(AssistantInboxLabelGroup::class)],
            'enabled' => ['sometimes', 'boolean'],
        ];
    }
}
