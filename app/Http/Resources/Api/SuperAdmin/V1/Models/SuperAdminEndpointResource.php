<?php

namespace App\Http\Resources\Api\SuperAdmin\V1\Models;

use App\Models\Ai\AiEndpoint;
use App\Services\Ai\EndpointRateLimiter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An operator endpoint. The key is never returned: only whether one is set
 * and its last four characters.
 *
 * @mixin AiEndpoint
 */
class SuperAdminEndpointResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'base_url' => $this->base_url,
            'has_key' => filled($this->api_key),
            'key_hint' => $this->keyHint(),
            'is_enabled' => $this->is_enabled,
            'workspace_requests_per_minute' => $this->workspace_requests_per_minute,
            'total_requests_per_minute' => $this->total_requests_per_minute,
            'rejected_today' => app(EndpointRateLimiter::class)->rejectedToday($this->slug),
            'notes' => $this->notes,
            'last_checked_at' => $this->last_checked_at,
            'last_check_ok' => $this->last_check_ok,
            'last_check_message' => $this->last_check_message,
            'created_at' => $this->created_at,
        ];
    }
}
