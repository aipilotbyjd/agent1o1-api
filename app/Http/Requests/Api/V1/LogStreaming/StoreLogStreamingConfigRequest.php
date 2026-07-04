<?php

namespace App\Http\Requests\Api\V1\LogStreaming;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLogStreamingConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'destination' => ['required', Rule::in(['http', 'datadog', 'elk', 'logtail'])],
            'endpoint' => ['required', 'url', 'max:500'],
            'headers' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
