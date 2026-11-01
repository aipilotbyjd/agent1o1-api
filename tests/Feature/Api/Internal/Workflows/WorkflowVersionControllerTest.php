<?php

use App\Enums\Workspaces\Role;
use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowVersion;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

/**
 * @return array{0: Workspace, 1: User}
 */
function ownerWorkspaceForVersions(): array
{
    $owner = User::factory()->create(['name' => 'Ada Lovelace']);
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    return [$workspace, $owner];
}

/**
 * @return array{nodes: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>}
 */
function versionTestGraph(string ...$keys): array
{
    $nodes = [];

    foreach ($keys as $index => $key) {
        $nodes[] = ['key' => $key, 'type' => 'transform', 'config' => ['mapping' => []], 'position' => ['x' => $index * 300, 'y' => 120]];
    }

    $edges = [];

    for ($index = 1; $index < count($keys); $index++) {
        $edges[] = ['from' => $keys[$index - 1], 'to' => $keys[$index], 'condition' => null];
    }

    return ['nodes' => $nodes, 'edges' => $edges];
}

it('records the canvas as it was drawn when publishing', function () {
    [$workspace, $owner] = ownerWorkspaceForVersions();
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph(versionTestGraph('a', 'b'));

    $version = $workflow->publishVersion('first cut', $owner);

    expect($version->editor_graph['nodes'])->toHaveCount(2);
    expect(collect($version->editor_graph['nodes'])->firstWhere('key', 'b')['position'])->toEqual(['x' => 300, 'y' => 120]);
    expect($version->editor_graph['edges'])->toBe([['from' => 'a', 'to' => 'b', 'condition' => null]]);
});

it('lists versions newest first with who published them and how often they ran, without graphs', function () {
    [$workspace, $owner] = ownerWorkspaceForVersions();
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph(versionTestGraph('a'));
    $first = $workflow->publishVersion('first', $owner);
    $workflow->replaceGraph(versionTestGraph('a', 'b'));
    $workflow->publishVersion('second', $owner);

    Run::factory()->forWorkflow($workflow)->count(2)->create(['workflow_version_id' => $first->id]);

    Passport::actingAs($owner);

    $versions = $this->getJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/versions")
        ->assertOk()
        ->json('data.versions');

    expect(array_column($versions, 'version'))->toBe([2, 1]);
    expect($versions[0]['publisher'])->toBe(['id' => $owner->id, 'name' => 'Ada Lovelace']);
    expect($versions[0]['runs_count'])->toBe(0);
    expect($versions[1]['runs_count'])->toBe(2);
    expect($versions[0])->not->toHaveKeys(['graph', 'editor_graph']);
});

