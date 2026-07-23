<?php

namespace App\Listeners;

use App\Events\ExecutionCompletedEvent;
use App\Events\ExecutionFailedEvent;
use App\Models\NotificationChannel;
use App\Models\NotificationPreference;
use App\Models\Run;
use App\Models\User;
use App\Notifications\ExecutionCompletedNotification;
use App\Notifications\ExecutionFailedNotification;
use App\Services\NotificationService;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Log;
use Throwable;

class DispatchExecutionNotifications
{
    public function __construct(private readonly NotificationService $notificationService) {}

    public function handleFailed(ExecutionFailedEvent $event): void
    {
        $this->dispatch($event->execution, 'execution.failed');
    }

    public function handleCompleted(ExecutionCompletedEvent $event): void
    {
        $this->dispatch($event->execution, 'execution.completed');
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            ExecutionFailedEvent::class => 'handleFailed',
            ExecutionCompletedEvent::class => 'handleCompleted',
        ];
    }

    private function dispatch(Run $execution, string $eventKey): void
    {
        try {
            $execution->loadMissing('workflow');

            $preferences = NotificationPreference::where('workspace_id', $execution->workspace_id)
                ->where('event_key', $eventKey)
                ->get();

            if ($preferences->isEmpty()) {
                $this->notifyTriggeredByUser($execution, $eventKey);

                return;
            }

            $userIds = $preferences->pluck('user_id')->unique()->values();
            $users = User::whereIn('id', $userIds)->get()->keyBy('id');

            $channelIds = $preferences->pluck('channel_ids')->flatten()->filter()->unique()->values();
            $channels = $channelIds->isNotEmpty()
                ? NotificationChannel::whereIn('id', $channelIds)->where('is_active', true)->get()->keyBy('id')
                : collect();

            foreach ($preferences as $preference) {
                $user = $users->get($preference->user_id);
                if (! $user) {
                    continue;
                }

                if ($preference->in_app) {
                    $this->notificationService->notify(
                        user: $user,
                        type: $eventKey,
                        title: $this->buildTitle($execution, $eventKey),
                        body: $this->buildBody($execution, $eventKey),
                        data: ['execution_id' => $execution->id, 'workflow_id' => $execution->workflow_id],
                        workspace: $execution->workspace,
                    );
                }

                if ($preference->email) {
                    $notification = $eventKey === 'execution.failed'
                        ? new ExecutionFailedNotification($execution)
                        : new ExecutionCompletedNotification($execution);

                    $user->notify($notification);
                }

                foreach ((array) $preference->channel_ids as $channelId) {
                    $channel = $channels->get($channelId);
                    if ($channel) {
                        $this->notificationService->deliverToChannel(
                            channel: $channel,
                            message: $this->buildTitle($execution, $eventKey),
                            payload: ['execution_id' => $execution->id, 'workflow_id' => $execution->workflow_id],
                        );
                    }
                }
            }
        } catch (Throwable $e) {
            Log::warning('DispatchExecutionNotifications failed.', [
                'execution_id' => $execution->id,
                'event' => $eventKey,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Fall back to an in-app notification for whoever triggered the execution
     * when no workspace preferences are configured.
     */
    private function notifyTriggeredByUser(Run $execution, string $eventKey): void
    {
        if ($eventKey !== 'execution.failed' || ! $execution->triggered_by) {
            return;
        }

        $user = User::find($execution->triggered_by);
        if (! $user) {
            return;
        }

        $this->notificationService->notify(
            user: $user,
            type: $eventKey,
            title: $this->buildTitle($execution, $eventKey),
            body: $this->buildBody($execution, $eventKey),
            data: ['execution_id' => $execution->id, 'workflow_id' => $execution->workflow_id],
            workspace: $execution->workspace,
        );
    }

    private function buildTitle(Run $execution, string $eventKey): string
    {
        $workflowName = $execution->workflow?->name ?? 'Workflow';

        return $eventKey === 'execution.failed'
            ? "Execution failed: {$workflowName}"
            : "Execution completed: {$workflowName}";
    }

    private function buildBody(Run $execution, string $eventKey): ?string
    {
        if ($eventKey === 'execution.failed') {
            $error = $execution->error['message'] ?? 'An unexpected error occurred.';

            return "Error: {$error}";
        }

        $duration = $execution->duration_ms
            ? 'Completed in '.round($execution->duration_ms / 1000, 2).'s.'
            : 'Completed successfully.';

        return $duration;
    }
}
