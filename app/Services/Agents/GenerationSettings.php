<?php

namespace App\Services\Agents;

use App\Models\Agents\Agent;

/**
 * The per-agent sampling options handed to the provider: `temperature`
 * from its own column, the rest from the free-form `settings` array.
 * Unknown `settings` keys are ignored; a missing one leaves the SDK's
 * default in place.
 */
final readonly class GenerationSettings
{
    /**
     * Hard ceilings, whatever an agent's settings say: a member could
     * otherwise set a step count that turns one prompt into a runaway,
     * credit-draining tool loop.
     */
    public const int MAX_STEPS = 50;

    public const int MAX_TOKENS = 32_768;

    public function __construct(
        public ?float $temperature = null,
        public ?int $maxTokens = null,
        public ?int $maxSteps = null,
        public ?float $topP = null,
    ) {}

    public static function fromAgent(Agent $agent): self
    {
        $settings = $agent->settings ?? [];

        return new self(
            temperature: $agent->temperature !== null ? (float) $agent->temperature : null,
            maxTokens: isset($settings['max_tokens']) ? self::clamp((int) $settings['max_tokens'], self::MAX_TOKENS) : null,
            maxSteps: isset($settings['max_steps']) ? self::clamp((int) $settings['max_steps'], self::MAX_STEPS) : null,
            topP: isset($settings['top_p']) ? (float) $settings['top_p'] : null,
        );
    }

    private static function clamp(int $value, int $ceiling): int
    {
        return max(1, min($value, $ceiling));
    }
}
