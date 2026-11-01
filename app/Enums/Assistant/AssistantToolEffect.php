<?php

namespace App\Enums\Assistant;

/**
 * What calling a tool does in the world. Decides whether it runs straight
 * away or waits for the owner (`ToolGate`), and whether background runs may
 * use it at all, only `Read` tools may.
 */
enum AssistantToolEffect: string
{
    case Read = 'read';

    /**
     * Changes only the assistant's own state (its memory, its notes):
     * nothing the owner would need to confirm.
     */
    case Internal = 'internal';

    case Write = 'write';
    case External = 'external';
    case Destructive = 'destructive';

    public function defaultRule(): AssistantToolRule
    {
        return in_array($this, [self::Read, self::Internal], true) ? AssistantToolRule::Allow : AssistantToolRule::Ask;
    }

    /**
     * A destructive call is always confirmed, whatever the owner's rules say.
     */
    public function canBeAutoAllowed(): bool
    {
        return $this !== self::Destructive;
    }
}
