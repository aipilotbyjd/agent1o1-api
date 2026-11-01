<?php

namespace App\Http\Requests\Api\Internal\V1\Referrals;

use App\Models\Referrals\ReferralCode;
use App\Models\User;
use App\Services\Referrals\ReferralCodes;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A user may pick a custom code once (letters, numbers and dashes) and
 * choose which of the workspaces they own their rewards land in.
 */
class UpdateReferralCodeRequest extends FormRequest
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
        /** @var User $user */
        $user = $this->user();

        return [
            'code' => [
                'sometimes',
                'string',
                'min:3',
                'max:32',
                'regex:/^[a-z0-9][a-z0-9-]*$/i',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (app(ReferralCodes::class)->isTaken((string) $value, $this->currentCode())) {
                        $fail('That referral code is already taken.');
                    }
                },
            ],
            'reward_workspace_id' => [
                'sometimes',
                'uuid',
                Rule::exists('workspaces', 'id')->where('owner_id', $user->id)->whereNull('deleted_at'),
            ],
        ];
    }

    /**
     * @return array<int, Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $code = $this->currentCode();

                if ($this->has('code') && $code?->custom_code_set_at !== null && $code->code !== mb_strtolower((string) $this->input('code'))) {
                    $validator->errors()->add('code', 'Your referral code can only be customised once.');
                }
            },
        ];
    }

    private function currentCode(): ?ReferralCode
    {
        return ReferralCode::query()->where('user_id', $this->user()?->id)->first();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'Use only letters, numbers and dashes, starting with a letter or number.',
            'reward_workspace_id.exists' => 'Rewards can only go to a workspace you own.',
        ];
    }
}
