<?php

namespace App\Notifications\Workspace;

use App\Enums\Notifications\NotificationEvent;
use App\Enums\Queue;
use App\Models\Notifications\NotificationPreference;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Notifications\Channels\WorkspaceWebhookChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Base class for every workspace-scoped notification: centralizes channel
 * selection so concrete notifications (`MemberInvitedNotification`, etc.)
 * only ever supply title/body/data, never per-notification channel logic.
 * Ported from the old project's `WorkspaceEventNotification`.
 */
abstract class WorkspaceEventNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly Workspace $workspace,
        public readonly NotificationEvent $event,
        public readonly string $title,
        public readonly ?string $body = null,
        public readonly array $data = [],
    ) {
        $this->onQueue(Queue::Notification->value);
        $this->afterCommit();
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $preference = $this->preferenceFor($notifiable);

        $channels = [];

        if ($preference?->in_app ?? NotificationEvent::DEFAULT_IN_APP) {
            $channels[] = 'database';
            $channels[] = 'broadcast';
        }

        if ($preference?->email ?? NotificationEvent::DEFAULT_EMAIL) {
            $channels[] = 'mail';
        }

        if (! empty($preference?->channel_ids)) {
            $channels[] = WorkspaceWebhookChannel::class;
        }

        return $channels;
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'workspace_id' => $this->workspace->id,
            'type' => $this->event->value,
            'title' => $this->title,
            'body' => $this->body,
            'data' => $this->data,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'workspace_id' => $this->workspace->id,
            'type' => $this->event->value,
            'title' => $this->title,
            'body' => $this->body,
            'data' => $this->data,
            'created_at' => now()->toIso8601String(),
        ]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title)
            ->line($this->body ?? $this->title);
    }

    /**
     * @return array{workspace_id: int, channel_ids: array<int, int>, message: string}
     */
    public function toWorkspaceChannel(object $notifiable): array
    {
        $preference = $this->preferenceFor($notifiable);

        return [
            'workspace_id' => $this->workspace->id,
            'channel_ids' => $preference?->channel_ids ?? [],
            'message' => $this->body === null ? $this->title : "{$this->title}: {$this->body}",
        ];
    }

    private function preferenceFor(object $notifiable): ?NotificationPreference
    {
        if (! $notifiable instanceof User) {
            return null;
        }

        return NotificationPreference::query()
            ->where('workspace_id', $this->workspace->id)
            ->where('user_id', $notifiable->id)
            ->where('event_key', $this->event->value)
            ->first();
    }
}
