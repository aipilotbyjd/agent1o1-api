<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Broadcasts;

use App\Services\Broadcasts\BroadcastAudience;
use Illuminate\Validation\Rule;

/**
 * The validation rules for an `audience` definition (see `BroadcastAudience`),
 * shared by announcements, campaigns and the audience preview.
 */
final class AudienceRules
{
    /**
     * @return array<string, mixed>
     */
    public static function for(string $key = 'audience'): array
    {
        return [
            $key => ['sometimes', 'nullable', 'array:plans,roles,workspace_ids,inactive_days'],
            "{$key}.plans" => ['sometimes', 'array', 'max:20'],
            "{$key}.plans.*" => ['string', Rule::exists('plans', 'slug')],
            "{$key}.roles" => ['sometimes', Rule::in(BroadcastAudience::ROLES)],
            "{$key}.workspace_ids" => ['sometimes', 'array', 'max:100'],
            "{$key}.workspace_ids.*" => ['uuid', Rule::exists('workspaces', 'id')],
            "{$key}.inactive_days" => ['sometimes', 'nullable', 'integer', 'min:1', 'max:3650'],
        ];
    }
}
