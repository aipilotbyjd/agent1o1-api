<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ChangePasswordRequest extends FormRequest
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
        $password = Password::min((int) config('super_admin_auth.password.min_length'))
            ->mixedCase()
            ->numbers()
            ->symbols();

        if (config('super_admin_auth.password.check_compromised')) {
            $password->uncompromised();
        }

        return [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', 'different:current_password', $password],
        ];
    }
}
