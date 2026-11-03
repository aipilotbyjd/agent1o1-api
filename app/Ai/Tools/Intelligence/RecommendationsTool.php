<?php

namespace App\Ai\Tools\Intelligence;

use App\Enums\Intelligence\RecommendationStatus;
use App\Models\Intelligence\AdminRecommendation;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * The open recommendations, for the admin analyst to point at. It cannot
 * apply them: a person does that from the recommendations page.
 */
class RecommendationsTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'The open recommended actions, most severe first, each with its evidence.';
    }

    public function handle(Request $request): Stringable|string
    {
        $recommendations = AdminRecommendation::query()
            ->where('status', RecommendationStatus::Open)
            ->orderByRaw("case severity when 'critical' then 0 when 'warning' then 1 else 2 end")
            ->limit(20)
            ->get(['rule', 'severity', 'title', 'body', 'evidence'])
            ->toArray();

        return $recommendations === [] ? 'There are no open recommendations.' : (json_encode($recommendations) ?: '[]');
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
