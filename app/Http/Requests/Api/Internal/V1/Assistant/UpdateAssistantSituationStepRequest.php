<?php

namespace App\Http\Requests\Api\Internal\V1\Assistant;

use App\Enums\Assistant\AssistantSituationStepStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAssistantSituationStepRequest extends FormRequest
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
        return ['status' => ['required', Rule::enum(AssistantSituationStepStatus::class)]];
    }
}
