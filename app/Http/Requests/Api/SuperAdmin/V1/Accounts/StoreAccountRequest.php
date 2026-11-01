<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Accounts;

use App\Enums\SuperAdmin\SuperAdminRole;
use App\Http\Requests\Api\SuperAdmin\V1\StepUpRequest;
use App\Rules\SuperAdmin\IpOrCidr;
use Illuminate\Validation\Rule;

class StoreAccountRequest extends StepUpRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('super_admins', 'email')],
            'role' => ['required', Rule::enum(SuperAdminRole::class)],
            'allowed_ips' => ['nullable', 'array'],
            'allowed_ips.*' => ['string', new IpOrCidr],
        ];
    }
}
