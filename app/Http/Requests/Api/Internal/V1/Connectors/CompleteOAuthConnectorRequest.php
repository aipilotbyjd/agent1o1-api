<?php

namespace App\Http\Requests\Api\Internal\V1\Connectors;

use Illuminate\Foundation\Http\FormRequest;

/**
 * What the provider sent the member's browser back with: the `state` the
 * OAuth flow was started with and the authorization `code`.
 */
class CompleteOAuthConnectorRequest extends FormRequest
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
            'state' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:4096'],
        ];
    }
}
