<?php

namespace App\Notifications\Workflows;

use App\Enums\Notifications\NotificationType;
use App\Enums\Workflows\PublishRequestStatus;
use App\Models\Workflows\WorkflowPublishRequest;
use App\Notifications\AppNotification;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Tells the person who asked for a review how it went: approved and live, or
 * rejected with the reviewer's reason.
 */
class PublishRequestDecidedNotification extends AppNotification
{
    public function __construct(private readonly WorkflowPublishRequest $publishRequest)
    {
        $workflow = $publishRequest->workflow;
        $reviewer = $publishRequest->reviewer?->name ?? 'A reviewer';
        $approved = $publishRequest->status === PublishRequestStatus::Approved;
        $version = $publishRequest->publishedVersion?->version;

        parent::__construct(
            workspace: $publishRequest->workspace,
            type: NotificationType::PublishRequestDecided,
            title: $approved
                ? "{$reviewer} approved your changes to {$workflow->name}. v{$version} is live"
                : "{$reviewer} rejected your changes to {$workflow->name}",
            body: $publishRequest->review_notes,
            data: [
                'workflow_id' => $workflow->id,
                'publish_request_id' => $publishRequest->id,
                'status' => $publishRequest->status->value,
                'published_version_id' => $publishRequest->published_version_id,
            ],
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = $this->mailMessage($notifiable);

        if (filled($this->publishRequest->review_notes)) {
            $mail->line('Reviewer note: '.$this->publishRequest->review_notes);
        }

        return $mail->action('Open the workflow', PublishRequestLink::to($this->publishRequest));
    }
}
