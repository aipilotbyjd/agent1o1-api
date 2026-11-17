<?php

namespace App\Notifications\Broadcasts;

use App\Models\Broadcasts\BroadcastCampaign;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Ramsey\Uuid\Uuid;

/**
 * A campaign's message in a member's notification bell. It belongs to the
 * account, not a workspace, so `workspace_id` is null and it isn't in the
 * per-workspace `NotificationEvent` catalog: members can't switch it off, only
 * opt out of marketing.
 *
 * Not queued: `DeliverBroadcastRecipientJob` already runs on the broadcast
 * queue and sends it from there. The id is derived from the campaign and the
 * member, so a retried delivery collides with the row it already wrote rather
 * than adding a second copy, and a recall knows exactly what to delete.
 */
class AdminMessageNotification extends Notification
{
    public const TYPE = 'admin.message';

    public function __construct(
        public readonly BroadcastCampaign $campaign,
        public readonly bool $isTest = false,
    ) {}

    /**
     * The notification id one campaign gives one member.
     */
    public static function idFor(string $campaignId, string $userId): string
    {
        return Uuid::uuid5(Uuid::NAMESPACE_URL, "broadcast-campaign:{$campaignId}:user:{$userId}")->toString();
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return $this->payload();
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([...$this->payload(), 'created_at' => now()->toIso8601String()]);
    }

    /**
     * @return array{workspace_id: null, type: string, title: string, body: string|null, data: array{campaign_id: string, category: string, action_label: string|null, action_url: string|null}}
     */
    private function payload(): array
    {
        return [
            'workspace_id' => null,
            'type' => self::TYPE,
            'title' => ($this->isTest ? '[TEST] ' : '').$this->campaign->subject,
            'body' => $this->campaign->notification_summary,
            'data' => [
                'campaign_id' => $this->campaign->id,
                'category' => $this->campaign->category->value,
                'action_label' => $this->campaign->action_label,
                'action_url' => $this->campaign->action_url,
            ],
        ];
    }
}
