<?php

namespace App\Http\Resources\Api\Internal\V1\Templates;

use App\Models\Templates\AgentTemplate;
use App\Models\Templates\TemplateCollectionItem;
use App\Models\Templates\WorkflowTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TemplateCollectionItem
 */
class TemplateCollectionItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'position' => $this->position,
            'templatable_type' => $this->templatable_type,
            'templatable_id' => $this->templatable_id,
            'templatable' => $this->whenLoaded('templatable', fn () => match (true) {
                $this->templatable instanceof WorkflowTemplate => WorkflowTemplateResource::make($this->templatable),
                $this->templatable instanceof AgentTemplate => AgentTemplateResource::make($this->templatable),
                default => null,
            }),
        ];
    }
}
