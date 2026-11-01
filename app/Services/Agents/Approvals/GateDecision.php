<?php

namespace App\Services\Agents\Approvals;

use App\Enums\Agents\ActionRisk;
use App\Enums\Agents\ActionVerdict;
use App\Models\Agents\AgentAction;

/**
 * The gate's answer for one call, and the `AgentAction` it was recorded as:
 * null only for a read that ran freely, which isn't logged.
 */
final readonly class GateDecision
{
    /**
     * @param  array{source: string, detail: string}|null  $reason
     */
    public function __construct(
        public ActionVerdict $verdict,
        public ?array $reason = null,
        public ?AgentAction $action = null,
        public ?ActionRisk $risk = null,
    ) {}

    public static function fromAction(AgentAction $action): self
    {
        return new self($action->outcome, $action->reason, $action, $action->risk);
    }

    public function detail(): ?string
    {
        return $this->reason['detail'] ?? null;
    }
}
