<?php

namespace App\Services\Agents\Approvals;

use App\Enums\Agents\AgentActionStatus;
use App\Models\Agents\AgentAction;

/**
 * What the model is told when a call didn't simply run. Worded so it
 * doesn't retry the same thing in a loop, and passes on anything the person
 * said when they turned it down.
 */
final class ActionMessages
{
    public static function denied(AgentAction $action): string
    {
        return self::encode([
            'error' => 'This action was blocked and did not run.',
            'reason' => $action->reason['detail'] ?? null,
            'note' => 'Do not retry it. Tell the user what you wanted to do instead, or find another way that does not need it.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $effectiveArguments
     */
    public static function simulated(AgentAction $action, array $effectiveArguments): string
    {
        return self::encode([
            'simulated' => true,
            'note' => ($action->reason['detail'] ?? 'This action was simulated.').' Carry on as if it had succeeded, and tell the user it was simulated.',
            'would_run' => ['tool' => $action->tool_name, 'arguments' => $effectiveArguments],
        ]);
    }

    public static function rejected(AgentAction $action): string
    {
        return self::rejectionText($action->status, $action->decision_note);
    }

    public static function rejectionText(AgentActionStatus $status, ?string $note): string
    {
        $base = match ($status) {
            AgentActionStatus::Expired => 'Nobody approved this action in time, so it did not run.',
            AgentActionStatus::Cancelled => 'This action was cancelled before anyone approved it, so it did not run.',
            default => 'The user rejected this action, so it did not run.',
        };

        return filled($note) ? "{$base} Their note: {$note}" : $base;
    }

    public static function failed(AgentAction $action): string
    {
        return self::encode(['error' => 'The action failed: '.($action->result ?? 'unknown error')]);
    }

    public static function pending(AgentAction $action): string
    {
        return self::encode(['status' => 'waiting_for_approval', 'note' => 'This action is waiting for a person to approve it.']);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function encode(array $payload): string
    {
        return json_encode(array_filter($payload, fn (mixed $value): bool => $value !== null), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
    }
}
