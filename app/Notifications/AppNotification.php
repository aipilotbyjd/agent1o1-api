<?php

namespace App\Notifications;

use App\Enums\Notifications\NotificationType;
use App\Enums\Notifications\NotificationSeverity;
use App\Enums\Queue;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Notifications\Channels\DigestChannel;
use App\Notifications\Channels\InboxChannel;
use App\Services\Notifications\DeliveryGate;
use App\Services\Notifications\IntegrationMessage;
use App\Services\Notifications\NotificationLinks;
use App\Services\Notifications\PreferenceResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Symfony\Component\Mime\Email;

/**
 * The base of every member-facing notification. A subclass supplies content
 * only (title, body, data, and optionally a richer email or chat message);
 * everything else comes from the event's `NotificationDefinition` and the
 * member's preferences:
 *
 * - channels: `via()` asks `PreferenceResolver`, so no subclass picks its own;
 * - the inbox row, its grouping and its realtime push: `InboxChannel`;
 * - email: a consistent layout with the event's button, why the member got
 *   it, a link to their settings and one-click unsubscribe;
 * - daily digests: `DigestChannel`, when the member chose them;
 * - chat and webhook channels: `toIntegration()`, routed once per event by
 *   `IntegrationRouter` rather than once per recipient;
 * - de-duplication: `idempotencyKey()` and `groupKey()`, enforced by
 *   `DeliveryGate`.
 *
 * Send through `NotificationDispatcher`, never `notify()` directly: that is
 * what routes chat and webhook messages and loads preferences in bulk.
 */
abstract class AppNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The workspace the notification is about, when it is about one. Account
     * notifications (assistant, security, platform messages) may still name
     * the workspace they came from by id, for links, without loading it.
     */
    public readonly ?Workspace $workspace;

    public readonly ?string $workspaceId;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly NotificationType $type,
        public readonly string $title,
        public readonly ?string $body = null,
        public readonly array $data = [],
        Workspace|string|null $workspace = null,
    ) {
        $this->workspace = $workspace instanceof Workspace ? $workspace : null;
        $this->workspaceId = $workspace instanceof Workspace ? (string) $workspace->getKey() : $workspace;

        $this->onQueue(Queue::Notification->value);
        $this->afterCommit();
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if (! $notifiable instanceof User) {
            return $this->type->definition()->defaultEmail ? ['mail'] : [];
        }

        $preference = app(PreferenceResolver::class)->resolve($notifiable, $this->type, $this->workspaceId);

        $channels = [];

        if ($preference->inApp) {
            $channels[] = InboxChannel::class;
        }

        if ($preference->email) {
            $channels[] = $preference->sendsDigest() ? DigestChannel::class : 'mail';
        }

        return $channels;
    }

    /**
     * Outbound channels are de-duplicated per member; the inbox de-duplicates
     * on its own row.
     */
    public function shouldSend(object $notifiable, string $channel): bool
    {
        if (! in_array($channel, ['mail', DigestChannel::class], true)) {
            return true;
        }

        $recipient = $notifiable instanceof User ? (string) $notifiable->getKey() : 'anonymous';

        return app(DeliveryGate::class)->allows($this, "{$channel}:{$recipient}");
    }

    /**
     * Repeat sends of this notification with the same key reach each member
     * and channel once (`config('notifications.idempotency_hours')`). Null
     * means every send is new.
     */
    public function idempotencyKey(): ?string
    {
        return null;
    }

    /**
     * What repeats of this event fold into, from its definition's grouping
     * and its data. Null when the event isn't grouped or the grouped-by
     * value is missing.
     */
    public function groupKey(): ?string
    {
        $grouping = $this->type->definition()->grouping;

        if ($grouping === null) {
            return null;
        }

        $scope = $this->workspaceId ?? 'account';

        if ($grouping->by === null) {
            return "{$this->type->value}:{$scope}";
        }

        $value = $this->data[$grouping->by] ?? null;

        return is_scalar($value) && $value !== '' ? "{$this->type->value}:{$scope}:{$value}" : null;
    }

    public function severity(): NotificationSeverity
    {
        return $this->type->definition()->severity;
    }

    /**
     * Where following the notification leads, inside the app.
     */
    public function actionPath(): ?string
    {
        return $this->type->actionPath($this->workspaceId, $this->data);
    }

    public function actionUrl(): ?string
    {
        return NotificationLinks::absolute($this->actionPath());
    }

    /**
     * The stored inbox payload. The resource reads `title`, `body`, `data`
     * and `workspace_id` from here; the columns `InboxChannel` writes beside
     * it carry the rest.
     *
     * @return array{workspace_id: string|null, type: string, title: string, body: string|null, data: array<string, mixed>}
     */
    public function toInbox(object $notifiable): array
    {
        return [
            'workspace_id' => $this->workspaceId,
            'type' => $this->type->value,
            'title' => $this->title,
            'body' => $this->body,
            'data' => $this->data,
        ];
    }

    /**
     * Laravel's own `database` channel and `toArray()` callers get the same
     * payload as the inbox.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->toInbox($notifiable);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = $this->mailMessage($notifiable);

        foreach (preg_split('/\R/', $this->body ?? '') ?: [] as $line) {
            if (trim($line) !== '') {
                $mail->line($line);
            }
        }

        return $this->withAction($mail);
    }

    /**
     * What a chat or webhook channel receives. Override to add Slack blocks.
     */
    public function toIntegration(): IntegrationMessage
    {
        return new IntegrationMessage(
            title: $this->title,
            body: $this->body,
            actionUrl: $this->actionUrl(),
            actionLabel: $this->type->actionLabel($this->data),
        );
    }

    /**
     * The email every subclass starts from: subject, urgency, footer and
     * unsubscribe headers. Subclasses with a richer email build on it.
     */
    protected function mailMessage(object $notifiable): MailMessage
    {
        $definition = $this->type->definition();

        $mail = (new MailMessage)
            ->subject($this->title)
            ->level($definition->severity === NotificationSeverity::Critical ? 'error' : 'info')
            ->salutation($this->footer($notifiable));

        $unsubscribeUrl = $notifiable instanceof User && ! $definition->mandatory
            ? NotificationLinks::oneClickUnsubscribe($notifiable, $this->type, $this->workspaceId)
            : null;

        if ($unsubscribeUrl !== null) {
            $mail->withSymfonyMessage(function (Email $message) use ($unsubscribeUrl): void {
                $message->getHeaders()
                    ->addTextHeader('List-Unsubscribe', "<{$unsubscribeUrl}>")
                    ->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
            });
        }

        return $mail;
    }

    /**
     * Adds the event's button, when it has somewhere to go.
     */
    protected function withAction(MailMessage $mail): MailMessage
    {
        $url = $this->actionUrl();
        $label = $this->type->actionLabel($this->data);

        return $url !== null && $label !== null ? $mail->action($label, $url) : $mail;
    }

    private function footer(object $notifiable): string
    {
        $settingsUrl = NotificationLinks::settings($this->workspaceId);
        $reason = $this->type->definition()->mandatory
            ? 'This message is about your account, so it is always sent.'
            : "You're getting this because \"{$this->type->label()}\" email is on in your notification settings.";

        return $settingsUrl !== null ? "{$reason} [Manage notification settings]({$settingsUrl})" : $reason;
    }
}
