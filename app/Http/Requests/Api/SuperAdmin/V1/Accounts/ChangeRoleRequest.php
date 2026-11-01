<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Accounts;

use App\Enums\SuperAdmin\SuperAdminRole;
use App\Http\Requests\Api\SuperAdmin\V1\StepUpRequest;
use Illuminate\Validation\Rule;

class ChangeRoleRequest extends StepUpRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'role' => ['required', Rule::enum(SuperAdminRole::class)],
        ];
    }
}
