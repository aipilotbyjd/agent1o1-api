<?php

namespace App\Services\SuperAdmin\Alerts\Evaluators;

use App\Models\Ai\AiProviderPause;
use App\Models\SuperAdmin\AlertRule;
use App\Services\SuperAdmin\Alerts\AlertEvaluator;
use App\Services\SuperAdmin\Alerts\AlertSignal;

/**
 * A provider that is paused stays an open alert until it is resumed, so a
 * pause that was meant to be temporary is not forgotten.
 */
class ProviderPaused implements AlertEvaluator
{
    public static function type(): string
    {
        return 'provider_paused';
    }

    public static function defaults(): array
    {
        return [];
    }

    public function evaluate(AlertRule $rule): array
    {
        return AiProviderPause::query()->get()
            ->map(fn (AiProviderPause $pause): AlertSignal => new AlertSignal(
                $pause->provider,
                "Provider \"{$pause->provider}\" is paused".($pause->reason ? ": {$pause->reason}" : '.'),
                ['provider' => $pause->provider, 'reason' => $pause->reason, 'paused_at' => $pause->paused_at?->toIso8601String()],
            ))
            ->all();
    }
}
