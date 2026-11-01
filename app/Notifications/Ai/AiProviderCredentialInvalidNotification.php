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

        parent::__construct(
            workspace: $credential->workspace,
            event: NotificationEvent::AiProviderCredentialInvalid,
            title: "{$label} key{$name} stopped working",
            body: "{$whose} {$label} key ({$credential->key_hint}) was rejected by {$label}, so AI calls are back on the platform's key and use credits. Replace the key in Settings → AI Providers to keep using your own.",
            data: [
                'ai_provider_credential_id' => $credential->id,
                'execution_provider' => $credential->execution_provider,
            ],
        );
    }
}
