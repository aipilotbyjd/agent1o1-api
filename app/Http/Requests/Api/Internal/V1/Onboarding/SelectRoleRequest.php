<?php

namespace App\Http\Requests\Api\Internal\V1\Onboarding;

use App\Enums\Onboarding\JobRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class SelectRoleRequest extends FormRequest
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
            'job_role' => ['required', new Enum(JobRole::class)],
        ];
    }
}
