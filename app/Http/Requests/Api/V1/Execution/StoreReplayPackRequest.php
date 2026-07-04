<?php

namespace App\Http\Requests\Api\V1\Execution;

use Illuminate\Foundation\Http\FormRequest;

class StoreReplayPackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:255'],
        ];
    }
}
