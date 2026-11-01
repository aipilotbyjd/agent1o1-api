<?php

namespace App\Notifications\Workflows;

use App\Enums\Notifications\NotificationEvent;
use App\Models\Workflows\WorkflowPublishRequest;
use App\Notifications\Workspace\WorkspaceEventNotification;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Someone asked for a workflow change to be reviewed before it goes live:
 * sent to the workspace's other admins and owners
 * (`PublishRequests::approvers()`).
 */
class PublishReviewRequestedNotification extends WorkspaceEventNotification
{
    public function __construct(private readonly WorkflowPublishRequest $publishRequest)
    {
        $workflow = $publishRequest->workflow;
        $requester = $publishRequest->requester?->name ?? 'Someone';

        parent::__construct(
            workspace: $publishRequest->workspace,
            event: NotificationEvent::PublishReviewRequested,
            title: "{$requester} asked you to review changes to {$workflow->name}",
            body: $publishRequest->notes,
            data: [
                'workflow_id' => $workflow->id,
                'publish_request_id' => $publishRequest->id,
            ],
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title)
            ->line('Nothing goes live until someone approves it.');

        if (filled($this->publishRequest->notes)) {
            $mail->line('What changed: '.$this->publishRequest->notes);
        }

        return $mail->action('Review the changes', PublishRequestLink::to($this->publishRequest));
    }
}
