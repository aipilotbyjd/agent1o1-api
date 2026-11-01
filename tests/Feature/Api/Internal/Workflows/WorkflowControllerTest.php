<?php

use App\Enums\Workspaces\Role;
use App\Exceptions\WorkflowValidationException;
use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workflows\Folder;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Carbon;
use Laravel\Passport\Passport;

/**
 * @return array{0: Workspace, 1: User}
 */
function ownerWorkspaceForWorkflow(): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    return [$workspace, $owner];
}

it('creates a draft workflow with a generated slug', function () {
    [$workspace, $owner] = ownerWorkspaceForWorkflow();
    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/workflows", [
        'name' => 'Weekly Digest',
    ]);

    $response->assertCreated();
    expect($response->json('data.workflow.slug'))->toStartWith('weekly-digest-');
    expect($response->json('data.workflow.status'))->toBe('draft');
    expect($response->json('data.workflow.is_published'))->toBeFalse();
});

it('404s reading a workflow that belongs to a different workspace', function () {
    [$workspace, $owner] = ownerWorkspaceForWorkflow();
    [$otherWorkspace] = ownerWorkspaceForWorkflow();

    $foreign = Workflow::factory()->forWorkspace($otherWorkspace)->create();

    Passport::actingAs($owner);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/workflows/{$foreign->id}")->assertNotFound();
});

it('excludes internal (loop-mode) workflows from the workspace listing', function () {
    [$workspace, $owner] = ownerWorkspaceForWorkflow();
    Workflow::factory()->forWorkspace($workspace)->create();
    Workflow::factory()->forWorkspace($workspace)->create(['is_internal' => true]);

    Passport::actingAs($owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/workflows")->assertOk();

    expect($response->json('data.workflows'))->toHaveCount(1);
});

it('saves a draft graph via the builder endpoint', function () {
    [$workspace, $owner] = ownerWorkspaceForWorkflow();
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    Passport::actingAs($owner);

    $response = $this->putJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/graph", [
        'nodes' => [
            ['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]],
            ['key' => 'b', 'type' => 'transform', 'config' => ['mapping' => []]],
        ],
        'edges' => [
            ['from' => 'a', 'to' => 'b'],
        ],
    ]);

    $response->assertOk();
    expect($response->json('data.workflow.nodes'))->toHaveCount(2);
    expect($response->json('data.workflow.has_unpublished_changes'))->toBeTrue();
});

it('rejects a draft graph with a schema-invalid node config', function () {
    [$workspace, $owner] = ownerWorkspaceForWorkflow();
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    Passport::actingAs($owner);

    $response = $this->putJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/graph", [
        'nodes' => [
            ['key' => 'a', 'type' => 'call_api', 'config' => ['url' => 'https://example.com', 'method' => 123]],
        ],
        'edges' => [],
    ]);

    $response->assertStatus(422);
    expect(collect($response->json('errors'))->implode(' '))->toContain("Node 'a': config.method must be");
});

it('saves a draft with a node whose required fields are not filled in yet, but will not publish it', function () {
    [$workspace, $owner] = ownerWorkspaceForWorkflow();
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    Passport::actingAs($owner);

    $this->putJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/graph", [
        'nodes' => [
            ['key' => 'a', 'type' => 'ask_ai', 'config' => ['instructions' => 'You are a helpful assistant.']],
        ],
        'edges' => [],
    ])->assertOk();

    expect(fn () => $workflow->fresh()->publishVersion())
        ->toThrow(WorkflowValidationException::class);
});

