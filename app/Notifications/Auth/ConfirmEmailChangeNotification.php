<?php

namespace App\Notifications\Auth;

use App\Enums\Queue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the *new* address when a user asks to change their email, and routed
 * on demand rather than through the user's own notification routing: the
 * account still points at the old address until this link is followed, which is
 * the whole point of confirming it.
 */
class ConfirmEmailChangeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $confirmUrl,
        public readonly string $currentEmail,
    ) {
        $this->onQueue(Queue::Notification->value);
        $this->afterCommit();
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Confirm your new email address: '.config('app.name'))
            ->line('Your '.config('app.name').' account, currently using '.$this->currentEmail.', has been asked to move to this address.')
            ->action('Confirm this address', $this->confirmUrl)
            ->line('The link expires in 60 minutes. Until you follow it, the account keeps its current address.')
            ->line('If you did not request this, ignore this email: nothing changes.');
    }
}
