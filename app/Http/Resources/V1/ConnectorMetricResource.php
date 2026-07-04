<?php

namespace App\Http\Resources\V1;

use App\Models\ConnectorMetric;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ConnectorMetric
 */
class ConnectorMetricResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'connector' => $this->connector,
            'date' => $this->date?->toDateString(),
            'total_calls' => $this->total_calls,
            'success_calls' => $this->success_calls,
            'failed_calls' => $this->failed_calls,
            'total_duration_ms' => $this->total_duration_ms,
            'avg_duration_ms' => $this->total_calls > 0 ? (int) round($this->total_duration_ms / $this->total_calls) : 0,
        ];
    }
}
