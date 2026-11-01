<?php

use App\Ai\Agents\WorkspaceAgent;
use App\Enums\Agents\KnowledgeMode;
use App\Enums\Workspaces\Role;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentMessage;
use App\Models\Agents\AgentSession;
use App\Models\Ai\ModelCatalog;
use App\Models\User;
use App\Models\Workflows\Tag;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

it('creates an agent with a generated slug', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/agents", [
        'name' => 'Support Bot',
        'instructions' => 'You help customers.',
    ]);

    $response->assertCreated();
    expect($response->json('data.agent.slug'))->toStartWith('support-bot-');
    expect($response->json('data.agent.provider'))->toBe('anthropic');
});

it('opts an agent into a model catalog entry via update, and back out again', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $catalog = ModelCatalog::factory()->create(['slug' => 'claude-3-5-sonnet']);

    Passport::actingAs($owner);

    $response = $this->patchJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}", [
        'model_catalog_id' => $catalog->id,
    ]);

    $response->assertOk();
    expect($response->json('data.agent.model_catalog_id'))->toBe($catalog->id);
    expect($agent->fresh()->model_catalog_id)->toBe($catalog->id);

    $response = $this->patchJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}", [
        'model_catalog_id' => null,
    ]);

    $response->assertOk();
    expect($response->json('data.agent.model_catalog_id'))->toBeNull();
});

it('rejects an unknown model catalog id', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();

    Passport::actingAs($owner);

    $this->patchJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}", [
        'model_catalog_id' => 999999,
    ])->assertJsonValidationErrors('model_catalog_id');
});

it('404s reading an agent that belongs to a different workspace', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $otherWorkspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Other']);

    $foreign = Agent::factory()->forWorkspace($otherWorkspace)->create();

    Passport::actingAs($owner);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/agents/{$foreign->id}")->assertNotFound();
});

it('creates a session and sends a message through the internal api', function () {
    WorkspaceAgent::fake(['Hi, how can I help?']);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();

    Passport::actingAs($owner);

    $sessionResponse = $this->postJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/sessions");
    $sessionResponse->assertCreated();
    $sessionId = $sessionResponse->json('data.session.id');

    $messageResponse = $this->postJson(
        "/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/sessions/{$sessionId}/messages",
        ['message' => 'Hello'],
    );

    $messageResponse->assertOk();
    expect($messageResponse->json('data.message.content'))->toBe('Hi, how can I help?');
    expect($messageResponse->json('data.message.role'))->toBe('assistant');
});

it('lets a member chat but not manage agents', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $member = User::factory()->create();
    $workspace->members()->create(['user_id' => $member->id, 'role' => Role::Member, 'joined_at' => now()]);

    $agent = Agent::factory()->forWorkspace($workspace)->create();

    WorkspaceAgent::fake(['ok']);
    Passport::actingAs($member);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/sessions")->assertCreated();
    $this->patchJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}", ['name' => 'x'])->assertForbidden();
});

it('saves and returns an agent\'s icon, color and self-update setting', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    Passport::actingAs($owner);

    $id = $this->postJson("/api/v1/workspaces/{$workspace->id}/agents", [
        'name' => 'Support Bot',
        'instructions' => 'You help customers.',
        'icon' => 'headphones',
        'color' => 'teal',
    ])->assertCreated()->json('data.agent.id');

    $this->patchJson("/api/v1/workspaces/{$workspace->id}/agents/{$id}", ['allow_self_updates' => true])
        ->assertOk()
        ->assertJsonPath('data.agent.icon', 'headphones')
        ->assertJsonPath('data.agent.color', 'teal')
        ->assertJsonPath('data.agent.allow_self_updates', true);
});

it('leaves skill editing off by default and lets it be turned on', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();

    Passport::actingAs($owner);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}")
        ->assertJsonPath('data.agent.allow_skill_editing', false);

    $this->patchJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}", ['allow_skill_editing' => true])
        ->assertOk()
        ->assertJsonPath('data.agent.allow_skill_editing', true);
});

it('rejects an icon or color the frontend cannot render', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();

    Passport::actingAs($owner);

    $this->patchJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}", ['icon' => 'skull', 'color' => '#ff0000'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['icon', 'color']);
});

it('keeps the model and look when duplicating an agent', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $catalog = ModelCatalog::factory()->create();
    $agent = Agent::factory()->forWorkspace($workspace)->create([
        'model_catalog_id' => $catalog->id,
        'icon' => 'rocket',
        'color' => 'orange',
        'allow_self_updates' => true,
    ]);

    Passport::actingAs($owner);

    $copy = Agent::find($this->postJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/duplicate")->assertCreated()->json('data.agent.id'));

    expect($copy->model_catalog_id)->toBe($catalog->id);
    expect($copy->icon)->toBe('rocket');
    expect($copy->color)->toBe('orange');
    expect($copy->allow_self_updates)->toBeTrue();
});

it('copies attached knowledge collections when duplicating an agent', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $agent->knowledgeCollections()->create(['collection' => 'support']);
    $agent->knowledgeCollections()->create(['collection' => 'policies', 'mode' => KnowledgeMode::Always]);
    Passport::actingAs($owner);

    $copy = Agent::find($this->postJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/duplicate")->assertCreated()->json('data.agent.id'));

    expect($copy->knowledgeCollections()->pluck('mode', 'collection')->map->value->all())
        ->toEqual(['support' => 'search', 'policies' => 'always']);
});

it('lists agents with their tags, chat count and last use', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $busy = Agent::factory()->forWorkspace($workspace)->create();
    $idle = Agent::factory()->forWorkspace($workspace)->create();
    $busy->tags()->attach(Tag::factory()->forWorkspace($workspace)->create(['name' => 'Sales'])->id);
    AgentSession::factory()->forAgent($busy)->create(['last_activity_at' => now()->subDays(3)]);
    AgentSession::factory()->forAgent($busy)->create(['last_activity_at' => now()->subHour()]);

    Passport::actingAs($owner);

    $agents = collect($this->getJson("/api/v1/workspaces/{$workspace->id}/agents")->assertOk()->json('data.agents'))->keyBy('id');

    expect($agents[$busy->id]['tags'][0]['name'])->toBe('Sales');
    expect($agents[$busy->id]['sessions_count'])->toBe(2);
    expect($agents[$busy->id]['last_used_at'])->not->toBeNull();
    expect($agents[$idle->id]['sessions_count'])->toBe(0);
    expect($agents[$idle->id]['last_used_at'])->toBeNull();
});

it('returns an agent\'s tags when reading it', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $agent->tags()->attach(Tag::factory()->forWorkspace($workspace)->create(['name' => 'Sales'])->id);

    Passport::actingAs($owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}");

    $response->assertOk();
    expect($response->json('data.agent.tags.*.name'))->toBe(['Sales']);
});

it('lists an agent\'s chats with their message counts', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $session = AgentSession::factory()->forAgent($agent)->create();
    AgentMessage::factory()->forSession($session)->count(3)->create();

    Passport::actingAs($owner);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/sessions")
        ->assertOk()
        ->assertJsonPath('data.sessions.0.messages_count', 3);
});
