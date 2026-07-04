<?php

namespace App\Http\Requests\Api\V1\Billing;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PackCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'pack_key' => ['required', 'string', 'in:'.implode(',', array_keys(config('billing.packs', [])))],
        ];
    }
}
