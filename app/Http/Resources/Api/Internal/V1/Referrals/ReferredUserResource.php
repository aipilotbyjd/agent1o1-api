<?php

namespace App\Http\Resources\Api\Internal\V1\Referrals;

use App\Models\Referrals\Referral;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A referral as its referrer sees it: the referred person's email masked
 *
 * (`j***@acme.com`), no workspace, and no fraud reason for a rejection.
 *
 * @mixin Referral
 */
class ReferredUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => self::maskEmail($this->referredUser?->email),
            'status' => $this->status,
            'signed_up_at' => $this->created_at,
            'verified_at' => $this->verified_at,
            'activated_at' => $this->activated_at,
            'converted_at' => $this->converted_at,
        ];
    }

    public static function maskEmail(?string $email): ?string
    {
        if ($email === null || ! str_contains($email, '@')) {
            return null;
        }

        [$local, $domain] = explode('@', $email, 2);

        return mb_substr($local, 0, 1).'***@'.$domain;
    }
}
