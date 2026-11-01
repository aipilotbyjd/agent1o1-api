<?php

namespace App\Http\Requests\Api\Internal\V1\Onboarding;

use App\Enums\Onboarding\DiscoverySource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class SubmitDiscoveryRequest extends FormRequest
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
            'discovery_source' => ['required', new Enum(DiscoverySource::class)],
        ];
    }
}
