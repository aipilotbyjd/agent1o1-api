<?php

namespace App\Http\Resources\Api\Public\V1\Changelog;

use App\Models\Changelog\ChangelogEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A changelog entry as the public sees it. Never carries the audience or who
 * wrote it. `body_html` is already sanitised; `body` is the markdown source.
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
            'category_label' => $this->category->label(),
            'areas' => $this->areas ?? [],
            'is_pinned' => $this->is_pinned,
            'is_major' => $this->is_major,
            'published_at' => $this->published_at,
        ];
    }
}
