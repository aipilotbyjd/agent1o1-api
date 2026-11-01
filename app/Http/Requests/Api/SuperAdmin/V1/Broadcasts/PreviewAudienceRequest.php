<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Broadcasts;

use App\Enums\Broadcasts\BroadcastCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreviewAudienceRequest extends FormRequest
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
            'category' => ['sometimes', Rule::enum(BroadcastCategory::class)],
            ...AudienceRules::for(),
        ];
    }
}
