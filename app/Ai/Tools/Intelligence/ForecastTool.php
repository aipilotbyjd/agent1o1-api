<?php

namespace App\Ai\Tools\Intelligence;

use App\Services\Intelligence\ForecastService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

/**
 * Read-only forecasts for the admin analyst.
 */
class ForecastTool implements Tool
{
    private const array KINDS = ['provider_spend', 'revenue', 'margin', 'credit_run_out', 'accuracy'];

    public function __construct(private readonly ForecastService $forecasts) {}

    public function description(): Stringable|string
    {
        return 'Forecasts of daily provider spend, revenue or gross margin (with a 90% band), which customers will run out of credits this period, or how accurate past forecasts were.';
    }

    public function handle(Request $request): Stringable|string
    {
        $horizon = max(1, min((int) ($request['horizon_days'] ?? 30), 90));

        try {
            $data = match ((string) $request['kind']) {
                'provider_spend' => $this->withoutHistory($this->forecasts->providerSpend($horizon)),
                'revenue' => $this->withoutHistory($this->forecasts->revenue($horizon)),
                'margin' => $this->forecasts->margin($horizon),
                'credit_run_out' => $this->forecasts->creditRunOut(20),
                'accuracy' => $this->forecasts->accuracy(),
                default => null,
            };
        } catch (Throwable $exception) {
            report($exception);

            return 'The forecast could not be made right now.';
        }

        return $data === null ? 'Unknown kind. Use one of: '.implode(', ', self::KINDS).'.' : (json_encode($data) ?: '[]');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'kind' => $schema->string()->enum(self::KINDS)->required(),
            'horizon_days' => $schema->integer()->description('How many days ahead, 1 to 90. Defaults to 30.'),
        ];
    }

    /**
     * @param  array<string, mixed>  $forecast
     * @return array<string, mixed>
     */
    private function withoutHistory(array $forecast): array
    {
        $forecast['history'] = array_slice($forecast['history'], -14);

        return $forecast;
    }
}
