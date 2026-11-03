<?php

namespace App\Listeners\Agents;

use App\Enums\Agents\ActionToolKind;
use App\Events\Agents\AgentActionsRequested;
use App\Models\Agents\AgentAction;
use App\Notifications\Agents\AgentActionApprovalRequestedNotification;
use App\Services\Agents\Approvals\ActionApprovers;
use App\Services\Notifications\NotificationDispatcher;

/**
 * Tells the people who may decide a paused turn's actions that it's waiting,
 * in the app, and by email or chat per their notification preferences.
 */
class NotifyAgentActionApprovers
{
    public function __construct(
        private readonly ActionApprovers $approvers,
        private readonly NotificationDispatcher $notifications,
    ) {}

    public function handle(AgentActionsRequested $event): void
    {
        // A question the agent asked is answered in the chat it was asked
        // in, by the person there: it is not an approval request.
        $actions = $event->actions->reject(fn (AgentAction $action): bool => $action->tool_kind === ActionToolKind::Question)->values();

        if ($actions->isEmpty()) {
            return;
        }

        $actions->loadMissing('session');

        foreach ($this->approvers->notificationsFor($event->session->workspace, $actions) as $entry) {
            $this->notifications->dispatch(
                [$entry['user']],
                new AgentActionApprovalRequestedNotification($event->session, $entry['actions']),
            );
        }
    }
}
