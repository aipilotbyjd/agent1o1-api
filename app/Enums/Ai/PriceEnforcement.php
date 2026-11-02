<?php

namespace App\Enums\Ai;

use App\Services\Billing\BillingSettings;

/**
 * What happens to a call on the platform's own key whose model price is not
 * verified (`billing.price_enforcement`).
 */
enum PriceEnforcement: string
{
    /** Calls run; nothing is recorded. */
    case Off = 'off';

    /** Calls run, are billed at the conservative fallback when unpriced, and are counted so they show up in the price book report. */
    case Warn = 'warn';

    /** Platform-key calls on a model without a verified price are refused; a workspace's own key still works. */
    case Enforce = 'enforce';

    /**
     * The mode in force: a Super Admin override (`BillingSettings`), else
     * `billing.price_enforcement`.
     */
    public static function current(): self
    {
        return self::tryFrom((string) app(BillingSettings::class)->get('price_enforcement')) ?? self::Warn;
    }
}
