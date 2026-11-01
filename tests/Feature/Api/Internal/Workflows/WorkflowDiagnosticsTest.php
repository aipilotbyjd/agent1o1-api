<?php

use App\Enums\NodeRunStatus;
use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Services\Workflows\NodeTester;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

/**
 * @return array{0: Workflow, 1: User, 2: Workspace}
 */
function diagnosticsWorkflow(array $nodes, array $edges = []): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph(['nodes' => $nodes, 'edges' => $edges]);

    return [$workflow->fresh(), $owner, $workspace];
}

it('reports a saved draft as valid', function () {
    [$workflow, $owner, $workspace] = diagnosticsWorkflow([
        ['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => ['x' => 'input.x']]],
    ]);

    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/validate")
        ->assertOk()
        ->assertJsonPath('data.valid', true)
        ->assertJsonPath('data.issues', []);
});

it('reports issues for an unsaved graph sent in the body', function () {
    [$workflow, $owner, $workspace] = diagnosticsWorkflow([
        ['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]],
    ]);

    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/validate", [
        'graph' => [
            'nodes' => [
                ['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]],
                ['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]],
            ],
            'edges' => [],
        ],
    ]);

    $response->assertOk()->assertJsonPath('data.valid', false);
    expect($response->json('data.issues.0'))->toContain("Duplicate node key 'a'");
});

it('dry runs a graph without executing anything', function () {
    [$workflow, $owner, $workspace] = diagnosticsWorkflow(
        [
            ['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => ['x' => 'input.x']]],
            ['key' => 'b', 'type' => 'transform', 'config' => ['mapping' => ['y' => 'nodes.a.x']]],
        ],
        [['from' => 'a', 'to' => 'b']],
    );

    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/dry-run", [
        'input' => ['x' => 1],
    ]);

    $response->assertOk();
    expect($response->json('data.dry_run.issues'))->toBe([]);
    expect(array_column($response->json('data.dry_run.steps'), 'key'))->toBe(['a', 'b']);
    // Nothing executed: no run rows were created by a dry run.
    expect(Run::count())->toBe(0);
});

it('executes a single node for real and records it as a node_test run', function () {
    [$workflow, $owner, $workspace] = diagnosticsWorkflow([
        ['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => ['x' => 'input.x']]],
    ]);
    $node = $workflow->nodes()->firstWhere('key', 'a');

    Passport::actingAs($owner);

    $response = $this->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/nodes/{$node->id}/test",
        ['input' => ['x' => 'hello']],
    );

    $response->assertOk()
        ->assertJsonPath('data.node_run.status', 'completed')
        ->assertJsonPath('data.node_run.output.x', 'hello');

    $run = Run::sole();
    expect($run->trigger_type)->toBe(NodeTester::TRIGGER_TYPE);
    expect($run->workflow_version_id)->toBeNull();
});

it('tests a node against supplied upstream outputs and an unsaved config', function () {
    [$workflow, $owner, $workspace] = diagnosticsWorkflow([
        ['key' => 'b', 'type' => 'transform', 'config' => ['mapping' => ['y' => 'nodes.a.x']]],
    ]);
    $node = $workflow->nodes()->firstWhere('key', 'b');

    Passport::actingAs($owner);

    $this->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/nodes/{$node->id}/test",
        [
            'nodes' => ['a' => ['x' => 'from-upstream']],
            'config' => ['mapping' => ['renamed' => 'nodes.a.x']],
        ],
    )
        ->assertOk()
        ->assertJsonPath('data.node_run.output.renamed', 'from-upstream');
});

it('returns the failure on the node run rather than a 500 when a node throws', function () {
    [$workflow, $owner, $workspace] = diagnosticsWorkflow([
        ['key' => 'call', 'type' => 'call_api', 'config' => [
            'method' => 'GET',
            'url' => 'http://127.0.0.1:1/unreachable',
            'timeout_seconds' => 1,
        ]],
    ]);
    $node = $workflow->nodes()->firstWhere('key', 'call');

    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/nodes/{$node->id}/test");

    $response->assertOk()->assertJsonPath('data.node_run.status', NodeRunStatus::Failed->value);
    expect($response->json('data.node_run.error'))->not->toBeNull();
});

it('refuses to test a flow-control node on its own', function () {
    [$workflow, $owner, $workspace] = diagnosticsWorkflow([
        ['key' => 'gate', 'type' => 'human_approval', 'config' => []],
    ]);
    $node = $workflow->nodes()->firstWhere('key', 'gate');

    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/nodes/{$node->id}/test")
        ->assertStatus(422);
});

it('excludes node test runs from the run list when asked', function () {
    [$workflow, $owner, $workspace] = diagnosticsWorkflow([
        ['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]],
    ]);
    $node = $workflow->nodes()->firstWhere('key', 'a');

    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/nodes/{$node->id}/test")->assertOk();

    $all = $this->getJson("/api/v1/workspaces/{$workspace->id}/runs");
    expect($all->json('data'))->toHaveCount(1);

    $filtered = $this->getJson("/api/v1/workspaces/{$workspace->id}/runs?exclude_trigger_type=".NodeTester::TRIGGER_TYPE);
    expect($filtered->json('data'))->toHaveCount(0);
});
