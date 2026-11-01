<?php

namespace App\Enums\Workflows;

/**
 * `workflow_builder_sessions.status`. A promoted session stays editable (it
 * can be promoted again into the same workflow) while an archived one is
 * read-only history (`workflow-builder:archive-idle`, or archived by hand).
 */
enum BuilderSessionStatus: string
{
    case Active = 'active';
    case Promoted = 'promoted';
    case Archived = 'archived';

    public function isEditable(): bool
    {
        return $this !== self::Archived;
    }
}
