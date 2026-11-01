<?php

namespace App\Http\Resources\Api\Internal\V1\Broadcasts;

use App\Models\Broadcasts\Announcement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An announcement as a member sees it. Never carries the audience: how a
 * banner was targeted is not the member's business.
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
            'blocks_writes' => $this->blocksWrites(),
            'window_open' => $this->isInWindow(),
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'published_at' => $this->published_at,
        ];
    }
}
