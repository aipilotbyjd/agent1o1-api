<?php

namespace App\Http\Resources\Api\Internal\V1\Changelog;

use App\Http\Resources\Api\Public\V1\Changelog\ChangelogEntryResource as PublicChangelogEntryResource;
use App\Models\Changelog\ChangelogEntry;
use Illuminate\Http\Request;

/**
 * A changelog entry as a signed-in member sees it: the public shape plus
 * whether they have read it. The controller sets `is_read` on the model.
 *
 * @mixin ChangelogEntry
 */
class ChangelogEntryResource extends PublicChangelogEntryResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'is_read' => (bool) $this->resource->getAttribute('is_read'),
        ];
    }
}
