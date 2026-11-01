<?php

namespace App\Http\Controllers\Api\Internal\V1\Triggers;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Internal\V1\Triggers\TriggerPresetResource;
use App\Http\Responses\ApiResponse;
use App\Models\Triggers\TriggerPreset;

class TriggerPresetController extends Controller
{
    public function __invoke()
    {
        $presets = TriggerPreset::query()
            ->where('is_active', true)
            ->orderBy('category')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('category')
            ->map(fn ($group) => TriggerPresetResource::collection($group));

        return ApiResponse::success(['presets' => $presets]);
    }
}
