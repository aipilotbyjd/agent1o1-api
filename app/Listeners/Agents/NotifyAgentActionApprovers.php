<?php

namespace App\Listeners\Agents;

use App\Events\Agents\AgentActionsRequested;
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
        if ($event->actions->isEmpty()) {
            return;
        }

        $event->actions->loadMissing('session');

        foreach ($this->approvers->notificationsFor($event->session->workspace, $event->actions) as $entry) {
            $this->notifications->dispatch(
                [$entry['user']],
                new AgentActionApprovalRequestedNotification($event->session, $entry['actions']),
            );
        }
    }
}
