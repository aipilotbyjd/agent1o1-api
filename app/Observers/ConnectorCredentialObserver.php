<?php

namespace App\Observers;

use App\Models\Connectors\ConnectorCredential;
use App\Services\Analytics\ProductAnalytics;

/**
 * Tells PostHog when a workspace connects an app. Only the connector key and
 * the credential scope are sent: never the account label or anything stored
 * in `data`.
 */
class ConnectorCredentialObserver
{
    public function created(ConnectorCredential $connectorCredential): void
    {
        app(ProductAnalytics::class)->capture(
            $connectorCredential->created_by !== null ? (string) $connectorCredential->created_by : null,
            'connector_connected',
            [
                'connector' => $connectorCredential->connector?->key,
                'scope' => $connectorCredential->scope->value,
            ],
            (string) $connectorCredential->workspace_id,
        );
    }
}
