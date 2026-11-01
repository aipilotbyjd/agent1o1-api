<?php

namespace App\Console\Commands\Referrals;

use App\Console\Commands\Referrals\Concerns\ManagesReferralsFromConsole;
use App\Enums\Referrals\ReferralRewardStatus;
use App\Models\Referrals\ReferralReward;
use App\Services\Referrals\ReferralAdmin;
use App\Services\Referrals\ReferralRuleDescriber;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/**
 * The reward ledger: list rewards, approve ones held for review, or grant
 * one before its hold ends. (`referrals:revoke` withdraws a reward;
 * `referrals:grant` hands one out by hand.)
 *
 *   referrals:reward list --status=awaiting_approval
 *   referrals:reward approve 4c3b2a1f
 *   referrals:reward grant-now 4c3b2a1f
 */
class ReferralRewardCommand extends Command
{
    use ManagesReferralsFromConsole;

    protected $signature = 'referrals:reward
        {action : list, approve or grant-now}
        {reward? : The reward id (short ids work)}
        {--status= : Filter the list: awaiting_approval, pending, granted or revoked}
        {--limit=25 : How many to list}';

    protected $description = 'Lists referral rewards, approves them, or grants them early.';

    public function handle(ReferralAdmin $admin, ReferralRuleDescriber $describer): int
    {
        if ($this->argument('action') === 'list') {
            return $this->list($describer);
        }

        $reward = $this->argument('reward') === null
            ? null
            : $this->findByShortId(ReferralReward::query(), $this->argument('reward'), 'reward');

        if ($reward === null) {
            if ($this->argument('reward') === null) {
                $this->error('Name the reward by its id (see `referrals:reward list`).');
            }

            return self::FAILURE;
        }

        try {
            return match ($this->argument('action')) {
                'approve' => $this->approve($admin, $reward),
                'grant-now' => $this->grantNow($admin, $reward),
                default => $this->unknownAction(),
            };
        } catch (ValidationException $e) {
            $this->printErrors($e->errors());

            return self::FAILURE;
        }
    }

    private function list(ReferralRuleDescriber $describer): int
    {
        $status = $this->option('status') !== null ? ReferralRewardStatus::tryFrom($this->option('status')) : null;

        if ($this->option('status') !== null && $status === null) {
            $this->error('Unknown status. Use awaiting_approval, pending, granted or revoked.');

            return self::FAILURE;
        }

        $rewards = ReferralReward::query()
            ->with(['recipient:id,email', 'plan'])
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->latest()
            ->limit((int) $this->option('limit'))
            ->get();

        $this->table(
            ['Id', 'Recipient', 'Reward', 'Why', 'Status', 'Date'],
            $rewards->map(fn (ReferralReward $reward): array => [
                $this->shortId($reward->id),
                $reward->recipient?->email,
                $describer->rewardSummary($reward),
                $reward->trigger->value,
                $reward->status->value,
                $reward->status === ReferralRewardStatus::Pending
                    ? 'due '.$reward->grant_after?->toDateString()
                    : ($reward->granted_at ?? $reward->created_at)?->toDateString(),
            ])->all(),
        );

        return self::SUCCESS;
    }

    private function approve(ReferralAdmin $admin, ReferralReward $reward): int
    {
        $admin->approveReward($reward, null);

        $this->info($reward->refresh()->status === ReferralRewardStatus::Granted
            ? 'Approved and granted.'
            : 'Approved; it will be granted when its hold ends.');

        return self::SUCCESS;
    }

    private function grantNow(ReferralAdmin $admin, ReferralReward $reward): int
    {
        $admin->grantRewardNow($reward, null);

        $this->info('Granted.');

        return self::SUCCESS;
    }

    private function unknownAction(): int
    {
        $this->error('Unknown action. Use list, approve or grant-now.');

        return self::FAILURE;
    }
}
