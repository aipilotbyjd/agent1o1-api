<?php

namespace App\Notifications\Assistant;

use App\Enums\Notifications\NotificationEvent;
use App\Models\Assistant\AssistantFollowUp;
use App\Notifications\AppNotification;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Nobody has answered an email thread the owner asked to follow up on.
 */
class FollowUpReminderNotification extends AppNotification
{
    public function __construct(public readonly AssistantFollowUp $followUp)
    {
        $subject = filled($followUp->subject) ? $followUp->subject : 'an email thread';

        parent::__construct(
            event: NotificationEvent::AssistantFollowUp,
            title: "No reply yet: {$subject}",
            body: "Nobody has replied to \"{$subject}\" yet.",
            data: array_filter([
                'follow_up_id' => $followUp->id,
                'subject' => $followUp->subject,
            ], fn (mixed $value): bool => $value !== null),
            workspaceId: $followUp->assistant->workspace_id,
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = filled($this->followUp->subject) ? $this->followUp->subject : 'an email thread';

        $mail = $this->mailMessage($notifiable)
            ->line("Nobody has answered \"{$subject}\" since you asked to follow up.");

        if (filled($this->followUp->note)) {
            $mail->line("Your note: {$this->followUp->note}");
        }

        return $this->withAction($mail);
    }
}
