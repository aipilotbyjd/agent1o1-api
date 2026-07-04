<?php

namespace App\Http\Requests\Api\V1\Onboarding;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InviteTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'emails' => ['required', 'array', 'min:1', 'max:20'],
            'emails.*' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::in(Role::assignableValues())],
            'personal_note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
