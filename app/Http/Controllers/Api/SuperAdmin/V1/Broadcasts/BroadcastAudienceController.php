<?php

namespace App\Http\Controllers\Api\SuperAdmin\V1\Broadcasts;

use App\Enums\Broadcasts\BroadcastCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SuperAdmin\V1\Broadcasts\PreviewAudienceRequest;
use App\Http\Responses\ApiResponse;
use App\Services\Broadcasts\BroadcastAudience;
use App\Services\Broadcasts\BroadcastCampaignService;

/**
 * "Who would this reach?": the count an email would go to and a sample of
 * them, before anything is sent. Nothing is stored.
 */
class BroadcastAudienceController extends Controller
{
    public function store(PreviewAudienceRequest $request, BroadcastCampaignService $campaigns, BroadcastAudience $audience)
    {
        $category = BroadcastCategory::from($request->validated('category', BroadcastCategory::Marketing->value));
        $definition = $request->validated('audience');

        return ApiResponse::success([
            'audience' => $audience->normalize($definition),
            ...$campaigns->previewAudience($definition, $category),
        ]);
    }
}
