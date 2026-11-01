<?php

use App\Ai\Agents\EmbeddedAgent;
use App\Jobs\Agents\RunAgentEvalJob;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentEvalCase;
use App\Models\Agents\AgentEvalSuite;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Queue;
use Laravel\Passport\Passport;

/**
 * @return array{0: Agent, 1: User, 2: Workspace}
 */
function evalApiAgent(): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();

    return [$agent, $owner, $workspace];
}

it('creates a suite, adds cases and executes it end to end', function () {
    EmbeddedAgent::fake(['Refunds are available within 30 days.']);

    [$agent, $owner, $workspace] = evalApiAgent();

    Passport::actingAs($owner);
    $base = "/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/eval-suites";

    $suiteId = $this->postJson($base, ['name' => 'Refund policy', 'description' => 'Core answers'])
        ->assertCreated()
        ->json('data.suite.id');

    $this->postJson("{$base}/{$suiteId}/cases", [
        'name' => 'mentions the window',
        'input' => 'How long do I have to request a refund?',
        'assertions' => [
            ['type' => 'contains', 'value' => '30 days'],
            ['type' => 'not_contains', 'value' => 'no refunds'],
        ],
    ])->assertCreated();

    $this->getJson("{$base}/{$suiteId}")
        ->assertOk()
        ->assertJsonPath('data.suite.cases.0.name', 'mentions the window');

    $run = $this->postJson("{$base}/{$suiteId}/runs");

    $run->assertCreated()
        ->assertJsonPath('data.run.status', 'completed')
        ->assertJsonPath('data.run.passed', 1)
        ->assertJsonPath('data.run.failed', 0)
        ->assertJsonPath('data.run.results.0.passed', true);

    expect($run->json('data.run.agent_version_id'))->not->toBeNull();

    $this->getJson("{$base}/{$suiteId}/runs")
        ->assertOk()
        ->assertJsonPath('data.runs.0.id', $run->json('data.run.id'));
});

it('refuses a case with no assertions', function () {
    [$agent, $owner, $workspace] = evalApiAgent();
    $suite = AgentEvalSuite::factory()->forAgent($agent)->create();

    Passport::actingAs($owner);

    $this->postJson(
        "/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/eval-suites/{$suite->id}/cases",
        ['name' => 'empty', 'input' => 'anything', 'assertions' => []],
    )->assertStatus(422);
});

it('refuses an unknown assertion type', function () {
    [$agent, $owner, $workspace] = evalApiAgent();
    $suite = AgentEvalSuite::factory()->forAgent($agent)->create();

    Passport::actingAs($owner);

    $this->postJson(
        "/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/eval-suites/{$suite->id}/cases",
        ['name' => 'bad', 'input' => 'q', 'assertions' => [['type' => 'vibes', 'value' => 'good']]],
    )->assertStatus(422);
});

it('updates and deletes cases and suites', function () {
    [$agent, $owner, $workspace] = evalApiAgent();
    $suite = AgentEvalSuite::factory()->forAgent($agent)->create();
    $case = AgentEvalCase::factory()->forSuite($suite)->create(['name' => 'original']);

    Passport::actingAs($owner);
    $base = "/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/eval-suites/{$suite->id}";

    $this->patchJson("{$base}/cases/{$case->id}", ['name' => 'renamed'])
        ->assertOk()
        ->assertJsonPath('data.case.name', 'renamed');

    $this->deleteJson("{$base}/cases/{$case->id}")->assertNoContent();
    expect($suite->cases()->count())->toBe(0);

    $this->deleteJson($base)->assertNoContent();
    expect($agent->evalSuites()->count())->toBe(0);
});

it('404s a suite that belongs to a different agent', function () {
    [$agent, $owner, $workspace] = evalApiAgent();
    $other = Agent::factory()->forWorkspace($workspace)->create();
    $suite = AgentEvalSuite::factory()->forAgent($other)->create();

    Passport::actingAs($owner);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/eval-suites/{$suite->id}")
        ->assertNotFound();
});

it('lets a viewer read suites but not run them', function () {
    [$agent, $owner, $workspace] = evalApiAgent();
    $suite = AgentEvalSuite::factory()->forAgent($agent)->create();

    $viewer = User::factory()->create();
    $workspace->members()->create(['user_id' => $viewer->id, 'role' => 'viewer']);

    Passport::actingAs($viewer);
    $base = "/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/eval-suites";

    $this->getJson($base)->assertOk();
    $this->postJson("{$base}/{$suite->id}/runs")->assertForbidden();
    $this->postJson($base, ['name' => 'Nope'])->assertForbidden();
});

it('queues a suite run and returns it as pending', function () {
    Queue::fake();

    [$agent, $owner, $workspace] = evalApiAgent();
    $suite = AgentEvalSuite::factory()->forAgent($agent)->create();
    AgentEvalCase::factory()->forSuite($suite)->create([
        'assertions' => [['type' => 'contains', 'value' => 'yes']],
    ]);

    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/eval-suites/{$suite->id}/runs")
        ->assertCreated()
        ->assertJsonPath('data.run.status', 'pending');

    Queue::assertPushed(RunAgentEvalJob::class);
});
