<?php

namespace App\Console\Commands\Referrals;

use App\Console\Commands\Referrals\Concerns\ManagesReferralsFromConsole;
use App\Enums\Referrals\ReferralStatus;
use App\Models\Referrals\Referral;
use App\Models\Referrals\ReferralReward;
use App\Models\User;
use App\Services\Referrals\ReferralAdmin;
use App\Services\Referrals\ReferralRuleDescriber;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/**
 * Lists referrals, shows one with its rewards, or rejects/restores one.
 *
 *   referrals:referral list --status=converted --referrer=jane@acme.com
 *   referrals:referral show 9f8e7d6c
 *   referrals:referral reject 9f8e7d6c --reason="Same person, two accounts"
 *   referrals:referral restore 9f8e7d6c
 */
class ReferralReferralCommand extends Command
{
    use ManagesReferralsFromConsole;

    protected $signature = 'referrals:referral
        {action : list, show, reject or restore}
        {referral? : The referral id (short ids work)}
        {--status= : Filter the list: pending, verified, activated, converted or rejected}
        {--referrer= : Filter the list by the referrer\'s email}
        {--limit=25 : How many to list}
        {--reason= : Why a referral is rejected}';

    protected $description = 'Lists, shows, rejects or restores referrals.';

    public function handle(ReferralAdmin $admin, ReferralRuleDescriber $describer): int
    {
        if ($this->argument('action') === 'list') {
            return $this->list();
        }

        $referral = $this->argument('referral') === null
            ? null
            : $this->findByShortId(Referral::query(), $this->argument('referral'), 'referral');

        if ($referral === null) {
            if ($this->argument('referral') === null) {
                $this->error('Name the referral by its id (see `referrals:referral list`).');
            }

            return self::FAILURE;
        }

        try {
            return match ($this->argument('action')) {
                'show' => $this->show($referral, $describer),
                'reject' => $this->reject($admin, $referral),
                'restore' => $this->restore($admin, $referral),
                default => $this->unknownAction(),
            };
        } catch (ValidationException $e) {
            $this->printErrors($e->errors());

            return self::FAILURE;
        }
    }

    private function list(): int
    {
        $status = $this->option('status') !== null ? ReferralStatus::tryFrom($this->option('status')) : null;

        if ($this->option('status') !== null && $status === null) {
            $this->error('Unknown status. Use pending, verified, activated, converted or rejected.');

            return self::FAILURE;
        }

        $referrerId = $this->option('referrer') !== null
            ? User::query()->where('email', $this->option('referrer'))->value('id') ?? 'none'
            : null;

        $referrals = Referral::query()
            ->with(['referrer:id,email', 'referredUser:id,email', 'code:id,code'])
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->when($referrerId !== null, fn ($query) => $query->where('referrer_user_id', $referrerId))
            ->latest()
            ->limit((int) $this->option('limit'))
            ->get();

        $this->table(
            ['Id', 'Referrer', 'New user', 'Code', 'Status', 'Signed up'],
            $referrals->map(fn (Referral $referral): array => [
                $this->shortId($referral->id),
                $referral->referrer?->email,
                $referral->referredUser?->email,
                $referral->code?->code,
                $referral->status->value.($referral->rejection_reason ? " ({$referral->rejection_reason})" : ''),
                $referral->created_at?->toDateString(),
            ])->all(),
        );

        return self::SUCCESS;
    }

    private function show(Referral $referral, ReferralRuleDescriber $describer): int
    {
        $referral->load(['referrer', 'referredUser', 'code', 'program', 'rewards.plan']);

        $this->table(['Field', 'Value'], [
            ['Id', $referral->id],
            ['Referrer', $referral->referrer?->email],
            ['New user', $referral->referredUser?->email],
            ['Code / program', "{$referral->code?->code} / {$referral->program?->slug}"],
            ['Status', $referral->status->value],
            ['Verified / active / paying', implode(' / ', [
                $referral->verified_at?->toDateString() ?? '-',
                $referral->activated_at?->toDateString() ?? '-',
                $referral->converted_at?->toDateString() ?? '-',
            ])],
            ['Rejection reason', $referral->rejection_reason ?? '-'],
        ]);

        $this->table(
            ['Reward id', 'For', 'Reward', 'Status'],
            $referral->rewards->map(fn (ReferralReward $reward): array => [
                $this->shortId($reward->id),
                $reward->recipient_role->value,
                $describer->rewardSummary($reward),
                $reward->status->value,
            ])->all(),
        );

        return self::SUCCESS;
    }

    private function reject(ReferralAdmin $admin, Referral $referral): int
    {
        $reason = $this->option('reason');

        if (! is_string($reason) || trim($reason) === '') {
            $this->error('Pass --reason="…": it is stored with the rejection.');

            return self::FAILURE;
        }

        $admin->rejectReferral($referral, trim($reason), null);

        $this->info('Referral rejected and its rewards withdrawn.');

        return self::SUCCESS;
    }

    private function restore(ReferralAdmin $admin, Referral $referral): int
    {
        $admin->restoreReferral($referral, null);

        $this->info("Referral restored as {$referral->status->value}. Rewards revoked by the rejection stay revoked; re-grant with `referrals:grant` if needed.");

        return self::SUCCESS;
    }

    private function unknownAction(): int
    {
        $this->error('Unknown action. Use list, show, reject or restore.');

        return self::FAILURE;
    }
}
