<?php

namespace App\Services\Agents\Approvals;

use App\Enums\Agents\ActionEffect;
use App\Enums\Agents\ActionToolKind;

/**
 * One call the gate is asked to weigh. `$arguments` is what the model sent
 * (already narrowed to what it may set); `$effectiveArguments` is what the
 * call would really run with (bound values merged over) which is what
 * rule conditions are checked against.
 */
final readonly class ToolCall
{
    /**
     * @param  array<string, mixed>  $arguments
     * @param  array<string, mixed>  $effectiveArguments
     * @param  array<string, mixed>|null  $policy  the tool's own `approval_policy`
     */
    public function __construct(
        public string $toolName,
        public ActionToolKind $kind,
        public ActionEffect $effect,
        public array $arguments,
        public array $effectiveArguments,
        public ?string $toolCallId = null,
        public ?array $policy = null,
    ) {}
}
