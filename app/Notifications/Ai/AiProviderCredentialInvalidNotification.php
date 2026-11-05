<?php

namespace App\Notifications\Ai;

use App\Enums\Connectors\ConnectorCredentialScope;
use App\Enums\Notifications\NotificationEvent;
use App\Models\Ai\AiProviderCredential;
use App\Notifications\Workspace\WorkspaceEventNotification;

class AiProviderCredentialInvalidNotification extends WorkspaceEventNotification
{
    public function __construct(AiProviderCredential $credential, string $label)
    {
        $whose = $credential->scope === ConnectorCredentialScope::Personal ? 'Your personal' : 'The workspace\'s';
        $name = $credential->name ? " \"{$credential->name}\"" : '';
        // What happens to calls meanwhile depends on the workspace's key policy.
        $meanwhile = 'Until then, AI calls that relied on it use the platform\'s key (and credits) where the workspace allows it, or stop.';

        [$title, $body] = $credential->isOAuth()
            ? [
                "{$label} connection{$name} stopped working",
                "{$whose} {$label} connection expired and couldn't be renewed. Reconnect it in Settings → AI Providers. {$meanwhile}",
            ]
            : [
                "{$label} key{$name} stopped working",
                "{$whose} {$label} key ({$credential->key_hint}) was rejected by {$label}. Replace it in Settings → AI Providers. {$meanwhile}",
            ];

        parent::__construct(
            workspace: $credential->workspace,
            event: NotificationEvent::AiProviderCredentialInvalid,
            title: $title,
            body: $body,
            data: [
                'ai_provider_credential_id' => $credential->id,
                'execution_provider' => $credential->execution_provider,
            ],
        );
    }
}
