<?php

use App\Broadcasting\Channels;
use App\Broadcasting\WorkspaceChannelGate;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| User Channel
|--------------------------------------------------------------------------
|
| User ids are UUIDs, so they are compared exactly as strings. Casting to
| int kept only the leading digits ("01a0…" → 1) and let nearly any user
| subscribe to any other user's channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function (User $user, string $id): bool {
    return hash_equals((string) $user->getKey(), $id);
});

/*
|--------------------------------------------------------------------------
| Workspace Channels
|--------------------------------------------------------------------------
|
| Names come from `Channels` (shared with the events that publish to them)
| and the rules from `WorkspaceChannelGate`, so neither side can drift out
| of step with the other.
|
*/

$gate = app(WorkspaceChannelGate::class);

Broadcast::channel(Channels::WORKSPACE_RUNS_PATTERN, $gate->runs(...));
Broadcast::channel(Channels::RUN_PATTERN, $gate->run(...));
Broadcast::channel(Channels::AGENT_SESSION_PATTERN, $gate->agentSession(...));
Broadcast::channel(Channels::WORKFLOW_BUILDER_SESSION_PATTERN, $gate->workflowBuilderSession(...));
Broadcast::channel(Channels::ASSISTANT_SESSION_PATTERN, $gate->assistantSession(...));
Broadcast::channel(Channels::ASSISTANT_PATTERN, $gate->assistant(...));
Broadcast::channel(Channels::PAGE_PATTERN, $gate->page(...));
Broadcast::channel(Channels::WORKFLOW_PRESENCE_PATTERN, $gate->workflowPresence(...));

/*
|--------------------------------------------------------------------------
| Announcements Channel
|--------------------------------------------------------------------------
|
| Any signed-in member. The events on it carry no content: see
| `Channels::ANNOUNCEMENTS`.
|
*/

Broadcast::channel(Channels::ANNOUNCEMENTS, fn (User $user): bool => true);
