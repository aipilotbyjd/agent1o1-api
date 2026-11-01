<?php

namespace App\Http\Requests\Api\Internal\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Regenerating recovery codes invalidates the ones the real owner is holding,
 * so it carries the same password check as enabling and disabling.
 */
class RegenerateRecoveryCodesRequest extends FormRequest
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
