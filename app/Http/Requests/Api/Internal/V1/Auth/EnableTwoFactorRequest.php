<?php

namespace App\Http\Requests\Api\Internal\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Turning two-factor on is as sensitive as turning it off: whoever binds the
 * authenticator controls every future sign-in, so a stolen access token must
 * not be enough on its own.
 */
class EnableTwoFactorRequest extends FormRequest
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
            'current_password' => ['required', 'string', 'current_password:api'],
        ];
    }
}
