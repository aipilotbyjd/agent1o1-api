<?php

namespace App\Http\Requests\Api\Internal\V1\Onboarding;

use Illuminate\Foundation\Http\FormRequest;

class SelectPlanRequest extends FormRequest
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
            'plan_slug' => ['required', 'string', 'exists:plans,slug'],
        ];
    }
}
