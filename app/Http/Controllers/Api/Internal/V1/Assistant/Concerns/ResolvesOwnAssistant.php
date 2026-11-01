<?php

namespace App\Http\Controllers\Api\Internal\V1\Assistant\Concerns;

use App\Models\Assistant\Assistant;
use App\Models\Assistant\AssistantSession;
use App\Models\Assistant\AssistantTurn;
use App\Models\Workspaces\Workspace;
use App\Services\Assistant\AssistantProvisioner;
use Illuminate\Http\Request;

/**
 * Every assistant route acts on the signed-in member's own assistant. A
 * session or turn that belongs to anyone else's answers 404, not 403: its
 * existence isn't something another member should learn.
 */
trait ResolvesOwnAssistant
{
    protected function ownAssistant(Request $request, Workspace $workspace): Assistant
    {
        return app(AssistantProvisioner::class)->forMember($workspace, $request->user());
    }

    protected function ensureOwnSession(Request $request, Workspace $workspace, AssistantSession $session): void
    {
        abort_if($session->assistant_id !== $this->ownAssistant($request, $workspace)->id, 404);
        abort_if($session->expires_at?->isPast() ?? false, 404);
    }

    protected function ensureOwnTurn(Request $request, Workspace $workspace, AssistantSession $session, AssistantTurn $turn): void
    {
        $this->ensureOwnSession($request, $workspace, $session);

        abort_if($turn->assistant_session_id !== $session->id, 404);
    }
}
