<?php

namespace App\Ai\Tools\WorkflowBuilder\Concerns;

use App\Exceptions\WorkflowBuilderConflictException;
use App\Models\User;
use Closure;

/**
 * Shared error handling for the tools that change the draft. A rejected edit
 * goes back to the model as the tool's result, never as an exception, so it
 * can correct itself, including when the user changed the draft on the
 * canvas mid-turn, in which case the session is reloaded first so the next
 * attempt starts from what the user now sees.
 *
 * Edits are attributed to `$actingUser` (whoever sent the message this
 * turn answers) falling back to the session's owner.
 */
trait EditsDraft
{
    use ReadsToolArguments;

    /**
     * @param  Closure(): string  $edit  Makes the edit and returns its confirmation.
     */
    protected function attemptEdit(Closure $edit): string
    {
        return $this->answer(function () use ($edit): string {
            try {
                return $edit();
            } catch (WorkflowBuilderConflictException) {
                $this->session->refresh();

                return $this->session->status->isEditable()
                    ? 'The user changed the draft while you were working, so this edit was not applied. Call read_draft to see the current draft, then try again.'
                    : WorkflowBuilderConflictException::archived()->getMessage().' Stop editing and tell the user.';
            }
        });
    }

    protected function editor(): ?User
    {
        return $this->actingUser ?? $this->session->user;
    }
}
