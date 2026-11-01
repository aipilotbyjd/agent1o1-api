<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Referrals;

use App\Models\Referrals\ReferralCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminReferralCodeRequest extends FormRequest
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
        /** @var ReferralCode $code */
        $code = $this->route('code');

        return self::rulesFor($code);
    }

    /**
     * Shared with the `referrals:code` command.
     *
     * @return array<string, mixed>
     */
    public static function rulesFor(ReferralCode $code): array
    {
        return [
            'code' => ['sometimes', 'string', 'min:3', 'max:32', 'regex:/^[a-z0-9][a-z0-9-]*$/i', Rule::unique('referral_codes', 'code')->ignore($code->id)],
            'program_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('referral_programs', 'id')->whereNull('deleted_at')],
            'is_active' => ['sometimes', 'boolean'],
            'max_uses' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'expires_at' => ['sometimes', 'nullable', 'date'],
            'rule_multiplier' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'reward_workspace_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('workspaces', 'id')->where('owner_id', $code->user_id)->whereNull('deleted_at')],
        ];
    }
}