it('422s a draft graph that could not be stored, leaving the saved draft untouched', function (array $graph, string $error) {
    [$workspace, $owner] = ownerWorkspaceForWorkflow();
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph([
        'nodes' => [['key' => 'kept', 'type' => 'transform', 'config' => ['mapping' => []]]],
        'edges' => [],
    ]);

    Passport::actingAs($owner);

    $response = $this->putJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/graph", $graph);

    $response->assertUnprocessable();
    expect($response->json('errors'))->toContain($error);
    expect($workflow->nodes()->pluck('key')->all())->toBe(['kept']);
})->with([
    'edge to a missing node' => [[
        'nodes' => [['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]]],
        'edges' => [['from' => 'a', 'to' => 'ghost']],
    ], "Edge references unknown target node 'ghost'."],
    'duplicate node keys' => [[
        'nodes' => [
            ['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]],
            ['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]],
        ],
        'edges' => [],
    ], "Duplicate node key 'a'."],
]);

it('stores a repeated edge once instead of refusing the save', function () {
    [$workspace, $owner] = ownerWorkspaceForWorkflow();
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    Passport::actingAs($owner);

    $this->putJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/graph", [
        'nodes' => [
            ['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]],
            ['key' => 'b', 'type' => 'transform', 'config' => ['mapping' => []]],
        ],
        'edges' => [
            ['from' => 'a', 'to' => 'b'],
            ['from' => 'a', 'to' => 'b'],
            ['from' => 'a', 'to' => 'b', 'condition' => 'error'],
            ['from' => 'a', 'to' => 'b', 'condition' => 'error'],
        ],
    ])->assertOk();

    expect($workflow->edges()->pluck('condition')->sort()->values()->all())->toEqualCanonicalizing([null, 'error']);
});

it('publishes a valid draft as a new version', function () {
    [$workspace, $owner] = ownerWorkspaceForWorkflow();
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    Passport::actingAs($owner);

    $this->putJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/graph", [
        'nodes' => [['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]]],
        'edges' => [],
    ])->assertOk();

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/versions", [
        'notes' => 'first cut',
    ]);

    $response->assertCreated();
    expect($response->json('data.version.version'))->toBe(1);
    expect($response->json('data.workflow.is_published'))->toBeTrue();
    expect($response->json('data.workflow.has_unpublished_changes'))->toBeFalse();
});

it('rejects publishing a graph that fails GraphValidator', function () {
    [$workspace, $owner] = ownerWorkspaceForWorkflow();
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    Passport::actingAs($owner);

    $this->putJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/graph", [
        'nodes' => [
            ['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]],
            ['key' => 'b', 'type' => 'transform', 'config' => ['mapping' => []]],
        ],
        'edges' => [
            ['from' => 'a', 'to' => 'b'],
            ['from' => 'b', 'to' => 'a'],
        ],
    ])->assertOk();

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/versions");

    $response->assertStatus(422);
    expect($response->json('errors'))->toBe(['The graph contains a cycle.']);
});

