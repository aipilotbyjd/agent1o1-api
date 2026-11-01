<?php

namespace App\Http\Resources\Api\SuperAdmin\V1\Broadcasts;

use App\Models\Broadcasts\BroadcastCampaign;
use App\Services\Broadcasts\BroadcastAudience;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BroadcastCampaign
 */
class BroadcastCampaignResource extends JsonResource
{
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
            'subject' => $this->subject,
            'body_markdown' => $this->body_markdown,
            'audience' => app(BroadcastAudience::class)->normalize($this->audience),
            'status' => $this->status,
            'scheduled_at' => $this->scheduled_at,
            'started_at' => $this->started_at,
            'finished_at' => $this->finished_at,
            'recipient_count' => $this->recipient_count,
            'sent_count' => $this->sent_count,
            'failed_count' => $this->failed_count,
            'skipped_count' => $this->skipped_count,
            'progress' => $this->recipient_count > 0 ? round($finished / $this->recipient_count * 100, 1) : 0.0,
            'failure_reason' => $this->failure_reason,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
