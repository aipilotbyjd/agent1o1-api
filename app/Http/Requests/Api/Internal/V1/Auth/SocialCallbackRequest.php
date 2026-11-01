<?php

namespace App\Http\Requests\Api\Internal\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;

class SocialCallbackRequest extends FormRequest
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
            'code' => ['required', 'string'],
            'state' => ['sometimes', 'string'],
        ];
    }
}
