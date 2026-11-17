<?php

namespace App\Notifications\Assistant;

use App\Enums\Notifications\NotificationEvent;
use App\Models\Assistant\AssistantBriefingRun;
use App\Notifications\AppNotification;
use App\Services\Notifications\NotificationLinks;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

/**
 * A Meeting Prep brief, ahead of the meeting: by email (on by default) and
 * optionally in the bell.
 */
class MeetingBriefNotification extends AppNotification
{
    public function __construct(
        public readonly AssistantBriefingRun $run,
        string $title,
    ) {
        $meetingTitle = $run->meeting?->title;

        parent::__construct(
            event: NotificationEvent::AssistantMeetingBrief,
            title: $meetingTitle !== null ? "{$title}: {$meetingTitle}" : $title,
            body: $run->summary !== null ? Str::limit((string) $run->summary, 500) : null,
            data: [
                'assistant_briefing_run_id' => $run->id,
                'meeting_title' => $meetingTitle,
            ],
            workspaceId: $run->config->assistant->workspace_id,
        );
    }

    /**
     * One brief is sent once, however often its job runs.
     */
    public function idempotencyKey(): ?string
    {
        return 'meeting-brief:'.$this->run->id;
    }

    public function actionPath(): ?string
    {
        return '/'.rawurlencode((string) $this->workspaceId).'/assistant?tab=prep';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = $this->mailMessage($notifiable)->line((string) $this->run->summary);

        if (filled($this->run->document)) {
            $mail->line(Str::limit((string) $this->run->document, 3000));
        }

        return $mail->action('Open the brief', (string) NotificationLinks::absolute($this->actionPath()));
    }
}
