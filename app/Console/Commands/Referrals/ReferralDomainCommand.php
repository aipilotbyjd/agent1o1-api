<?php

namespace App\Console\Commands\Referrals;

use App\Console\Commands\Referrals\Concerns\ManagesReferralsFromConsole;
use App\Http\Requests\Api\SuperAdmin\V1\Referrals\StoreReferralBlockedDomainRequest;
use App\Models\Referrals\ReferralBlockedDomain;
use App\Services\Referrals\ReferralAdmin;
use Illuminate\Console\Command;

/**
 * Email domains whose signups never earn anyone a referral reward.
 *
 *   referrals:domain list
 *   referrals:domain add spam.example --reason="Throwaway mail"
 *   referrals:domain remove spam.example
 */
class ReferralDomainCommand extends Command
{
    use ManagesReferralsFromConsole;

    protected $signature = 'referrals:domain
        {action : list, add or remove}
        {domain? : The email domain, e.g. mailinator.com}
        {--reason= : Why it is blocked}';

    protected $description = 'Lists, blocks or unblocks email domains for referral rewards.';

    public function handle(ReferralAdmin $admin): int
    {
        $domain = $this->argument('domain') !== null ? mb_strtolower(trim($this->argument('domain'))) : null;

        if ($this->argument('action') === 'list') {
            $this->table(
                ['Domain', 'Reason', 'Added'],
                ReferralBlockedDomain::query()->orderBy('domain')->get()
                    ->map(fn (ReferralBlockedDomain $row): array => [$row->domain, $row->reason ?? '-', $row->created_at?->toDateString()])
                    ->all(),
            );

            return self::SUCCESS;
        }

        if ($domain === null) {
            $this->error('Name the domain, e.g. mailinator.com.');

            return self::FAILURE;
        }

        if ($this->argument('action') === 'add') {
            $data = $this->validateInput(['domain' => $domain, 'reason' => $this->option('reason')], StoreReferralBlockedDomainRequest::rulesFor());

            if ($data === null) {
                return self::FAILURE;
            }

            $admin->blockDomain($data['domain'], $data['reason'] ?? null, null);
            $this->info("Blocked {$domain}.");

            return self::SUCCESS;
        }

        if ($this->argument('action') === 'remove') {
            $blocked = ReferralBlockedDomain::query()->where('domain', $domain)->first();

            if ($blocked === null) {
                $this->error("{$domain} is not blocked.");

                return self::FAILURE;
            }

            $admin->unblockDomain($blocked, null);
            $this->info("Unblocked {$domain}.");

            return self::SUCCESS;
        }

        $this->error('Unknown action. Use list, add or remove.');

        return self::FAILURE;
    }
}
