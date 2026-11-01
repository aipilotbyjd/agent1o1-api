<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Referrals;

use App\Enums\Referrals\ReferralRewardType;
use App\Models\Workspaces\WorkspaceMember;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreManualReferralRewardRequest extends FormRequest
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
            'user_id' => ['required', 'uuid', Rule::exists('users', 'id')],
            'workspace_id' => ['nullable', 'uuid', Rule::exists('workspaces', 'id')->whereNull('deleted_at')],
            'reward_type' => ['required', Rule::enum(ReferralRewardType::class)],
            'credits' => ['required_if:reward_type,credits', 'nullable', 'integer', 'min:1', 'max:100000000'],
            'plan_id' => ['required_if:reward_type,plan_time', 'nullable', 'uuid', Rule::exists('plans', 'id')],
            'duration_days' => ['required_if:reward_type,plan_time', 'nullable', 'integer', 'min:1', 'max:3650'],
            'amount_cents' => ['required_if:reward_type,stripe_balance_credit', 'nullable', 'integer', 'min:1', 'max:100000000'],
            'trial_days' => ['required_if:reward_type,trial_extension', 'nullable', 'integer', 'min:1', 'max:365'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<int, Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $workspaceId = $this->input('workspace_id');

                if ($workspaceId === null || $validator->errors()->isNotEmpty()) {
                    return;
                }

                $isMember = WorkspaceMember::query()
                    ->where('workspace_id', $workspaceId)
                    ->where('user_id', $this->input('user_id'))
                    ->exists();

                if (! $isMember) {
                    $validator->errors()->add('workspace_id', 'The user is not a member of that workspace.');
                }
            },
        ];
    }
}
