<?php

namespace App\Enums\Triggers;

/**
 * What became of an event in the provider inbox.
 */
enum ProviderEventStatus: string
{
    /**
     * Stored, not routed yet.
     */
    case Received = 'received';

    /**
     * Matched against subscriptions and handed to their triggers.
     */
    case Routed = 'routed';

    /**
     * No subscription wanted it.
     */
    case Dropped = 'dropped';

    /**
     * Routing failed.
     */
    case Failed = 'failed';
}
