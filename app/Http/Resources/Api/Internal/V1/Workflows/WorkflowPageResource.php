<?php

namespace App\Http\Resources\Api\Internal\V1\Workflows;

use App\Models\User;
use App\Models\Workflows\WorkflowPage;
use App\Services\Workflows\Pages\PageSchema;
use App\Services\Workflows\Pages\PageSharePreview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A page for the people who build and manage it. `issues` is worked out
 * against the workflow as it is now, so a page that was fine when it was
 * published says so when the workflow has changed under it since.
 *
 * @mixin WorkflowPage
 */
class WorkflowPageResource extends JsonResource
{
    /**
     * Lists leave the content out; the builder loads one page at a time.
     */
    protected bool $withContent = true;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $workflow = $this->workflow;

        return [
            'id' => $this->id,
            'workflow_id' => $this->workflow_id,
            'workflow' => $workflow === null ? null : [
                'id' => $workflow->id,
                'name' => $workflow->name,
                'published' => $workflow->isPublished(),
                'trashed' => $workflow->trashed(),
            ],
            'name' => $this->name,
            'description' => $this->description,
            'content' => $this->when($this->withContent, fn () => $this->content),
            'published_content' => $this->when($this->withContent, fn () => $this->published_content),
            'is_published' => $this->isPublished(),
            'has_unpublished_changes' => $this->hasUnpublishedChanges(),
            'published_at' => $this->published_at,
            'published_by' => $this->whenLoaded('publisher', fn () => $this->person($this->publisher)),
            'access' => $this->access->value,
            'has_password' => $this->password !== null,
            'public_token' => $this->public_token,
            'is_enabled' => $this->is_enabled,
            'max_runs_per_day' => $this->max_runs_per_day,
            'share' => [
                'title' => $this->share_title,
                'description' => $this->share_description,
                'image_url' => $this->share_image_url,
            ],
            'share_preview' => $this->when($this->withContent, fn () => app(PageSharePreview::class)->for($this->resource)),
            'runs_count' => $this->whenCounted('runs'),
            'last_run_at' => $this->when(
                array_key_exists('submissions_max_created_at', $this->resource->getAttributes()),
                fn () => $this->resource->getAttribute('submissions_max_created_at'),
            ),
            'issues' => app(PageSchema::class)->issues($this->content, $workflow),
            'draft_revision' => $this->draft_revision,
            'variant' => [
                'exists' => $this->variant_content !== null,
                'live' => $this->isTestingVariant(),
                'split' => $this->variant_split,
                'content' => $this->when($this->withContent, fn () => $this->variant_content),
                'issues' => $this->variant_content === null ? [] : app(PageSchema::class)->issues($this->variant_content, $workflow),
            ],
            'created_by' => $this->whenLoaded('creator', fn () => $this->person($this->creator)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * @return array{id: string, name: string}|null
     */
    private function person(?User $user): ?array
    {
        return $user === null ? null : ['id' => $user->id, 'name' => $user->name];
    }
}
