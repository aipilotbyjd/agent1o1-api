<?php

namespace App\Ai\Tools\Intelligence;

use App\Services\Intelligence\RiskScores;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

/**
 * Read-only customer risk lists for the admin analyst.
 */
class RisksTool implements Tool
{
    private const array LISTS = ['margin_at_risk', 'churn', 'upgrade', 'abuse'];

    public function __construct(private readonly RiskScores $risks) {}

    public function description(): Stringable|string
    {
        return 'Customers ranked by a 0-100 score with the reasons behind it: projected to lose money this period, likely to cancel, likely to upgrade, or likely abusing free credits.';
    }

    public function handle(Request $request): Stringable|string
    {
        try {
            $data = match ((string) $request['list']) {
                'margin_at_risk' => $this->risks->marginAtRisk(15),
                'churn' => $this->risks->churnRisk(15),
                'upgrade' => $this->risks->upgradeLikelihood(15),
                'abuse' => $this->risks->abuse(15),
                default => null,
            };
        } catch (Throwable $exception) {
            report($exception);

            return 'The list could not be read right now.';
        }

        return $data === null ? 'Unknown list. Use one of: '.implode(', ', self::LISTS).'.' : (json_encode($data) ?: '[]');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'list' => $schema->string()->enum(self::LISTS)->required(),
        ];
    }
}
