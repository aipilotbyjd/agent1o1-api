<?php

namespace App\Enums\Assistant;

/**
 * Who changed a style profile: the owner editing it, the assistant on the
 * owner's word in chat, or the learner reading their feedback.
 */
enum AssistantStyleSource: string
{
    case Owner = 'owner';
    case Assistant = 'assistant';
    case Feedback = 'feedback';
    case Restore = 'restore';
}
