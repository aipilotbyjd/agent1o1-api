<?php

namespace App\Exceptions;

use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use RuntimeException;

/**
 * Domain-level connector/credential errors: an OAuth-only connector store
 * attempt, an invalid/expired OAuth state, a failed token exchange/refresh,
 * an expired or unresolvable credential at node-execution time. Mapped to a
 * 422 response in `bootstrap/app.php`.
 */
class ConnectorException extends RuntimeException
{
    /**
     * A manual connection saved without a field its form requires.
     *
     * @param  list<string>  $labels
     */
    public static function missingFields(Connector $connector, array $labels): self
    {
        return new self("{$connector->name} needs: ".implode(', ', $labels).'.');
    }

    public static function notConfigured(Connector $connector): self
    {
        return new self("{$connector->name} isn't set up on this server yet, so it can't be connected.");
    }

    /**
     * The access token is past its expiry and can't be renewed, only the
     * member reconnecting the app fixes it.
     */
    public static function needsReconnect(ConnectorCredential $credential): self
    {
        $app = $credential->connector?->name ?? 'This app';

        return new self("The {$app} connection \"{$credential->name}\" has expired. Reconnect it in Apps.");
    }
}
