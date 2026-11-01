<?php

namespace App\Http\Resources\Api\Internal\V1\Workflows;

use App\Models\Workflows\WorkflowPageSubmission;
use App\Services\Workflows\Pages\PageSchema;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One use of a page, for its history. A visitor at the public link has no
 * user, and is shown as such. `outputs` are the values of the run's result
 * that the version of the page it came from shows.
 *
 * @mixin WorkflowPageSubmission
 */
class WorkflowPageSubmissionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'is_test' => $this->is_test,
            'variant' => $this->variant,
            'input' => (object) ($this->input ?? $this->run?->input ?? []),
            'outputs' => $this->whenLoaded('page', fn () => (object) $this->shownOutputs()),
            'duration_seconds' => $this->run?->started_at && $this->run->finished_at
                ? $this->run->started_at->diffInSeconds($this->run->finished_at)
                : null,
            'user' => $this->user === null ? null : ['id' => $this->user->id, 'name' => $this->user->name],
            'run' => $this->run === null ? null : [
                'id' => $this->run->id,
                'status' => $this->run->status->value,
                'started_at' => $this->run->started_at,
                'finished_at' => $this->run->finished_at,
            ],
            'created_at' => $this->created_at,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function shownOutputs(): array
    {
        $output = $this->run?->output ?? [];
        $content = $this->page->contentFor($this->variant, published: ! $this->is_test) ?? $this->page->content;

        return array_intersect_key($output, array_flip(PageSchema::shownOutputs($content)));
    }
}
