<?php

namespace App\Notifications\Ai;

use App\Enums\Notifications\NotificationType;
use App\Models\Workspaces\Workspace;
use App\Notifications\Channels\InboxChannel;
use App\Notifications\AppNotification;

/**
 * A super admin turned customer keys for a provider off, or back on, and
 * chose to tell the workspaces that have a key for it. Unlike other
 * workspace events, the super admin decides the channels for this one
 * notice (in-app only, or in-app and email) instead of each member's
 * notification preferences, which is why the event isn't configurable.
 */
class AiProviderAvailabilityNotification extends AppNotification
{
    public function __construct(
        Workspace $workspace,
        string $providerLabel,
        bool $available,
        ?string $message,
        private readonly bool $sendEmail,
    ) {
        parent::__construct(
            workspace: $workspace,
            type: NotificationType::AiProviderAvailabilityChanged,
            title: $available ? "{$providerLabel} is available again" : "{$providerLabel} is turned off for now",
            body: $available
                ? "Your {$providerLabel} keys are being used again for AI calls. Nothing to do on your side."
                : trim(($message ?? '')." Your {$providerLabel} keys are kept, not deleted, and will be used again once it's back. Until then, AI calls that relied on them use your other keys, or the platform's where your workspace allows it."),
            data: ['available' => $available],
        );
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return $this->sendEmail ? [InboxChannel::class, 'mail'] : [InboxChannel::class];
    }
}