it('returns both graphs when reading one version', function () {
    [$workspace, $owner] = ownerWorkspaceForVersions();
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph(versionTestGraph('a', 'b'));
    $version = $workflow->publishVersion(null, $owner);

    Passport::actingAs($owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/versions/{$version->id}")
        ->assertOk();

    expect($response->json('data.version.graph.nodes'))->toHaveCount(2);
    expect($response->json('data.version.editor_graph.nodes.1.position'))->toEqual(['x' => 300, 'y' => 120]);
    expect($response->json('data.version.publisher.name'))->toBe('Ada Lovelace');
});

it('restores an older version into the draft without publishing it', function () {
    [$workspace, $owner] = ownerWorkspaceForVersions();
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph(versionTestGraph('a'));
    $first = $workflow->publishVersion(null, $owner);
    $workflow->replaceGraph(versionTestGraph('a', 'b', 'c'));
    $second = $workflow->publishVersion(null, $owner);

    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/versions/{$first->id}/restore")
        ->assertOk();

    expect(array_column($response->json('data.workflow.nodes'), 'key'))->toBe(['a']);
    expect($response->json('data.workflow.has_unpublished_changes'))->toBeTrue();
    expect($response->json('data.workflow.current_version_id'))->toBe($second->id);
    expect(WorkflowVersion::count())->toBe(2);
});

it('discards draft changes by restoring the live version', function () {
    [$workspace, $owner] = ownerWorkspaceForVersions();
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph(versionTestGraph('a', 'b'));
    $live = $workflow->publishVersion(null, $owner);
    $workflow->replaceGraph(versionTestGraph('a', 'b', 'c'));

    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/versions/{$live->id}/restore")
        ->assertOk();

    expect(array_column($response->json('data.workflow.nodes'), 'key'))->toEqualCanonicalizing(['a', 'b']);
    expect($response->json('data.workflow.has_unpublished_changes'))->toBeFalse();
});

it('keeps pinned sample data on nodes that survive a restore', function () {
    [$workspace, $owner] = ownerWorkspaceForVersions();
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph(versionTestGraph('a'));
    $first = $workflow->publishVersion(null, $owner);
    $workflow->replaceGraph(versionTestGraph('a', 'b'));
    $workflow->nodes()->where('key', 'a')->update(['pinned_data' => json_encode(['ok' => true])]);

    $workflow->restoreVersion($first);

    expect($workflow->nodes()->where('key', 'a')->value('pinned_data'))->not->toBeNull();
});

it('restores a version published before the canvas was recorded, without positions', function () {
    [$workspace, $owner] = ownerWorkspaceForVersions();
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph(versionTestGraph('a', 'b'));
    $legacy = $workflow->publishVersion(null, $owner);
    $legacy->forceFill(['editor_graph' => null])->save();
    $workflow->replaceGraph(versionTestGraph('x'));

    $workflow->restoreVersion($legacy->fresh());

    expect($workflow->nodes()->pluck('key')->all())->toEqualCanonicalizing(['a', 'b']);
    expect($workflow->nodes()->pluck('position')->filter()->all())->toBe([]);
    expect($workflow->edges()->count())->toBe(1);
});

it('404s restoring a version that belongs to another workflow', function () {
    [$workspace, $owner] = ownerWorkspaceForVersions();
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $other = Workflow::factory()->forWorkspace($workspace)->create();
    $other->replaceGraph(versionTestGraph('a'));
    $foreignVersion = $other->publishVersion(null, $owner);

    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/versions/{$foreignVersion->id}/restore")
        ->assertNotFound();
});

it('does not let a viewer restore a version', function () {
    [$workspace, $owner] = ownerWorkspaceForVersions();
    $viewer = User::factory()->create();
    $workspace->members()->create(['user_id' => $viewer->id, 'role' => Role::Viewer, 'joined_at' => now()]);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph(versionTestGraph('a'));
    $version = $workflow->publishVersion(null, $owner);

    Passport::actingAs($viewer);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/versions/{$version->id}/restore")
        ->assertForbidden();
});

it('turns a loop-mode step back into the step it was compiled from for older versions', function () {
    [$workspace, $owner] = ownerWorkspaceForVersions();
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph(['nodes' => [[
        'key' => 'each',
        'type' => 'transform',
        'config' => ['mapping' => ['n' => 'input.item'], '_loop' => ['items_path' => 'input.items', 'max_concurrent' => 3]],
    ]], 'edges' => []]);
    $legacy = $workflow->publishVersion(null, $owner);
    $legacy->forceFill(['editor_graph' => null])->save();

    $node = $legacy->fresh()->editorGraph()['nodes'][0];

    expect($node['type'])->toBe('transform');
    expect($node['config']['mapping'])->toBe(['n' => 'input.item']);
    expect($node['config']['_loop'])->toBe(['items_path' => 'input.items', 'max_concurrent' => 3, 'on_item_error' => 'fail_fast']);
});

it('leaves a loop step someone added themselves as it is', function () {
    [$workspace, $owner] = ownerWorkspaceForVersions();
    $child = Workflow::factory()->forWorkspace($workspace)->create();
    $child->replaceGraph(versionTestGraph('a'));
    $child->publishVersion(null, $owner);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph(['nodes' => [[
        'key' => 'each',
        'type' => 'loop',
        'config' => ['items_path' => 'input.items', 'workflow_id' => $child->id],
    ]], 'edges' => []]);
    $legacy = $workflow->publishVersion(null, $owner);
    $legacy->forceFill(['editor_graph' => null])->save();

    expect($legacy->fresh()->editorGraph()['nodes'][0]['type'])->toBe('loop');
});
