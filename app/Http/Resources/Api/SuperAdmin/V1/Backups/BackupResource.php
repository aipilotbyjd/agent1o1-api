<?php

namespace App\Http\Resources\Api\SuperAdmin\V1\Backups;

use App\Models\System\Backup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Backup
 */
class BackupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'disk' => $this->disk,
            'filename' => $this->path === null ? null : basename($this->path),
            'size_bytes' => $this->size_bytes,
            'downloadable' => $this->isDownloadable(),
            'triggered_by' => $this->whenLoaded('triggeredBy', fn (): ?array => $this->triggeredBy === null ? null : [
                'id' => $this->triggeredBy->id,
                'name' => $this->triggeredBy->name,
            ]),
            'error' => $this->error,
            'started_at' => $this->started_at,
            'finished_at' => $this->finished_at,
            'created_at' => $this->created_at,
        ];
    }
}
