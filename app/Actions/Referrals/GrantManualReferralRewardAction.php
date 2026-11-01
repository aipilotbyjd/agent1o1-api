<?php

namespace App\Actions\Referrals;

use App\Enums\Referrals\ReferralRecipient;
use App\Enums\Referrals\ReferralRewardStatus;
use App\Enums\Referrals\ReferralRewardType;
use App\Enums\Referrals\ReferralTrigger;
use App\Models\Referrals\ReferralReward;
use App\Models\SuperAdmin\SuperAdmin;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Referrals\ReferralRecipientWorkspace;
use Illuminate\Support\Str;

/**
 * A goodwill reward an admin hands out directly, from the admin API or
 * `php artisan referrals:grant`. It goes on the same ledger as earned
 * rewards (trigger `manual`) and is granted immediately.
 */
class GrantManualReferralRewardAction
{
    public function __construct(
        private readonly GrantReferralRewardAction $grant,
        private readonly ReferralRecipientWorkspace $workspaces,
    ) {}

    /**
     * @param  array{reward_type: ReferralRewardType, credits?: ?int, plan_id?: ?string, duration_days?: ?int, amount_cents?: ?int, trial_days?: ?int}  $values
     */
    public function execute(User $recipient, ?Workspace $workspace, array $values, ?SuperAdmin $admin, ?string $notes = null): ReferralReward
    {
        $workspace ??= $this->workspaces->forReferrer($recipient);

        $reward = ReferralReward::query()->create([
            'recipient_user_id' => $recipient->id,
            'workspace_id' => $workspace?->id,
            'recipient_role' => ReferralRecipient::Referrer,
            'trigger' => ReferralTrigger::Manual,
            'reward_type' => $values['reward_type'],
            'credits' => $values['credits'] ?? null,
            'plan_id' => $values['plan_id'] ?? null,
            'duration_days' => $values['duration_days'] ?? null,
            'amount_cents' => $values['amount_cents'] ?? null,
            'trial_days' => $values['trial_days'] ?? null,
            'status' => ReferralRewardStatus::Pending,
            'grant_after' => now(),
            'granted_by' => $admin?->id,
            'notes' => $notes,
            'idempotency_key' => 'manual:'.Str::uuid(),
        ]);

        $this->grant->execute($reward, $admin);

        return $reward->refresh();
    }
}
