<?php

namespace App\Notifications\Assistant;

use App\Enums\Queue;
use App\Models\Assistant\AssistantBriefingRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * The Daily report by email: the summary and catch-up, with a link to the
 * full report and its Situations in the app.
 */
class DailyReportNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly AssistantBriefingRun $run,
        public readonly string $title,
    ) {
        $this->onQueue(Queue::Notification->value);
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title.' - '.now()->toFormattedDayDateString())
            ->line((string) $this->run->summary);

        if (filled($this->run->document)) {
            $mail->line(Str::limit((string) $this->run->document, 3000));
        }

        $workspaceId = $this->run->config->assistant->workspace_id;

        return $mail->action('Open the full report', config('app.frontend_url')."/{$workspaceId}/assistant?tab=daily");
    }
}
