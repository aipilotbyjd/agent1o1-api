<?php

namespace App\Enums\Assistant;

enum AssistantInboxDraftStatus: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Suggested = 'suggested';
    case KeptYourEdits = 'kept_your_edits';
}
