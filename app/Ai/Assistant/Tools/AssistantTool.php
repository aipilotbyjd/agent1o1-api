<?php

namespace App\Ai\Assistant\Tools;

use App\Enums\Assistant\AssistantToolEffect;
use App\Services\Assistant\Tools\ToolGate;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Base for every tool the assistant can call. A tool only says what it does
 * (`effect()`) and how (`execute()`); whether a call runs straight away,
 * waits for the owner, or replays a stored result is `ToolGate`'s call.
 * Built without a gate (a unit test, say) a tool simply runs.
 */
abstract class AssistantTool implements Approvable, Tool
{
    use InteractsWithApprovals;

    private ?ToolGate $gate = null;

    abstract public function name(): string;

    abstract public function effect(): AssistantToolEffect;

    abstract protected function execute(Request $request): string;

    public function gatedBy(?ToolGate $gate): static
    {
        $this->gate = $gate;

        return $this;
    }

    /**
     * What the owner is told when asked to approve this call.
     */
    public function approvalReason(Request $request): string
    {
        return "Allow {$this->name()}?";
    }

    protected function needsApproval(Request $request): Approval|bool
    {
        return $this->gate?->approvalFor($this, $request) ?? false;
    }

    public function handle(Request $request): Stringable|string
    {
        if ($this->gate === null) {
            return $this->execute($request);
        }

        return $this->gate->run($this, $request, fn (): string => $this->execute($request));
    }
}
