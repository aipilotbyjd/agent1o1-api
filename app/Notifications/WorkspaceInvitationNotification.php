<?php

namespace App\Notifications;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkspaceInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Invitation $invitation,
        private readonly string $rawToken,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $acceptUrl = config('app.frontend_url').'/invitations/'.$this->rawToken.'/accept';
        $declineUrl = config('app.frontend_url').'/invitations/'.$this->rawToken.'/decline';

        $mail = (new MailMessage)
            ->subject('You have been invited to join '.$this->invitation->workspace->name)
            ->line('You have been invited to join **'.$this->invitation->workspace->name.'** as **'.$this->invitation->role->value.'**.');

        if ($this->invitation->personal_note) {
            $mail->line('> '.$this->invitation->personal_note);
        }

        return $mail
            ->action('Accept Invitation', $acceptUrl)
            ->line('Or decline: '.$declineUrl)
            ->line('This invitation expires in 7 days.');
    }
}
