<?php

namespace App\Enums\Ai;

/**
 * Where a model price came from, so a stale or guessed one can be told from
 * one read off a contract.
 */
enum ModelPriceSource: string
{
    /** Read off the provider's public price list. */
    case ProviderList = 'provider_list';

    /** Negotiated or committed pricing. */
    case Contract = 'contract';

    /** A guess, or an amortised cost for a model we run ourselves. */
    case Estimate = 'estimate';

    /** Copied from `config/billing.php` (the prices that predate this table). */
    case Config = 'config';
}
