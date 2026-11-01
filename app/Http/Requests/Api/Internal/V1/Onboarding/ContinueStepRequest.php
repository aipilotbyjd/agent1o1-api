<?php

namespace App\Http\Requests\Api\Internal\V1\Onboarding;

use App\Enums\Onboarding\OnboardingStep;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class ContinueStepRequest extends FormRequest
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
            'step' => ['required', new Enum(OnboardingStep::class)],
        ];
    }
}
