<?php

use App\Agents\Internal\WorkflowAgent;
use App\Enums\Role;
use App\Models\Agent;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Str;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;

beforeEach(function () {
    $this->seed(PlanSeeder::class);

    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['owner_id' => $this->user->id]);
    $this->workspace->members()->attach($this->user->id, [
        'id' => Str::uuid()->toString(),
        'role' => Role::Owner,
        'joined_at' => now(),
    ]);

    $this->agent = Agent::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
    ]);
});

/**
 * Persist a conversation row owned by the given agent/user.
 */
function makeConversation(Agent $agent, User $user, Workspace $workspace): Conversation
{
    $conversation = new Conversation;
    $conversation->forceFill([
        'id' => Str::uuid()->toString(),
        'user_id' => $user->id,
        'title' => 'Test conversation',
        'agent_id' => $agent->id,
        'workspace_id' => $workspace->id,
    ])->save();

    ConversationMessage::query()->forceCreate([
        'id' => Str::uuid()->toString(),
        'conversation_id' => $conversation->id,
        'user_id' => $user->id,
        'agent' => WorkflowAgent::class,
        'role' => 'user',
        'content' => 'Hello',
        'attachments' => [],
        'tool_calls' => [],
        'tool_results' => [],
        'usage' => [],
        'meta' => [],
    ]);

    return $conversation;
}

test('starting a conversation returns the agent reply', function () {
    WorkflowAgent::fake(['Hello, how can I help?']);

    $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/agents/{$this->agent->id}/conversations", [
            'message' => 'Hi there',
        ])
        ->assertCreated()
        ->assertJsonPath('data.response', 'Hello, how can I help?');
});

test('conversations are scoped to the agent and user', function () {
    makeConversation($this->agent, $this->user, $this->workspace);

    $this->actingAs($this->user, 'api')
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/agents/{$this->agent->id}/conversations")
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('a conversation can be shown with its messages', function () {
    $conversation = makeConversation($this->agent, $this->user, $this->workspace);

    $this->actingAs($this->user, 'api')
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/agents/{$this->agent->id}/conversations/{$conversation->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $conversation->id)
        ->assertJsonCount(1, 'data.messages');
});

test('sending a message to an existing conversation returns a reply', function () {
    WorkflowAgent::fake(['Sure thing.']);

    $conversation = makeConversation($this->agent, $this->user, $this->workspace);

    $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/agents/{$this->agent->id}/conversations/{$conversation->id}/messages", [
            'message' => 'Continue please',
        ])
        ->assertOk()
        ->assertJsonPath('data.response', 'Sure thing.');
});

test('another user cannot view a conversation', function () {
    $conversation = makeConversation($this->agent, $this->user, $this->workspace);

    $outsider = User::factory()->create();
    $this->workspace->members()->attach($outsider->id, [
        'id' => Str::uuid()->toString(),
        'role' => Role::Member,
        'joined_at' => now(),
    ]);

    $this->actingAs($outsider, 'api')
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/agents/{$this->agent->id}/conversations/{$conversation->id}")
        ->assertNotFound();
});
