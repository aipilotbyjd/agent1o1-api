<?php

namespace App\Contracts\Triggers;

/**
 * A trigger node whose event the provider only tells us about as it happens and
 * never lists afterwards (Slack's "reaction removed"). Reading can't find it, so
 * it works through instant delivery or not at all; Triggers & Events says so
 * when instant delivery isn't working.
 */
interface NeedsPushDelivery {}
