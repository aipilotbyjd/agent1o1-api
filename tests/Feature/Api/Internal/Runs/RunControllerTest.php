<?php

use App\Models\Agents\Agent;
use App\Models\Agents\AgentMessage;
use App\Models\Agents\AgentSession;
use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

it('starts a run for a published workflow and it completes synchronously', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    $workflow->replaceGraph([
        'nodes' => [['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => ['x' => 'input.x']]]],
        'edges' => [],
    ]);
    $workflow->publishVersion(publisher: $owner);
    $workflow = $workflow->fresh();

    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/runs", [
        'input' => ['x' => 42],
    ]);

    $response->assertStatus(202);
    expect($response->json('data.run.status'))->toBe('completed');
    expect($response->json('data.run.output'))->toBe(['a' => ['x' => 42]]);
});

it('rejects starting a run for an unpublished workflow', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/runs")
        ->assertStatus(422);
});

it('lists and shows runs scoped to the workspace', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph(['nodes' => [['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]]], 'edges' => []]);
    $workflow->publishVersion(publisher: $owner);
    $workflow = $workflow->fresh();

    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/runs")->assertStatus(202);

    $index = $this->getJson("/api/v1/workspaces/{$workspace->id}/runs");
    $index->assertOk();
    expect($index->json('data'))->toHaveCount(1);

    $runId = $index->json('data.0.id');

    $this->getJson("/api/v1/workspaces/{$workspace->id}/runs/{$runId}")
        ->assertOk()
        ->assertJsonPath('data.run.id', $runId);
});

it('exposes total credits used and duration on a completed run', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]],
            ['key' => 'b', 'type' => 'transform', 'config' => ['mapping' => []]],
        ],
        'edges' => [['from' => 'a', 'to' => 'b']],
    ]);
    $workflow->publishVersion(publisher: $owner);
    $workflow = $workflow->fresh();

    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/runs");
    $runId = $response->json('data.run.id');

    $show = $this->getJson("/api/v1/workspaces/{$workspace->id}/runs/{$runId}")->assertOk();

    // Two free `transform` nodes: 1 base credit each.
    expect($show->json('data.run.total_credits_used'))->toBe(2);
    expect($show->json('data.run.duration_ms'))->toBeGreaterThanOrEqual(0);
});

it('filters runs to everything done on one agent\'s behalf', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $otherAgent = Agent::factory()->forWorkspace($workspace)->create();

    $chatRun = AgentSession::factory()->forAgent($agent)->create()
        ->runs()->create(['workspace_id' => $workspace->id, 'trigger_type' => 'manual']);
    $reviewRun = $agent->reflectionRuns()->create(['workspace_id' => $workspace->id])
        ->runs()->create(['workspace_id' => $workspace->id, 'trigger_type' => 'reflection']);
    AgentSession::factory()->forAgent($otherAgent)->create()
        ->runs()->create(['workspace_id' => $workspace->id, 'trigger_type' => 'manual']);
    Run::factory()->create(['workspace_id' => $workspace->id]);

    Passport::actingAs($owner);

    $ids = collect($this->getJson("/api/v1/workspaces/{$workspace->id}/runs?agent_id={$agent->id}")->assertOk()->json('data'))->pluck('id');

    expect($ids->sort()->values()->all())->toBe(collect([$chatRun->id, $reviewRun->id])->sort()->values()->all());
});

it('still names a deleted agent and workflow behind a run', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create(['name' => 'Gone Agent']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create(['name' => 'Gone Flow']);

    AgentSession::factory()->forAgent($agent)->create()
        ->runs()->create(['workspace_id' => $workspace->id, 'trigger_type' => 'manual']);
    Run::factory()->forWorkflow($workflow)->create();
    $agent->delete();
    $workflow->delete();

    Passport::actingAs($owner);

    $runs = collect($this->getJson("/api/v1/workspaces/{$workspace->id}/runs")->assertOk()->json('data'))->keyBy('runnable_type');

    expect($runs['agent_session']['agent']['name'])->toBe('Gone Agent');
    expect($runs['workflow']['workflow']['name'])->toBe('Gone Flow');
});

it('filters runs by what ran', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();

    $chatRun = AgentSession::factory()->forAgent($agent)->create()
        ->runs()->create(['workspace_id' => $workspace->id, 'trigger_type' => 'manual']);
    $workflowRun = Run::factory()->create(['workspace_id' => $workspace->id]);

    Passport::actingAs($owner);

    $ids = fn (string $type) => collect($this->getJson("/api/v1/workspaces/{$workspace->id}/runs?runnable_type={$type}")->assertOk()->json('data'))->pluck('id')->all();

    expect($ids('workflow'))->toBe([$workflowRun->id]);
    expect($ids('agent_session'))->toBe([$chatRun->id]);
    $this->getJson("/api/v1/workspaces/{$workspace->id}/runs?runnable_type=App%5CModels%5CRuns%5CRun")->assertStatus(422);
});

it('names the agent behind a run and shows a chat turn\'s reply with its tool calls', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create(['name' => 'Researcher']);
    $session = AgentSession::factory()->forAgent($agent)->create();

    $reply = AgentMessage::factory()->forSession($session)->assistant()->create([
        'content' => 'Found it.',
        'tool_calls' => [['id' => 'call_1', 'name' => 'web_search', 'arguments' => ['query' => 'laravel']]],
        'tool_results' => [['id' => 'call_1', 'name' => 'web_search', 'result' => '{"hits":1}']],
    ]);
    $run = $session->runs()->create([
        'workspace_id' => $workspace->id,
        'trigger_type' => 'manual',
        'input' => ['message' => 'Look up laravel'],
    ]);
    $run->forceFill(['status' => 'completed', 'output' => ['text' => 'Found it.', 'message_id' => $reply->id]])->save();
    $workflowRun = Run::factory()->create(['workspace_id' => $workspace->id]);

    Passport::actingAs($owner);

    $index = collect($this->getJson("/api/v1/workspaces/{$workspace->id}/runs")->assertOk()->json('data'))->keyBy('id');
    expect($index[$run->id]['agent']['name'])->toBe('Researcher')
        ->and($index[$workflowRun->id]['agent'])->toBeNull()
        ->and($index[$run->id])->not->toHaveKey('agent_reply');

    $show = $this->getJson("/api/v1/workspaces/{$workspace->id}/runs/{$run->id}")->assertOk();
    expect($show->json('data.run.agent.id'))->toBe($agent->id)
        ->and($show->json('data.run.agent_reply.content'))->toBe('Found it.')
        ->and($show->json('data.run.agent_reply.tool_calls.0.name'))->toBe('web_search')
        ->and($show->json('data.run.agent_reply.tool_results.0.output'))->toBe('{"hits":1}');
});
