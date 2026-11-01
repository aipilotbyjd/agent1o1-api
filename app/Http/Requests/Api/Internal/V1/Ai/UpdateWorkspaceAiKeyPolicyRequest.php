<?php

namespace App\Http\Requests\Api\Internal\V1\Ai;

use App\Enums\Ai\PlatformKeyUsage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateWorkspaceAiKeyPolicyRequest extends FormRequest
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
            'platform_usage' => ['sometimes', new Enum(PlatformKeyUsage::class)],
            'allow_personal_keys' => ['sometimes', 'boolean'],
        ];
    }
}
