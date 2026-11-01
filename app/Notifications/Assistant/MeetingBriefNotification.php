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
 * A Meeting Prep brief by email, ahead of the meeting.
 */
class MeetingBriefNotification extends Notification implements ShouldQueue
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
        $meeting = $this->run->meeting;

        $mail = (new MailMessage)
            ->subject("{$this->title}: {$meeting?->title}")
            ->line((string) $this->run->summary);

        if (filled($this->run->document)) {
            $mail->line(Str::limit((string) $this->run->document, 3000));
        }

        $workspaceId = $this->run->config->assistant->workspace_id;

        return $mail->action('Open the brief', config('app.frontend_url')."/{$workspaceId}/assistant?tab=prep");
    }
}
