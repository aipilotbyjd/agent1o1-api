<?php

namespace App\Notifications\Account;

use App\Enums\Queue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * The daily digest: every email a member asked to receive in one go
 * (`DigestChannel`), sent by `notifications:send-digests`. It is the
 * delivery of notifications already filtered by preferences, so it is not
 * itself an `AppNotification`.
 */
class NotificationDigestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, array{title: string, body: string|null, action_url: string|null}>  $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $omitted = 0,
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
        $count = count($this->items) + $this->omitted;

        $mail = (new MailMessage)
            ->subject("Your daily summary: {$count} ".Str::plural('notification', $count))
            ->greeting('Here is what happened since your last summary.');

        foreach ($this->items as $item) {
            $line = "**{$item['title']}**";

            if (filled($item['body'])) {
                $line .= ' — '.Str::limit((string) $item['body'], 300);
            }

            if (filled($item['action_url'])) {
                $line .= " [Open]({$item['action_url']})";
            }

            $mail->line($line);
        }

        if ($this->omitted > 0) {
            $mail->line("And {$this->omitted} more in the app.");
        }

        return $mail->salutation('You get this summary because you chose a daily digest in your notification settings.');
    }
}
