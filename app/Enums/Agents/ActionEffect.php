<?php

namespace App\Enums\Agents;

/**
 * What a tool call does to the world, declared by the tool itself (a node
 * through `Contracts\DeclaresEffect`). Declared from least to most
 * consequential; `strongest()` picks the higher one, e.g. for a workflow
 * made of several nodes.
 */
enum ActionEffect: string
{
    /** Looks something up; changes nothing. */
    case Read = 'read';

    /** Creates or changes something inside a connected app. */
    case Write = 'write';

    /** Reaches other people: sends an email, posts a message, opens an issue. */
    case External = 'external';

    /** Deletes something. */
    case Destructive = 'destructive';

    public function rank(): int
    {
        return array_search($this, self::cases(), true);
    }

    public function isRead(): bool
    {
        return $this === self::Read;
    }

    public static function strongest(self ...$effects): self
    {
        return array_reduce($effects, fn (self $carry, self $effect): self => $effect->rank() > $carry->rank() ? $effect : $carry, self::Read);
    }
}
