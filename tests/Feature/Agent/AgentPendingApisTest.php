<?php

use App\Enums\Role;
use App\Models\Agent;
use App\Models\AgentKnowledge;
use App\Models\AgentMemory;
use App\Models\AgentRun;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Str;

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

    $this->base = "/api/v1/workspaces/{$this->workspace->id}/agents";
});

test('metadata endpoints return catalogs', function () {
    $this->actingAs($this->user, 'api')
        ->getJson("{$this->base}/meta/trigger-types")
        ->assertOk()
        ->assertJsonPath('data.trigger_types.0.value', 'schedule');

    $this->actingAs($this->user, 'api')
        ->getJson("{$this->base}/meta/categories")
        ->assertOk()
        ->assertJsonStructure(['data' => ['categories']]);

    $this->actingAs($this->user, 'api')
        ->getJson("{$this->base}/meta/providers")
        ->assertOk()
        ->assertJsonStructure(['data' => ['providers']]);

    $this->actingAs($this->user, 'api')
        ->getJson("{$this->base}/meta/models")
        ->assertOk()
        ->assertJsonStructure(['data' => ['providers']]);
});

test('agent runs are listed and shown with steps', function () {
    $run = AgentRun::create([
        'agent_id' => $this->agent->id,
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'source' => 'conversation',
        'status' => 'completed',
        'input' => 'hi',
        'output' => 'hello',
        'total_tokens' => 42,
        'started_at' => now(),
        'finished_at' => now(),
    ]);
    $run->steps()->create([
        'step_number' => 1,
        'execution_node_key' => 'agent',
        'action' => 'tool_call',
        'tool_name' => 'http_request',
    ]);

    $this->actingAs($this->user, 'api')
        ->getJson("{$this->base}/{$this->agent->id}/runs")
        ->assertOk()
        ->assertJsonPath('data.0.id', $run->id)
        ->assertJsonPath('data.0.steps_count', 1);

    $this->actingAs($this->user, 'api')
        ->getJson("{$this->base}/{$this->agent->id}/runs/{$run->id}")
        ->assertOk()
        ->assertJsonPath('data.steps.0.tool_name', 'http_request');
});

test('agent analytics aggregate run history', function () {
    AgentRun::create([
        'agent_id' => $this->agent->id, 'workspace_id' => $this->workspace->id,
        'source' => 'conversation', 'status' => 'completed', 'total_tokens' => 100,
        'started_at' => now(), 'finished_at' => now(),
    ]);
    AgentRun::create([
        'agent_id' => $this->agent->id, 'workspace_id' => $this->workspace->id,
        'source' => 'trigger', 'status' => 'failed', 'total_tokens' => 0,
        'started_at' => now(), 'finished_at' => now(),
    ]);

    $this->actingAs($this->user, 'api')
        ->getJson("{$this->base}/{$this->agent->id}/analytics")
        ->assertOk()
        ->assertJsonPath('data.totals.total_runs', 2)
        ->assertJsonPath('data.totals.completed', 1)
        ->assertJsonPath('data.totals.failed', 1)
        ->assertJsonPath('data.tokens.total', 100);
});

test('knowledge base entries can be managed', function () {
    $response = $this->actingAs($this->user, 'api')
        ->postJson("{$this->base}/{$this->agent->id}/knowledge", [
            'title' => 'Refund policy',
            'content' => 'Refunds are issued within 30 days.',
        ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Refund policy');

    $id = $response->json('data.id');
    expect(AgentKnowledge::find($id)->tokens)->toBeGreaterThan(0);

    $this->actingAs($this->user, 'api')
        ->putJson("{$this->base}/{$this->agent->id}/knowledge/{$id}", ['is_active' => false])
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    $this->actingAs($this->user, 'api')
        ->deleteJson("{$this->base}/{$this->agent->id}/knowledge/{$id}")
        ->assertOk();

    expect(AgentKnowledge::find($id))->toBeNull();
});

test('memory is upserted by key within scope', function () {
    $this->actingAs($this->user, 'api')
        ->postJson("{$this->base}/{$this->agent->id}/memories", [
            'key' => 'tone', 'value' => 'friendly',
        ])
        ->assertCreated();

    // Same key upserts rather than duplicating.
    $this->actingAs($this->user, 'api')
        ->postJson("{$this->base}/{$this->agent->id}/memories", [
            'key' => 'tone', 'value' => 'formal',
        ])
        ->assertOk()
        ->assertJsonPath('data.value', 'formal');

    expect(AgentMemory::where('agent_id', $this->agent->id)->where('key', 'tone')->count())->toBe(1);

    $this->actingAs($this->user, 'api')
        ->deleteJson("{$this->base}/{$this->agent->id}/memories")
        ->assertOk();

    expect(AgentMemory::where('agent_id', $this->agent->id)->count())->toBe(0);
});

test('another workspace cannot read this agent runs', function () {
    $other = User::factory()->create();
    $otherWorkspace = Workspace::factory()->create(['owner_id' => $other->id]);
    $otherWorkspace->members()->attach($other->id, [
        'id' => Str::uuid()->toString(),
        'role' => Role::Owner,
        'joined_at' => now(),
    ]);

    $this->actingAs($other, 'api')
        ->getJson("/api/v1/workspaces/{$otherWorkspace->id}/agents/{$this->agent->id}/runs")
        ->assertNotFound();
});
