<?php

namespace App\Notifications\Account;

use App\Enums\Queue;
use App\Models\Account\DataExport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the account owner their personal data export has finished. The
 * email carries no download link of its own: the archive is fetched from
 * account settings while signed in, so a forwarded or leaked email does not
 * hand the data to someone else.
 */
class DataExportReadyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly DataExport $export)
    {
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
        $settingsUrl = rtrim((string) config('app.frontend_url'), '/').'/settings/profile';

        return (new MailMessage)
            ->subject('Your data export is ready: '.config('app.name'))
            ->line('The copy of your '.config('app.name').' account data you asked for is ready to download.')
            ->action('Download from account settings', $settingsUrl)
            ->line('For your security, the download is only available while signed in, and expires on '.$this->export->expires_at?->toFormattedDayDateString().'.')
            ->line('If you did not request this export, change your password and review your active sessions.');
    }
}
