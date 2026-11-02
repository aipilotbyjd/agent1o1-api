<?php

namespace App\Ai\Tools\Intelligence;

use App\Services\Intelligence\PricingSimulator;
use App\Services\Intelligence\RoutingSimulator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

/**
 * Runs the pricing or model-routing simulator for the admin analyst. Both
 * only replay history; nothing is changed.
 */
class SimulateTool implements Tool
{
    public function __construct(
        private readonly PricingSimulator $pricing,
        private readonly RoutingSimulator $routing,
    ) {}

    public function description(): Stringable|string
    {
        return 'What-if analysis on real usage. "pricing": change one plan\'s monthly price (in cents) and/or monthly credits and see revenue, cost and margin. "routing": price one model\'s recent token usage on another model.';
    }

    public function handle(Request $request): Stringable|string
    {
        try {
            $data = match ((string) $request['type']) {
                'pricing' => $this->pricing->simulate(array_filter([
                    'plans' => isset($request['plan_slug']) ? [(string) $request['plan_slug'] => array_filter([
                        'price_monthly' => isset($request['price_monthly_cents']) ? (int) $request['price_monthly_cents'] : null,
                        'credits_monthly' => isset($request['credits_monthly']) ? (int) $request['credits_monthly'] : null,
                    ], fn ($value): bool => $value !== null)] : [],
                    'overage_credit_value_usd' => isset($request['overage_credit_value_usd']) ? (float) $request['overage_credit_value_usd'] : null,
                ], fn ($value): bool => $value !== null)),
                'routing' => $this->routing->simulate(
                    (string) $request['from_provider'], (string) $request['from_model'],
                    (string) $request['to_provider'], (string) $request['to_model'],
                    isset($request['feature']) ? (string) $request['feature'] : null,
                ),
                default => null,
            };
        } catch (Throwable $exception) {
            return 'The simulation could not run: '.$exception->getMessage();
        }

        return $data === null ? 'Unknown type. Use "pricing" or "routing".' : (json_encode($data) ?: '[]');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->enum(['pricing', 'routing'])->required(),
            'plan_slug' => $schema->string(),
            'price_monthly_cents' => $schema->integer(),
            'credits_monthly' => $schema->integer(),
            'overage_credit_value_usd' => $schema->number(),
            'from_provider' => $schema->string(),
            'from_model' => $schema->string(),
            'to_provider' => $schema->string(),
            'to_model' => $schema->string(),
            'feature' => $schema->string(),
        ];
    }
}
