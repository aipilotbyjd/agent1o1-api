<?php

namespace App\Services\Broadcasts;

use App\Models\User;
use Illuminate\Support\Facades\URL;

/**
 * A member's choice about marketing email, and the signed links that let them
 * make it from an inbox without signing in. The links carry no expiry on
 * purpose: an old email must still be able to unsubscribe.
 */
class MarketingPreferences
{
    public function optOut(User $user): User
    {
        if (! $user->hasOptedOutOfMarketing()) {
            $user->forceFill(['marketing_opted_out_at' => now()])->save();
        }

        return $user;
    }

    public function optIn(User $user): User
    {
        if ($user->hasOptedOutOfMarketing()) {
            $user->forceFill(['marketing_opted_out_at' => null])->save();
        }

        return $user;
    }

    /**
     * The page linked from the email footer. Opening it changes nothing (mail
     * scanners open links); the person confirms on the page.
     */
    public function pageUrl(User $user): string
    {
        return URL::signedRoute('broadcasts.unsubscribe.show', ['user' => $user->getKey()]);
    }

    /**
     * The endpoint mail clients POST to for one-click unsubscribe (RFC 8058).
     */
    public function oneClickUrl(User $user): string
    {
        return URL::signedRoute('broadcasts.unsubscribe.one-click', ['user' => $user->getKey()]);
    }
}
