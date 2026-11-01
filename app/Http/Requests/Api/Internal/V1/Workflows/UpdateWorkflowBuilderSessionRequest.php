<?php

namespace App\Http\Requests\Api\Internal\V1\Workflows;

use App\Enums\Workflows\BuilderSessionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkflowBuilderSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `status` only moves a session in or out of the archive: `promoted` is
     * set by promoting, never directly.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'status' => ['sometimes', 'required', Rule::in([BuilderSessionStatus::Active->value, BuilderSessionStatus::Archived->value])],
        ];
    }
}