it('duplicates a workflow with its graph, same folder, and a new slug', function () {
    [$workspace, $owner] = ownerWorkspaceForWorkflow();
    $folder = Folder::factory()->forWorkspace($workspace)->create();
    $workflow = Workflow::factory()->forWorkspace($workspace)->create(['name' => 'Weekly Digest', 'folder_id' => $folder->id]);
    $workflow->nodes()->create(['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]]);

    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/duplicate");

    $response->assertCreated();
    expect($response->json('data.workflow.name'))->toBe('Weekly Digest (copy)');
    expect($response->json('data.workflow.folder_id'))->toBe($folder->id);
    expect($response->json('data.workflow.slug'))->not->toBe($workflow->slug);
    expect($response->json('data.workflow.nodes'))->toHaveCount(1);
    expect(Workflow::count())->toBe(2);
});

it('404s duplicating a workflow that belongs to a different workspace', function () {
    [$workspace, $owner] = ownerWorkspaceForWorkflow();
    [$otherWorkspace] = ownerWorkspaceForWorkflow();
    $foreign = Workflow::factory()->forWorkspace($otherWorkspace)->create();

    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/workflows/{$foreign->id}/duplicate")->assertNotFound();
});

it('lets a viewer read workflows but not manage or publish them', function () {
    [$workspace, $owner] = ownerWorkspaceForWorkflow();
    $viewer = User::factory()->create();
    $workspace->members()->create(['user_id' => $viewer->id, 'role' => Role::Viewer, 'joined_at' => now()]);

    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    Passport::actingAs($viewer);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/workflows")->assertOk();
    $this->patchJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}", ['name' => 'x'])->assertForbidden();
    $this->postJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/versions")->assertForbidden();
});

it('favorites and unfavorites a workflow per user', function () {
    [$workspace, $owner] = ownerWorkspaceForWorkflow();
    $otherMember = User::factory()->create();
    $workspace->members()->create(['user_id' => $otherMember->id, 'role' => Role::Member, 'joined_at' => now()]);

    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    Passport::actingAs($owner);
    $this->patchJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/favorite", ['is_favorite' => true])
        ->assertOk();

    $listAsOwner = $this->getJson("/api/v1/workspaces/{$workspace->id}/workflows")->json('data.workflows');
    expect(collect($listAsOwner)->firstWhere('id', $workflow->id)['is_favorite'])->toBeTrue();

    Passport::actingAs($otherMember);
    $listAsOther = $this->getJson("/api/v1/workspaces/{$workspace->id}/workflows")->json('data.workflows');
    expect(collect($listAsOther)->firstWhere('id', $workflow->id)['is_favorite'])->toBeFalse();

    Passport::actingAs($owner);
    $this->patchJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/favorite", ['is_favorite' => false])
        ->assertOk();

    $listAfterUnfavorite = $this->getJson("/api/v1/workspaces/{$workspace->id}/workflows")->json('data.workflows');
    expect(collect($listAfterUnfavorite)->firstWhere('id', $workflow->id)['is_favorite'])->toBeFalse();
});

it('includes node counts, node types, and last run time on the list', function () {
    [$workspace, $owner] = ownerWorkspaceForWorkflow();
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->nodes()->create(['key' => 'a', 'type' => 'slack.send_message', 'config' => []]);
    $workflow->nodes()->create(['key' => 'b', 'type' => 'slack.send_message', 'config' => []]);
    $run = Run::factory()->forWorkflow($workflow)->completed()->create();

    Passport::actingAs($owner);
    $listed = collect($this->getJson("/api/v1/workspaces/{$workspace->id}/workflows")->json('data.workflows'))
        ->firstWhere('id', $workflow->id);

    expect($listed['nodes_count'])->toBe(2);
    expect($listed['node_types'])->toBe(['slack.send_message']);
    expect($listed['last_run_at'])->not->toBeNull();
    expect(Carbon::parse($listed['last_run_at']))->toEqual($run->finished_at);
});

it('says exactly which step and field stop a workflow from publishing, in plain words', function () {
    [$workspace, $owner] = ownerWorkspaceForWorkflow();
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'ask', 'type' => 'ask_ai', 'config' => ['instructions' => 'Be brief.', 'prompt' => '   ']],
        ],
        'edges' => [],
    ]);

    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/versions")->assertUnprocessable();

    expect($response->json('issues'))->toContain(['node_key' => 'ask', 'field' => 'prompt', 'message' => 'Prompt is required.'])
        ->and($response->json('errors'))->toContain("Node 'ask': config.prompt is required.");
});

it('saves a draft whose field the author cleared (sent as null), and still refuses to publish it', function () {
    [$workspace, $owner] = ownerWorkspaceForWorkflow();
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    Passport::actingAs($owner);

    $this->putJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/graph", [
        'nodes' => [['key' => 'ask', 'type' => 'ask_ai', 'config' => ['instructions' => 'Be brief.', 'prompt' => null]]],
        'edges' => [],
    ])->assertOk();

    $this->postJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/versions")
        ->assertUnprocessable()
        ->assertJsonFragment(['node_key' => 'ask', 'field' => 'prompt', 'message' => 'Prompt is required.']);
});

it('sums up a refused publish in plain words and leaves the details to the issues', function () {
    [$workspace, $owner] = ownerWorkspaceForWorkflow();
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]],
            ['key' => 'b', 'type' => 'transform', 'config' => ['mapping' => []]],
        ],
        'edges' => [['from' => 'a', 'to' => 'b'], ['from' => 'b', 'to' => 'a']],
    ]);

    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/versions")
        ->assertUnprocessable();

    expect($response->json('message'))->toBe('One thing needs fixing before this workflow can run.');
    expect($response->json('issues'))->toHaveCount(1);
});
