<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Accounts;

use App\Rules\SuperAdmin\IpOrCidr;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAccountRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'allowed_ips' => ['sometimes', 'nullable', 'array'],
            'allowed_ips.*' => ['string', new IpOrCidr],
        ];
    }
}
