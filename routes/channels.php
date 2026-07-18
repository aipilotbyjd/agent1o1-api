<?php

use App\Models\AgentMessageRequest;
use App\Models\Execution;
use App\Models\User;
use App\Models\WorkflowBuilderSession;
use Illuminate\Support\Facades\Broadcast;
use Laravel\Ai\Models\Conversation;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/*
|--------------------------------------------------------------------------
| Execution channel — real-time workflow execution updates via Reverb
|--------------------------------------------------------------------------
| A user may listen to an execution if they are a member of the workspace
| the execution belongs to.
*/

Broadcast::channel('execution.{executionId}', function (User $user, string $executionId) {
    $execution = Execution::select(['id', 'workspace_id'])->find($executionId);

    if (! $execution) {
        return false;
    }

    return $user->workspaces()->where('workspaces.id', $execution->workspace_id)->exists();
});

/*
|--------------------------------------------------------------------------
| Workspace channel — workspace-wide notifications
|--------------------------------------------------------------------------
*/

Broadcast::channel('workspace.{workspaceId}', function (User $user, string $workspaceId) {
    return $user->workspaces()->where('workspaces.id', $workspaceId)->exists();
});

/*
|--------------------------------------------------------------------------
| Builder session channel — AI workflow builder real-time updates
|--------------------------------------------------------------------------
| Only the session owner may subscribe. Builder AI responses are pushed here
| once the ProcessBuilderMessageJob completes, avoiding polling.
*/

Broadcast::channel('builder.session.{sessionId}', function (User $user, string $sessionId) {
    $session = WorkflowBuilderSession::select(['id', 'user_id'])->find($sessionId);

    return $session && $session->user_id === $user->id;
});

/*
|--------------------------------------------------------------------------
| Agent stream channel — standalone Agent chat real-time updates
|--------------------------------------------------------------------------
| `{channelKey}` is an AgentMessageRequest id — created by the controller
| before ProcessAgentMessageJob is dispatched, so there's always a real,
| ownership-checkable row even for a conversation's very first message
| (before any Laravel\Ai Conversation row exists).
*/

Broadcast::channel('agent.stream.{channelKey}', function (User $user, string $channelKey) {
    $request = AgentMessageRequest::select(['id', 'user_id'])->find($channelKey);

    return $request && $request->user_id === $user->id;
});
