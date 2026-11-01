<?php

namespace App\Http\Requests\Api\Internal\V1\Triggers;

use App\Enums\Triggers\TriggerTargetType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexTriggersRequest extends FormRequest
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
            'target_type' => ['nullable', Rule::enum(TriggerTargetType::class), 'required_with:target_id'],
            'target_id' => ['nullable', 'uuid'],
        ];
    }
}
