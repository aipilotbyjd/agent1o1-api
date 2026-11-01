<?php

namespace App\Services\Referrals;

use App\Models\Referrals\ReferralCode;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Issues and looks up `?ref=` codes. Codes are stored lower-case and
 * matched case-insensitively, so `Jane` and `jane` are the same link.
 */
class ReferralCodes
{
    /**
     * The user's code, created on first use as `<name-slug>-<4 chars>`.
     */
    public function forUser(User $user): ReferralCode
    {
        return ReferralCode::query()->where('user_id', $user->id)->first()
            ?? ReferralCode::query()->createOrFirst(['user_id' => $user->id], ['code' => $this->generate($user)]);
    }

    public function findUsable(string $code): ?ReferralCode
    {
        $found = ReferralCode::query()->where('code', $this->normalize($code))->with(['user', 'program'])->first();

        return $found?->isUsable() ? $found : null;
    }

    public function normalize(string $code): string
    {
        return mb_strtolower(trim($code));
    }

    public function isTaken(string $code, ?ReferralCode $except = null): bool
    {
        return ReferralCode::query()
            ->where('code', $this->normalize($code))
            ->when($except !== null, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->exists();
    }

    private function generate(User $user): string
    {
        $base = Str::limit(Str::slug($user->name) ?: 'friend', 20, '');

        do {
            $code = mb_strtolower($base.'-'.Str::random(4));
        } while ($this->isTaken($code));

        return $code;
    }
}
