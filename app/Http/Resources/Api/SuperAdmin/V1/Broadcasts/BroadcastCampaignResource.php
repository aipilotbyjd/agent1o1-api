<?php

namespace App\Http\Resources\Api\SuperAdmin\V1\Broadcasts;

use App\Models\Broadcasts\BroadcastCampaign;
use App\Services\Broadcasts\BroadcastAudience;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/**
 * A campaign. The detail view (`withEngagement()`) adds how many bells the
 * notification reached and how many of those were opened; lists leave the
 * counting out. A notification the member has since deleted counts in
 * neither, so the read rate is "of those still in an inbox".
 *
 * @mixin BroadcastCampaign
 */
class BroadcastCampaignResource extends JsonResource
{
    private bool $includeEngagement = false;

    public function withEngagement(): static
    {
        $this->includeEngagement = true;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $finished = $this->sent_count + $this->failed_count + $this->skipped_count;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category,
            'channel' => $this->channel,
            'subject' => $this->subject,
            'body_markdown' => $this->body_markdown,
            'notification_summary' => $this->notification_summary,
            'action_label' => $this->action_label,
            'action_url' => $this->action_url,
            'audience' => app(BroadcastAudience::class)->normalize($this->audience),
            'status' => $this->status,
            'scheduled_at' => $this->scheduled_at,
            'started_at' => $this->started_at,
            'finished_at' => $this->finished_at,
            'recalled_at' => $this->recalled_at,
            'recipient_count' => $this->recipient_count,
            'sent_count' => $this->sent_count,
            'failed_count' => $this->failed_count,
            'skipped_count' => $this->skipped_count,
            'progress' => $this->recipient_count > 0 ? round($finished / $this->recipient_count * 100, 1) : 0.0,
            'failure_reason' => $this->failure_reason,
            $this->mergeWhen($this->includeEngagement, fn (): array => $this->engagement()),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * @return array{notified_count: int, read_count: int}
     */
    private function engagement(): array
    {
        if (! $this->channel->includesInApp()) {
            return ['notified_count' => 0, 'read_count' => 0];
        }

        return [
            'notified_count' => $this->recipients()->whereNotNull('notified_at')->count(),
            'read_count' => DatabaseNotification::query()
                ->whereIn('id', $this->recipients()->whereNotNull('notification_id')->select('notification_id'))
                ->whereNotNull('read_at')
                ->count(),
        ];
    }
}
