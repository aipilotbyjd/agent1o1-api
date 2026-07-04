<?php

namespace App\Http\Requests\Api\V1\PinnedData;

use Illuminate\Foundation\Http\FormRequest;

class StorePinnedDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'node_id' => ['required', 'string', 'max:255'],
            'data' => ['required', 'array'],
        ];
    }
}
