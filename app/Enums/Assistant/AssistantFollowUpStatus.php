<?php

namespace App\Enums\Assistant;

enum AssistantFollowUpStatus: string
{
    /**
     * Waiting for the reminder date, or for the check to run.
     */
    case Pending = 'pending';

    /**
     * Someone answered before the date: nothing to chase.
     */
    case Replied = 'replied';

    /**
     * The date passed with no answer and the owner was reminded.
     */
    case Reminded = 'reminded';

    case Cancelled = 'cancelled';
}
