<?php

namespace App\Http\Requests\Api\V1\LogStreaming;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLogStreamingConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'destination' => ['sometimes', Rule::in(['http', 'datadog', 'elk', 'logtail'])],
            'endpoint' => ['sometimes', 'url', 'max:500'],
            'headers' => ['sometimes', 'nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
