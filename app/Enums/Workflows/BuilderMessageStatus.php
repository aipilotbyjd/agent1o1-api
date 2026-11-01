<?php

namespace App\Enums\Workflows;

/**
 * `workflow_builder_messages.processing_status`. Only assistant messages move
 * through `Pending` → `Processing` → `Completed`/`Failed`; user messages are
 * written `Completed`.
 */
enum BuilderMessageStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function isInFlight(): bool
    {
        return $this === self::Pending || $this === self::Processing;
    }
}
