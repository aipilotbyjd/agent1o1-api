<?php

namespace App\Notifications\Admin;

use App\Enums\Notifications\AlertSeverity;
use App\Enums\Queue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The single notification behind every admin alert. Deliberately not modelled
 * as one class per alert: an alert is identified by the `$key` string
 * `AdminAlerts::raise()` was called with, so adding a new alert never means
 * adding a class.
 *
 * `$channels` and `$destinations` are resolved by `AdminAlerts` before the
 * notification is constructed rather than read from config inside `via()`:
 * the notification is queued, and routing must reflect the moment the alert
 * was raised, not the moment a worker happened to pick it up.
 */
class AdminAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $context
     * @param  array<int, string>  $channels
     * @param  array<int, array{type: string, url: string}>  $destinations
     */
    public function __construct(
        public readonly string $key,
        public readonly string $title,
        public readonly ?string $body,
        public readonly array $context,
        public readonly AlertSeverity $severity,
        public readonly array $channels,
        public readonly array $destinations,
    ) {
        $this->onQueue(Queue::Notification->value);
        $this->afterCommit();
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("[{$this->severity->label()}] {$this->title}")
            ->line($this->body ?? $this->title);

        foreach ($this->context as $name => $value) {
            $mail->line(static::describe($name).': '.static::stringify($value));
        }

        return $mail->line("Alert: {$this->key} · Environment: ".config('app.env'));
    }

    /**
     * Consumed by `AdminAlertChannel`, which fans it out to every configured
     * Discord/webhook destination.
     *
     * @return array{key: string, severity: string, title: string, body: string|null, context: array<string, mixed>, destinations: array<int, array{type: string, url: string}>}
     */
    public function toAdminAlert(object $notifiable): array
    {
        return [
            'key' => $this->key,
            'severity' => $this->severity->value,
            'title' => $this->title,
            'body' => $this->body,
            'context' => $this->context,
            'destinations' => $this->destinations,
        ];
    }

    /**
     * `workspace_id` reads as "Workspace id" in a Discord embed field.
     */
    public static function describe(string $key): string
    {
        return ucfirst(str_replace('_', ' ', $key));
    }

    public static function stringify(mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            $value === null => 'null',
            is_scalar($value) => (string) $value,
            default => json_encode($value) ?: '',
        };
    }
}
