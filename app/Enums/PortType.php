<?php

namespace App\Enums;

/**
 * Port types checked by `GraphValidator`/`TypeChecker` when a `WorkflowEdge`
 * connects two node ports: see docs/WORKFLOWS_PLAN.md's "Type checking"
 * section.
 */
enum PortType: string
{
    case Text = 'text';
    case ListText = 'list_text';
    case ListListText = 'list_list_text';
    case File = 'file';
    case Boolean = 'boolean';
    case Number = 'number';

    /**
     * Whether a value produced by `$this` port can flow into a `$target` port
     * without an explicit conversion node.
     */
    public function isCompatibleWith(self $target): bool
    {
        if ($this === $target) {
            return true;
        }

        return match ($this) {
            self::Text => in_array($target, [self::ListText], true),
            self::ListText => in_array($target, [self::ListListText], true),
            default => false,
        };
    }
}
