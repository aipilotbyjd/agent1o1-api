<?php

namespace App\Listeners\Analytics;

use App\Services\Analytics\ProductAnalytics;
use Illuminate\Auth\Events\Verified;

/**
 * Activation funnel step: signup → email verified.
 */
class CaptureEmailVerified
{
    public function __construct(private readonly ProductAnalytics $analytics) {}

    public function handle(Verified $event): void
    {
        $this->analytics->capture((string) $event->user->getKey(), 'email_verified');
    }
}
