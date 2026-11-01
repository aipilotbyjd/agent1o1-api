<?php

use App\Enums\Agents\AgentMessageRole;
use App\Events\Agents\AgentMessageCreated;
use App\Models\Agents\Agent;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Event;

it('broadcasts a notice small enough for reverb however long the reply', function () {
    Event::fake([AgentMessageCreated::class]);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $session = $agent->sessions()->create(['workspace_id' => $workspace->id]);

    $message = $session->messages()->create([
        'role' => AgentMessageRole::Assistant,
        'content' => str_repeat('A long playbook. ', 5000),
        'tool_calls' => [['id' => 'call-1', 'name' => 'remember', 'arguments' => ['key' => str_repeat('k', 20000)]]],
    ]);

    $payload = (new AgentMessageCreated($message))->broadcastWith();

    expect(array_keys($payload))->toBe(['id', 'agent_session_id', 'role', 'created_at']);
    expect(strlen(json_encode($payload)))->toBeLessThan((int) config('reverb.apps.apps.0.max_message_size'));
});
