<?php

namespace App\Actions\Referrals;

use App\Models\Referrals\ReferralVisit;
use App\Services\Referrals\IpHasher;
use App\Services\Referrals\ReferralCodes;
use App\Services\Referrals\ReferralSettings;
use Illuminate\Support\Str;

/**
 * Logs a landing on a `?ref=` link and returns the visitor id the frontend
 * keeps in its cookie and sends back at signup. An unknown or unusable code
 * records nothing and returns null, so the frontend can drop the cookie.
 */
class RecordReferralVisitAction
{
    public function __construct(
        private readonly ReferralCodes $codes,
        private readonly ReferralSettings $settings,
    ) {}

    /**
     * @param  array{visitor_id?: ?string, landing_url?: ?string, referrer_url?: ?string, utm?: ?array<string, string>}  $data
     * @return array{visitor_id: string, code: string}|null
     */
    public function execute(string $code, array $data, ?string $ip, ?string $userAgent): ?array
    {
        if (! $this->settings->enabled()) {
            return null;
        }

        $referralCode = $this->codes->findUsable($code);

        if ($referralCode === null) {
            return null;
        }

        $visitorId = isset($data['visitor_id']) && Str::isUuid($data['visitor_id'])
            ? $data['visitor_id']
            : (string) Str::uuid();

        ReferralVisit::query()->create([
            'referral_code_id' => $referralCode->id,
            'visitor_id' => $visitorId,
            'landing_url' => $data['landing_url'] ?? null,
            'referrer_url' => $data['referrer_url'] ?? null,
            'utm' => $data['utm'] ?? null,
            'ip_hash' => IpHasher::hash($ip),
            'user_agent' => $userAgent !== null ? Str::limit($userAgent, 250, '') : null,
        ]);

        return ['visitor_id' => $visitorId, 'code' => $referralCode->code];
    }
}
