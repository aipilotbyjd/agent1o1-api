<?php

namespace App\Http\Requests\Api\Internal\V1\Assistant;

use App\Rules\Timezone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBriefingConfigRequest extends FormRequest
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
            'enabled' => ['sometimes', 'boolean'],
            'schedule' => ['sometimes', 'array'],
            'schedule.time' => ['required_with:schedule', 'date_format:H:i'],
            'schedule.days' => ['required_with:schedule', 'array', 'min:1'],
            'schedule.days.*' => ['integer', 'between:1,7', 'distinct'],
            'schedule.timezone' => ['required_with:schedule', 'string', new Timezone],
            'connector_scope' => ['sometimes', Rule::in(['all', 'selected'])],
            'connector_keys' => ['nullable', 'array', 'max:'.config('assistant.briefings.max_connectors')],
            'connector_keys.*' => ['string', 'max:100'],
            'instructions' => ['sometimes', 'nullable', 'string', 'max:'.config('assistant.briefings.instructions_max_chars')],
            'delivery' => ['sometimes', 'array'],
            'delivery.email' => ['boolean'],
            'settings' => ['sometimes', 'array'],
            'settings.auto' => ['sometimes', 'boolean'],
            'settings.minutes_before' => ['sometimes', 'integer', 'between:5,240'],
            'settings.scope' => ['sometimes', Rule::in(['external_only', 'all'])],
        ];
    }
}
