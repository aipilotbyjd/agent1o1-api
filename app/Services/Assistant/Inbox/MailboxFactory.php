<?php

namespace App\Services\Assistant\Inbox;

use App\Models\Assistant\AssistantInboxConfig;
use RuntimeException;

/**
 * The mailbox client for an inbox's provider. Swapped in tests.
 */
class MailboxFactory
{
    public function for(AssistantInboxConfig $config): Mailbox
    {
        $credential = $config->credential ?? throw new RuntimeException('The connected mailbox was removed. Reconnect it in Apps.');

        return match ($config->provider) {
            'gmail' => new GmailMailbox($credential),
            'outlook' => new OutlookMailbox($credential),
            default => throw new RuntimeException("Unsupported mail provider [{$config->provider}]."),
        };
    }
}
