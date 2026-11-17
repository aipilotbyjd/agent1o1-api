<?php

namespace App\Notifications\Assistant;

use App\Enums\Notifications\NotificationEvent;
use App\Models\Assistant\AssistantTrigger;
use App\Notifications\AppNotification;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * One of a member's assistant triggers failed too many times in a row and
 * was switched off. Sent to the assistant's owner only; workflow triggers
 * have their own `Triggers\TriggerDisabledNotification` for the workspace.
 */
class AssistantTriggerDisabledNotification extends AppNotification
{
    public function __construct(
        public readonly AssistantTrigger $trigger,
        public readonly string $lastError,
    ) {
        parent::__construct(
            event: NotificationEvent::AssistantTriggerDisabled,
            title: "Trigger \"{$trigger->name}\" was switched off",
            body: "It failed ".config('assistant.triggers.max_consecutive_failures').' times in a row. Last error: '.($lastError ?: 'unknown')
                .'. A common cause is an app connection that expired. Fix it, then turn the trigger back on.',
            data: [
                'assistant_trigger_id' => $trigger->id,
                'assistant_id' => $trigger->assistant_id,
                'error' => $lastError,
            ],
            workspaceId: $trigger->assistant->workspace_id,
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->withAction(
            $this->mailMessage($notifiable)
                ->line("Your trigger \"{$this->trigger->name}\" failed ".config('assistant.triggers.max_consecutive_failures').' times in a row, so it was switched off.')
                ->line('Last error: '.($this->lastError ?: 'unknown'))
                ->line('A common cause is an app connection that expired. Fix it, then turn the trigger back on.'),
        );
    }
}
