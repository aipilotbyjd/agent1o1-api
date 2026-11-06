<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Models;

use App\Http\Requests\Api\SuperAdmin\V1\StepUpRequest;

/**
 * Stores a one-click login app (OAuth client id and secret) for a provider.
 * A secret, so it needs a reason and a fresh authenticator code.
 */
class ProviderConnectCredentialsRequest extends StepUpRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'client_id' => ['required', 'string', 'max:500'],
            'client_secret' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
