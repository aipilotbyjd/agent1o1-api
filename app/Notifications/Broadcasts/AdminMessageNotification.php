<?php

namespace App\Notifications\Broadcasts;

use App\Enums\Notifications\NotificationEvent;
use App\Models\Broadcasts\BroadcastCampaign;
use App\Notifications\AppNotification;
use Ramsey\Uuid\Uuid;

/**
 * A campaign's message in a member's notification bell
 * (`NotificationEvent::PlatformMessage`). It belongs to the account, not a
 * workspace, so `workspace_id` is null. The event is mandatory: whether a
 * member gets marketing at all is decided by the marketing opt-out when the
 * campaign's audience is built, not by notification preferences.
 *
 * Sent with `notifyNow()` from `DeliverBroadcastRecipientJob`, which already
 * runs on the broadcast queue. The id is derived from the campaign and the
 * member, so a retried delivery collides with the row it already wrote
 * rather than adding a second copy, and a recall knows exactly what to delete.
 */
class AdminMessageNotification extends AppNotification
{
    public const TYPE = 'admin.message';

    public function __construct(
        public readonly BroadcastCampaign $campaign,
        public readonly bool $isTest = false,
    ) {
        parent::__construct(
            event: NotificationEvent::PlatformMessage,
            title: ($isTest ? '[TEST] ' : '').$campaign->subject,
            body: $campaign->notification_summary,
            data: [
                'campaign_id' => $campaign->id,
                'category' => $campaign->category->value,
                'action_label' => $campaign->action_label,
                'action_url' => $campaign->action_url,
            ],
        );
    }

    /**
     * The notification id one campaign gives one member.
     */
    public static function idFor(string $campaignId, string $userId): string
    {
        return Uuid::uuid5(Uuid::NAMESPACE_URL, "broadcast-campaign:{$campaignId}:user:{$userId}")->toString();
    }
}
