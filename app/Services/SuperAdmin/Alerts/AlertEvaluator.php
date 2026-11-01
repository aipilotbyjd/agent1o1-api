<?php

namespace App\Services\SuperAdmin\Alerts;

use App\Models\SuperAdmin\AlertRule;

/**
 * Looks at the platform for one kind of problem. Evaluators only read: they
 * return what is wrong right now, and `AlertEngine` decides what that means
 * (a new alert, a repeat, a recovery).
 */
interface AlertEvaluator
{
    /**
     * The rule type this evaluator handles.
     */
    public static function type(): string;

    /**
     * The thresholds this evaluator understands, with their defaults. A rule's
     * own `config` overrides these, and only these keys may be tuned.
     *
     * @return array<string, int|float|bool>
     */
    public static function defaults(): array;

    /**
     * @return list<AlertSignal>
     */
    public function evaluate(AlertRule $rule): array;
}
