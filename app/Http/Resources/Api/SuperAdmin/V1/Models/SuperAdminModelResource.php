<?php

namespace App\Http\Resources\Api\SuperAdmin\V1\Models;

use App\Models\Ai\ModelCatalog;
use App\Models\Ai\ModelRoute;
use App\Services\Ai\Operations\ModelRouteDescriber;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A catalog entry as an operator sees it, routes included: which provider
 * serves each, whether the platform can run it, what it costs and how it last
 * tested. Unlike the user-facing resource it shows the real backends, and it
 * never carries a key.
 *
 * @mixin ModelCatalog
 */
class SuperAdminModelResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $describer = app(ModelRouteDescriber::class);

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'display_name' => $this->display_name,
            'brand' => $this->brand,
            'description' => $this->description,
            'strengths' => $this->strengths ?? [],
            'speed' => $this->speed?->value,
            'cost_tier' => $this->cost_tier,
            'capabilities' => $this->capabilities,
            'is_active' => (bool) $this->is_active,
            'is_internal' => (bool) $this->is_internal,
            'sort_order' => $this->sort_order,
            'status' => $this->status(),
            'retires_at' => $this->retires_at,
            'replacement_slug' => $this->replacement_slug,
            'min_plan' => $this->whenLoaded('minPlan', fn () => $this->minPlan === null ? null : [
                'id' => $this->minPlan->id,
                'slug' => $this->minPlan->slug,
                'name' => $this->minPlan->name,
            ]),
            'routes' => $this->whenLoaded('routes', fn () => $this->routes
                ->map(fn (ModelRoute $route): array => $describer->describe($route))
                ->values()
                ->all()),
            'created_at' => $this->created_at,
        ];
    }

    /**
     * draft: never in service. active: in service. deprecated: in service, a
     * retirement is announced. overdue: in service past its retirement date.
     * retired: taken out of service.
     */
    private function status(): string
    {
        return match (true) {
            ! $this->is_active && ($this->retires_at !== null || filled($this->replacement_slug)) => 'retired',
            ! $this->is_active => 'draft',
            $this->retires_at?->isPast() => 'overdue',
            $this->retires_at !== null => 'deprecated',
            default => 'active',
        };
    }
}
