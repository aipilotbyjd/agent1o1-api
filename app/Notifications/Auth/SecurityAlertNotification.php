<?php

namespace App\Notifications\Auth;

use App\Enums\Auth\AuthEvent;
use App\Enums\Notifications\NotificationEvent;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Services\Timezones\Timezones;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The single email behind every security alert, keyed by the `AuthEvent` that
 * triggered it: one class rather than one per event, the same shape
 * `AdminAlertNotification` uses, so adding an alert only means flipping
 * `AuthEvent::notifiesUser()`.
 *
 * The time, IP and user agent are captured when the event is recorded, not
 * when a worker picks the job up, because by then the request is long gone.
 * The time is shown in the member's own timezone.
 *
 * `NotificationEvent::SecurityAlert` is mandatory, so it always reaches the
 * member's email and bell whatever their preferences.
 */
class SecurityAlertNotification extends AppNotification
{
    public readonly CarbonImmutable $occurredAt;

    public function __construct(
        public readonly AuthEvent $authEvent,
        public readonly ?string $ipAddress = null,
        public readonly ?string $userAgent = null,
    ) {
        $this->occurredAt = CarbonImmutable::now();

        parent::__construct(
            event: NotificationEvent::SecurityAlert,
            title: $authEvent->label(),
            body: $authEvent->remediation(),
            data: array_filter([
                'auth_event' => $authEvent->value,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ], fn (mixed $value): bool => $value !== null),
        );
    }

    private function when(object $notifiable): string
    {
        $timezone = $notifiable instanceof User ? $notifiable->timezoneIn($notifiable->currentWorkspace) : Timezones::DEFAULT;

        return $this->occurredAt->setTimezone($timezone)->toDayDateTimeString().' '.$timezone;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = $this->mailMessage($notifiable)
            ->subject($this->authEvent->label().' - '.config('app.name'))
            ->greeting('Security alert')
            ->line($this->authEvent->label().' on your '.config('app.name').' account.')
            ->line('When: '.$this->when($notifiable));

        if ($this->ipAddress !== null) {
            $mail->line('IP address: '.$this->ipAddress);
        }

        if ($this->userAgent !== null) {
            $mail->line('Device: '.$this->userAgent);
        }

        return $mail
            ->line($this->authEvent->remediation())
            ->action('Review your sessions', config('app.frontend_url').'/settings/security');
    }
}
