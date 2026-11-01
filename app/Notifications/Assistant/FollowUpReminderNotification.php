<?php

namespace App\Notifications\Assistant;

use App\Enums\Queue;
use App\Models\Assistant\AssistantFollowUp;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Nobody has answered an email thread the owner asked to follow up on.
 */
class FollowUpReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly AssistantFollowUp $followUp)
    {
        $this->onQueue(Queue::Notification->value);
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * The in-app notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'assistant_follow_up',
            'follow_up_id' => $this->followUp->id,
            'workspace_id' => $this->followUp->assistant->workspace_id,
            'subject' => $this->followUp->subject,
            'message' => 'Nobody has replied to "'.($this->followUp->subject ?: 'an email thread').'" yet.',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = filled($this->followUp->subject) ? $this->followUp->subject : 'an email thread';

        $mail = (new MailMessage)
            ->subject("No reply yet: {$subject}")
            ->line("Nobody has answered \"{$subject}\" since you asked to follow up.");

        if (filled($this->followUp->note)) {
            $mail->line("Your note: {$this->followUp->note}");
        }

        return $mail->action('Open the assistant', config('app.frontend_url')."/{$this->followUp->assistant->workspace_id}/assistant");
    }
}
