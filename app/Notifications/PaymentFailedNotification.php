<?php

namespace App\Notifications;

use App\Models\Workspace;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Workspace $workspace) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $billingUrl = config('app.frontend_url').'/settings/billing';

        return (new MailMessage)
            ->error()
            ->subject("Payment failed for {$this->workspace->name}")
            ->line("We were unable to process your payment for workspace **{$this->workspace->name}**.")
            ->line('Your subscription is now past due. Please update your payment method to avoid service interruption.')
            ->action('Update Payment Method', $billingUrl)
            ->line('If you have any questions, please contact our support team.');
    }
}
