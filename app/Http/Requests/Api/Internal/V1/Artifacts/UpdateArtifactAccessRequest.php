<?php

namespace App\Http\Requests\Api\Internal\V1\Artifacts;

use App\Enums\Artifacts\ArtifactGeneralAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateArtifactAccessRequest extends FormRequest
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
            'general_access' => ['required', new Enum(ArtifactGeneralAccess::class)],
        ];
    }
}
