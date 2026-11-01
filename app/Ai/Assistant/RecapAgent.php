<?php

namespace App\Ai\Assistant;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

/**
 * Folds the older part of a long conversation into a recap the assistant
 * keeps in its instructions (`ContextCompactor`), so a long chat keeps its
 * thread without resending every message.
 */
class RecapAgent implements Agent
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'PROMPT'
            You summarize the earlier part of a conversation between a person and their AI assistant, so the assistant can continue without the full transcript.
            Write concise notes under these headings, leaving out any that are empty:
            Goals: what the person is trying to get done.
            Decisions: what was agreed or decided.
            Facts: names, dates, numbers, links and other specifics that may matter later.
            Done: what the assistant already did (emails sent, items created), so it isn't repeated.
            Open: questions or tasks still outstanding.
            Keep exact values (emails, ids, amounts, dates). Do not invent anything. Write in the language of the conversation.
            PROMPT;
    }
}
