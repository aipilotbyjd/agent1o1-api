<?php

namespace App\Http\Resources\Api\SuperAdmin\V1\Broadcasts;

use App\Models\Broadcasts\Announcement;
use App\Services\Broadcasts\BroadcastAudience;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An announcement as a super admin sees it: the audience in its full shape,
 * the derived `status`, and how many members have dismissed it.
 *
 * @mixin Announcement
 */
class AnnouncementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'severity' => $this->severity,
            'title' => $this->title,
            'body' => $this->body,
            'cta_label' => $this->cta_label,
            'cta_url' => $this->cta_url,
            'dismissible' => $this->dismissible,
            'blocks_writes' => $this->blocks_writes,
            'audience' => app(BroadcastAudience::class)->normalize($this->audience, 'all'),
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'published_at' => $this->published_at,
            'status' => $this->status(),
            'dismissals_count' => $this->whenCounted('dismissals'),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
