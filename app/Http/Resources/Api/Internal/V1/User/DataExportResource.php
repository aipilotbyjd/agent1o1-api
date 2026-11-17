<?php

namespace App\Http\Resources\Api\Internal\V1\User;

use App\Models\Account\DataExport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DataExport
 */
class DataExportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'size_bytes' => $this->size_bytes,
            'is_downloadable' => $this->isDownloadable(),
            'requested_at' => $this->created_at,
            'completed_at' => $this->completed_at,
            'expires_at' => $this->expires_at,
        ];
    }
}
