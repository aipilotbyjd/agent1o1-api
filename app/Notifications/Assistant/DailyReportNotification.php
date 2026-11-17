<?php

namespace App\Notifications\Assistant;

use App\Enums\Notifications\NotificationEvent;
use App\Models\Assistant\AssistantBriefingRun;
use App\Notifications\AppNotification;
use App\Services\Notifications\NotificationLinks;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

/**
 * The Daily report: by email (on by default) with the summary and catch-up,
 * and optionally in the bell, linking to the full report and its Situations.
 */
class DailyReportNotification extends AppNotification
{
    public function __construct(
        public readonly AssistantBriefingRun $run,
        string $title,
    ) {
        parent::__construct(
            event: NotificationEvent::AssistantDailyReport,
            title: $title,
            body: $run->summary !== null ? Str::limit((string) $run->summary, 500) : null,
            data: ['assistant_briefing_run_id' => $run->id],
            workspaceId: $run->config->assistant->workspace_id,
        );
    }

    /**
     * One report is sent once, however often its job runs.
     */
    public function idempotencyKey(): ?string
    {
        return 'daily-report:'.$this->run->id;
    }

    public function actionPath(): ?string
    {
        return '/'.rawurlencode((string) $this->workspaceId).'/assistant?tab=daily';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = $this->mailMessage($notifiable)
            ->subject($this->title.' - '.now()->toFormattedDayDateString())
            ->line((string) $this->run->summary);

        if (filled($this->run->document)) {
            $mail->line(Str::limit((string) $this->run->document, 3000));
        }

        return $mail->action('Open the full report', (string) NotificationLinks::absolute($this->actionPath()));
    }
}
