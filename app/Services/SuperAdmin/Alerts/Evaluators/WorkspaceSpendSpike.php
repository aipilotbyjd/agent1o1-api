<?php

namespace App\Services\SuperAdmin\Alerts\Evaluators;

use App\Models\SuperAdmin\AlertRule;
use App\Services\Ai\Operations\SpendGuard;
use App\Services\SuperAdmin\Alerts\AlertEvaluator;
use App\Services\SuperAdmin\Alerts\AlertSignal;

/**
 * Reuses `SpendGuard`'s findings (its thresholds live in
 * `config/model_operations.php`) rather than re-deriving spend: the budget and
 * per-workspace spike logic stays in one place.
 */
class WorkspaceSpendSpike implements AlertEvaluator
{
    public function __construct(private readonly SpendGuard $guard) {}

    public static function type(): string
    {
        return 'workspace_spend_spike';
    }

    public static function defaults(): array
    {
        return [];
    }

    public function evaluate(AlertRule $rule): array
    {
        return array_map(
            fn (array $finding): AlertSignal => new AlertSignal(
                $finding['code'].':'.($finding['workspace_id'] ?? 'platform'),
                $finding['message'],
                ['workspace_id' => $finding['workspace_id'], 'code' => $finding['code']],
            ),
            $this->guard->findings(),
        );
    }
}
