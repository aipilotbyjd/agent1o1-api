<?php

namespace App\Http\Requests\Api\V1\Onboarding;

use App\Enums\JobRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'job_role' => ['required', Rule::enum(JobRole::class)],
        ];
    }
}
