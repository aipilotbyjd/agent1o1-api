<?php

namespace App\Http\Requests\Api\Internal\V1\Triggers;

use App\Enums\Triggers\TriggerEventStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexTriggerEventsRequest extends FormRequest
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
            // Narrows the list to these statuses, e.g. only what was rejected.
            'status' => ['nullable', 'array'],
            'status.*' => [Rule::enum(TriggerEventStatus::class)],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ];
    }
}
