<?php

namespace App\Http\Resources\Api\SuperAdmin\V1\Changelog;

use App\Models\Changelog\ChangelogEntry;
use App\Services\Broadcasts\BroadcastAudience;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A changelog entry as a super admin sees it: every field, the audience in
 * its full shape, and how many members have read it.
 *
 * @mixin ChangelogEntry
 */
class ChangelogEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'version' => $this->version,
            'title' => $this->title,
            'summary' => $this->summary,
            'body' => $this->body,
            'body_html' => $this->bodyHtml(),
            'cover_image_url' => $this->cover_image_url,
            'category' => $this->category,
            'areas' => $this->areas ?? [],
            'visibility' => $this->visibility,
            'audience' => $this->audience === null ? null : app(BroadcastAudience::class)->normalize($this->audience, 'all'),
            'is_pinned' => $this->is_pinned,
            'is_major' => $this->is_major,
            'status' => $this->status,
            'published_at' => $this->published_at,
            'scheduled_for' => $this->scheduled_for,
            'reads_count' => $this->whenCounted('reads'),
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
