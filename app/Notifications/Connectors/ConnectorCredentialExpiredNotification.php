<?php

namespace App\Notifications\Connectors;

use App\Enums\Notifications\NotificationType;
use App\Models\Connectors\ConnectorCredential;
use App\Notifications\AppNotification;

class ConnectorCredentialExpiredNotification extends AppNotification
{
    public function __construct(ConnectorCredential $credential)
    {
        parent::__construct(
            workspace: $credential->workspace,
            type: NotificationType::ConnectorCredentialExpired,
            title: "\"{$credential->name}\" needs to be reconnected",
            body: "The {$credential->connector->name} connector credential \"{$credential->name}\" expired and could not be automatically refreshed. Reconnect it to keep workflows using it running.",
            data: [
                'connector_credential_id' => $credential->id,
                'connector_key' => $credential->connector->key,
            ],
        );
    }
}
