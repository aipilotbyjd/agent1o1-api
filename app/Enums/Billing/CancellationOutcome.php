<?php

namespace App\Enums\Billing;

/**
 * How a trip through the cancellation flow ended.
 */
enum CancellationOutcome: string
{
    case Canceled = 'canceled';
    case DiscountAccepted = 'discount_accepted';
    case HelpRequested = 'help_requested';
}
