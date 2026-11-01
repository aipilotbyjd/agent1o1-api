<?php

namespace App\Notifications\Assistant;

use App\Enums\Queue;
use App\Models\Assistant\AssistantTrigger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TriggerDisabledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly AssistantTrigger $trigger,
        public readonly string $lastError,
    ) {
        $this->onQueue(Queue::Notification->value);
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $workspaceId = $this->trigger->assistant->workspace_id;

        return (new MailMessage)
            ->subject("Trigger \"{$this->trigger->name}\" was switched off")
            ->line("Your trigger \"{$this->trigger->name}\" failed ".config('assistant.triggers.max_consecutive_failures').' times in a row, so it was switched off.')
            ->line('Last error: '.($this->lastError ?: 'unknown'))
            ->line('A common cause is an app connection that expired. Fix it, then turn the trigger back on.')
            ->action('Open triggers', config('app.frontend_url')."/{$workspaceId}/assistant");
    }
}
