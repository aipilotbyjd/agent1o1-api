<?php

namespace App\Console\Commands\Referrals;

use App\Console\Commands\Referrals\Concerns\ManagesReferralsFromConsole;
use App\Http\Requests\Api\SuperAdmin\V1\Referrals\UpdateAdminReferralCodeRequest;
use App\Models\Referrals\ReferralCode;
use App\Models\Referrals\ReferralProgram;
use App\Models\User;
use App\Services\Referrals\ReferralAdmin;
use App\Services\Referrals\ReferralCodes;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/**
 * Shows or overrides one referrer's code: a vanity code, a pinned program
 * (an influencer deal), a reward multiplier, a signup limit or expiry, or
 * switching it off.
 *
 *   referrals:code jane@acme.com
 *   referrals:code jane@acme.com --set code=jane --set program=creator-partners --set rule_multiplier=2
 *   referrals:code jane --set is_active=false
 */
class ReferralCodeCommand extends Command
{
    use ManagesReferralsFromConsole;

    protected $signature = 'referrals:code
        {code : The referral code, or the owner\'s email}
        {--set=* : code, program (slug), rule_multiplier, max_uses, expires_at, is_active or reward_workspace_id as key=value}';

    protected $description = 'Shows or overrides a referrer\'s referral code.';

    public function handle(ReferralAdmin $admin, ReferralCodes $codes): int
    {
        $code = $this->resolveCode($codes);

        if ($code === null) {
            $this->error('No referral code or user matches that.');

            return self::FAILURE;
        }

        if ($this->option('set') !== []) {
            try {
                $input = $this->parseSets($this->option('set'));

                if (array_key_exists('program', $input)) {
                    $input['program_id'] = $input['program'] === null
                        ? null
                        : (ReferralProgram::query()->where('slug', $input['program'])->value('id') ?? $input['program']);
                    unset($input['program']);
                }

                $data = $this->validateInput($input, UpdateAdminReferralCodeRequest::rulesFor($code));

                if ($data === null) {
                    return self::FAILURE;
                }

                $admin->updateCode($code, $data, null);
                $this->info('Code updated.');
            } catch (ValidationException $e) {
                $this->printErrors($e->errors());

                return self::FAILURE;
            }
        }

        $code->refresh()->load(['user', 'program'])->loadCount(['referrals', 'visits']);

        $this->table(['Field', 'Value'], [
            ['Code', $code->code],
            ['Owner', "{$code->user?->name} <{$code->user?->email}>"],
            ['Program', $code->program?->slug ?? 'default'],
            ['Multiplier', "×{$code->rule_multiplier}"],
            ['Signups / limit', $code->referrals_count.' / '.($code->max_uses ?? 'no limit')],
            ['Link visits', $code->visits_count],
            ['Expires', $code->expires_at?->toDateString() ?? 'never'],
            ['Active', $this->yesNo($code->is_active)],
        ]);

        return self::SUCCESS;
    }

    private function resolveCode(ReferralCodes $codes): ?ReferralCode
    {
        $key = $this->argument('code');

        if (str_contains($key, '@')) {
            $user = User::query()->where('email', $key)->first();

            return $user === null ? null : $codes->forUser($user);
        }

        return ReferralCode::query()->where('code', $codes->normalize($key))->first();
    }
}
