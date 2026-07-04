<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\TriggerCategoryResource;
use App\Models\TriggerCategory;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;

class TriggerCatalogController extends Controller
{
    /**
     * Return the full trigger catalog: categories → types → config fields.
     * This is the reference data the UI uses to offer app-specific triggers
     * (GitHub, Slack, Stripe, …) alongside generic webhook/polling/schedule.
     */
    public function index(Workspace $workspace): JsonResponse
    {
        if ($forbidden = $this->requirePermission(Permission::WorkflowView)) {
            return $forbidden;
        }

        $categories = TriggerCategory::query()
            ->where('is_active', true)
            ->with(['triggerTypes' => function ($query) {
                $query->where('is_active', true)->with('fields');
            }])
            ->orderBy('id')
            ->get();

        return $this->successResponse(
            'Trigger catalog retrieved.',
            TriggerCategoryResource::collection($categories),
        );
    }
}
