<?php

namespace App\Notifications\Auth;

use App\Enums\Auth\AuthEvent;
use App\Enums\Queue;
use App\Models\User;
use App\Services\Timezones\Timezones;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The single email behind every security alert, keyed by the `AuthEvent` that
 * triggered it: one class rather than one per event, the same shape
 * `AdminAlertNotification` uses, so adding an alert only means flipping
 * `AuthEvent::notifiesUser()`.
 *
 * The time, IP and user agent are captured when the event is recorded, not
 * when a worker picks the job up, because by then the request is long gone.
 * The time is shown in the member's own timezone.
 */
class SecurityAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public readonly CarbonImmutable $occurredAt;

    public function __construct(
        public readonly AuthEvent $event,
        public readonly ?string $ipAddress = null,
        public readonly ?string $userAgent = null,
    ) {
        $this->occurredAt = CarbonImmutable::now();
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

    private function when(object $notifiable): string
    {
        $timezone = $notifiable instanceof User ? $notifiable->timezoneIn($notifiable->currentWorkspace) : Timezones::DEFAULT;

        return $this->occurredAt->setTimezone($timezone)->toDayDateTimeString().' '.$timezone;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->event->label().' - '.config('app.name'))
            ->greeting('Security alert')
            ->line($this->event->label().' on your '.config('app.name').' account.')
            ->line('When: '.$this->when($notifiable));

        if ($this->ipAddress !== null) {
            $mail->line('IP address: '.$this->ipAddress);
        }

        if ($this->userAgent !== null) {
            $mail->line('Device: '.$this->userAgent);
        }

        return $mail
            ->line($this->event->remediation())
            ->action('Review your sessions', config('app.frontend_url').'/settings/security');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'event' => $this->event->value,
            'label' => $this->event->label(),
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
        ];
    }
}
