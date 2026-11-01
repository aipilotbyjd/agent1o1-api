<?php

namespace App\Notifications\SuperAdmin;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A security alert about the Super Admin panel, sent to the fixed recipients
 * in `config/super_admin_alerts.php`. Not queued: an alert about the queue or
 * database being under attack should not depend on either.
 */
class SuperAdminAlertNotification extends Notification
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly string $event,
        public readonly string $summary,
        public readonly array $context = [],
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('[Super Admin] '.$this->summary)
            ->line($this->summary)
            ->line('Event: '.$this->event);

        foreach ($this->context as $key => $value) {
            $message->line($key.': '.(is_scalar($value) || $value === null ? (string) $value : json_encode($value)));
        }

        return $message;
    }
}
