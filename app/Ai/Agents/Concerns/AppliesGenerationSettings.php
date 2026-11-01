<?php

namespace App\Ai\Agents\Concerns;

use App\Services\Agents\GenerationSettings;

/**
 * Exposes a `GenerationSettings` through the methods the SDK's
 * `TextGenerationOptions::forAgent()` looks for. A `null` falls through to
 * the SDK default.
 *
 * @property-read GenerationSettings|null $settings
 */
trait AppliesGenerationSettings
{
    public function temperature(): ?float
    {
        return $this->settings?->temperature;
    }

    public function maxTokens(): ?int
    {
        return $this->settings?->maxTokens;
    }

    public function maxSteps(): ?int
    {
        return $this->settings?->maxSteps;
    }

    public function topP(): ?float
    {
        return $this->settings?->topP;
    }
}
