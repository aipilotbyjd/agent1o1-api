<?php

namespace App\Notifications;

use App\Models\Credential;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CredentialExpiredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Credential $credential) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->error()
            ->subject("Credential expired: {$this->credential->name}")
            ->line("The OAuth credential **{$this->credential->name}** could not be refreshed and has expired.")
            ->line('Workflows and agents using this credential will fail until it is reconnected.')
            ->action('Reconnect Credential', config('app.frontend_url')."/credentials/{$this->credential->id}")
            ->line('Please reconnect the credential to restore access.');
    }
}
