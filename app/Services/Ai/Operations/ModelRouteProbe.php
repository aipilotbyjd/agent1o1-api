<?php

namespace App\Services\Ai\Operations;

use App\Ai\Agents\AdHocPromptAgent;
use App\Ai\ResponseUsage;
use App\Services\Ai\ModelCatalogResolver;
use App\Services\Ai\OperatorEndpoints;
use App\Services\Billing\CreditMeter;
use Illuminate\Support\Str;
use Throwable;

/**
 * Sends one tiny real prompt down a single route and reports whether it
 * answered, how fast, and what it cost. Always the platform's own key (the
 * provider is named explicitly, so no workspace key is ever in play): the
 * point is to know the route works *before* a customer's call finds out.
 */
class ModelRouteProbe
{
    private const string INSTRUCTIONS = 'You are being health-checked. Reply with the single word: ok';

    public function __construct(
        private readonly OperatorEndpoints $endpoints,
        private readonly CreditMeter $meter,
    ) {}

    /**
     * @return array{ok: bool, latency_ms: ?int, prompt_tokens: ?int, completion_tokens: ?int, estimated_cost_usd: ?float, message: string}
     */
    public function probe(string $provider, string $model): array
    {
        $this->endpoints->ensureRegistered($provider);

        if (! ModelCatalogResolver::providerIsConfigured($provider)) {
            return $this->failed('The platform has no key for this provider, so there is nothing to test with.');
        }

        $startedAt = now();

        try {
            $response = (new AdHocPromptAgent(self::INSTRUCTIONS))->prompt('Health check.', provider: [$provider => $model]);
        } catch (Throwable $exception) {
            return $this->failed(Str::limit($exception->getMessage(), 300, ''), (int) $startedAt->diffInMilliseconds(now()));
        }

        $usage = ResponseUsage::from($response, $startedAt);

        return [
            'ok' => true,
            'latency_ms' => (int) $startedAt->diffInMilliseconds(now()),
            'prompt_tokens' => (int) ($usage['prompt_tokens'] ?? 0),
            'completion_tokens' => (int) ($usage['completion_tokens'] ?? 0),
            'estimated_cost_usd' => $this->meter->dollarCost([...$usage, 'provider' => $provider, 'model' => $model]),
            'message' => 'The route answered.',
        ];
    }

    /**
     * @return array{ok: bool, latency_ms: ?int, prompt_tokens: ?int, completion_tokens: ?int, estimated_cost_usd: ?float, message: string}
     */
    private function failed(string $message, ?int $latencyMs = null): array
    {
        return [
            'ok' => false,
            'latency_ms' => $latencyMs,
            'prompt_tokens' => null,
            'completion_tokens' => null,
            'estimated_cost_usd' => null,
            'message' => $message,
        ];
    }
}
