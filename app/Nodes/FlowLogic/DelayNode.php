<?php

namespace App\Nodes\FlowLogic;

use App\Contracts\DeclaresEffect;
use App\Contracts\HasIcon;
use App\Contracts\NodeContract;
use App\Enums\Agents\ActionEffect;
use App\Enums\NodeCategory;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

/**
 * Implements `NodeContract` (unlike its `FlowLogic` siblings): `execute()`
 * itself does no waiting, it just reports how long to wait. The engine
 * (`WorkflowRunner`) reads `seconds` back off the output and delays the
 * *next* dispatch by that long, rather than blocking a queue worker inside
 * `execute()`.
 */
class DelayNode implements DeclaresEffect, HasIcon, NodeContract
{
    /**
     * The longest a step may pause: seven days. Longer waits belong to the
     * Wait node, which resumes on a callback instead of holding a queued job.
     */
    public const int MAX_SECONDS = 604800;

    public function type(): string
    {
        return 'delay';
    }

    public function category(): string
    {
        return NodeCategory::FlowLogic->value;
    }

    public function name(): string
    {
        return 'Delay';
    }

    public function icon(): string
    {
        return 'timer-01';
    }

    public function description(): string
    {
        return 'Pauses before the next node runs, for a configured number of seconds.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['seconds'],
            'properties' => [
                'seconds' => Field::integer('Wait for (seconds)', 'Up to 7 days (604800 seconds).', 60, 0, self::MAX_SECONDS),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['seconds' => min(self::MAX_SECONDS, max(0, (int) ($config['seconds'] ?? 0)))];
    }
}
