<?php

namespace App\Services\Ai;

use App\Enums\Connectors\ConnectorCredentialScope;
use App\Models\Ai\AiProviderCredential;
use App\Notifications\Ai\AiProviderCredentialInvalidNotification;
use App\Services\Notifications\NotificationDispatcher;

/**
 * Tells whoever relies on a workspace's own AI provider key that it has
 * stopped working: its owner for a personal key, the owners/admins (and
 * whoever added it) for a team key.
 */
class AiProviderCredentialAlerts
{
    public function __construct(
        private readonly NotificationDispatcher $notifications,
        private readonly AiProviderKeyChecker $checker,
    ) {}

    public function stoppedWorking(AiProviderCredential $credential): void
    {
        $credential->loadMissing(['workspace', 'creator']);

        $recipients = $credential->scope === ConnectorCredentialScope::Personal
            ? collect([$credential->creator])->filter()
            : $this->notifications->ownersAndAdmins($credential->workspace)
                ->push($credential->creator)
                ->filter()
                ->unique('id');

        $this->notifications->dispatch($recipients, new AiProviderCredentialInvalidNotification(
            $credential,
            $this->checker->label($credential->execution_provider),
        ));
    }
}
