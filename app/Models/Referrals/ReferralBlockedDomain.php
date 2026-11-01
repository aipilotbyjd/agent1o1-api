<?php

namespace App\Models\Referrals;

use App\Services\Referrals\ReferralSettings;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['domain', 'reason'])]
class ReferralBlockedDomain extends Model
{
    use HasUuids;

    protected static function booted(): void
    {
        static::saving(function (ReferralBlockedDomain $domain): void {
            $domain->domain = mb_strtolower(trim($domain->domain));
        });

        static::saved(fn () => ReferralSettings::flush());
        static::deleted(fn () => ReferralSettings::flush());
    }
}
