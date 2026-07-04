<?php

namespace App\Http\Requests\Api\V1\Onboarding;

use App\Enums\DiscoverySource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveDiscoveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'discovery_source' => ['required', Rule::enum(DiscoverySource::class)],
        ];
    }
}
