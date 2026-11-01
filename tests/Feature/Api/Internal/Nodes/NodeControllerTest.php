<?php

use App\Enums\Workspaces\Role;
use App\Models\Nodes\CustomNode;
use App\Models\Nodes\NodeCategory;
use App\Models\Runs\NodeRun;
use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Workflows\NodeRegistry;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

/**
 * @return array{0: Workspace, 1: User}
 */
function ownerWorkspaceForNodes(): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    return [$workspace, $owner];
}

it('lists the builtin catalog plus workspace custom nodes', function () {
    [$workspace, $owner] = ownerWorkspaceForNodes();
    $category = NodeCategory::factory()->create();
    CustomNode::factory()->forWorkspace($workspace)->inCategory($category)->create(['name' => 'My Custom Node']);

    Passport::actingAs($owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/nodes");

    $response->assertOk();
    $builtinCount = count(app(NodeRegistry::class)->catalog());
    expect($response->json('data.nodes'))->toHaveCount($builtinCount + 1);

    $types = collect($response->json('data.nodes'))->pluck('type');
    expect($types)->toContain('slack_post_message');

    $custom = collect($response->json('data.nodes'))->firstWhere('name', 'My Custom Node');
    expect($custom['is_custom'])->toBeTrue();
});

it('does not leak another workspace custom node in the listing', function () {
    [$workspace, $owner] = ownerWorkspaceForNodes();
    [$otherWorkspace] = ownerWorkspaceForNodes();
    $category = NodeCategory::factory()->create();
    CustomNode::factory()->forWorkspace($otherWorkspace)->inCategory($category)->create(['name' => 'Foreign Node']);

    Passport::actingAs($owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/nodes");

    $names = collect($response->json('data.nodes'))->pluck('name');
    expect($names)->not->toContain('Foreign Node');
});

it('creates a custom node with a generated unique type', function () {
    [$workspace, $owner] = ownerWorkspaceForNodes();
    $category = NodeCategory::factory()->create();

    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/nodes", [
        'category_id' => $category->id,
        'name' => 'My Node',
        'config_schema' => ['type' => 'object', 'properties' => []],
    ]);

    $response->assertCreated();
    expect($response->json('data.node.type'))->toBe('my_node');
    expect($response->json('data.node.is_custom'))->toBeTrue();

    $again = $this->postJson("/api/v1/workspaces/{$workspace->id}/nodes", [
        'category_id' => $category->id,
        'name' => 'My Node',
        'config_schema' => ['type' => 'object', 'properties' => []],
    ]);

    expect($again->json('data.node.type'))->toBe('my_node_2');
});

it('404s reading or modifying a custom node from a different workspace', function () {
    [$workspace, $owner] = ownerWorkspaceForNodes();
    [$otherWorkspace] = ownerWorkspaceForNodes();
    $category = NodeCategory::factory()->create();
    $foreign = CustomNode::factory()->forWorkspace($otherWorkspace)->inCategory($category)->create();

    Passport::actingAs($owner);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/nodes/{$foreign->id}")->assertNotFound();
    $this->patchJson("/api/v1/workspaces/{$workspace->id}/nodes/{$foreign->id}", ['name' => 'x'])->assertNotFound();
    $this->deleteJson("/api/v1/workspaces/{$workspace->id}/nodes/{$foreign->id}")->assertNotFound();
});

it('updates and deletes a custom node', function () {
    [$workspace, $owner] = ownerWorkspaceForNodes();
    $category = NodeCategory::factory()->create();
    $node = CustomNode::factory()->forWorkspace($workspace)->inCategory($category)->create();

    Passport::actingAs($owner);

    $this->patchJson("/api/v1/workspaces/{$workspace->id}/nodes/{$node->id}", ['name' => 'Renamed'])
        ->assertOk()
        ->assertJsonPath('data.node.name', 'Renamed');

    $this->deleteJson("/api/v1/workspaces/{$workspace->id}/nodes/{$node->id}")->assertNoContent();
    expect(CustomNode::find($node->id))->toBeNull();
});

it('filters the listing by search term', function () {
    [$workspace, $owner] = ownerWorkspaceForNodes();

    Passport::actingAs($owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/nodes?search=slack_post_message");

    $types = collect($response->json('data.nodes'))->pluck('type');
    expect($types->all())->toBe(['slack_post_message']);
});

it('filters the listing by category slug', function () {
    [$workspace, $owner] = ownerWorkspaceForNodes();
    $category = NodeCategory::factory()->create(['slug' => 'slack']);
    CustomNode::factory()->forWorkspace($workspace)->inCategory($category)->create(['name' => 'Slack helper']);

    Passport::actingAs($owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/nodes?category=slack");

    $nodes = collect($response->json('data.nodes'));
    expect($nodes->pluck('name'))->toContain('Slack helper');
    expect($nodes->where('is_custom', false)->pluck('category')->unique()->all())->toBe(['slack']);
    expect($nodes)->toHaveCount(count(glob(app_path('Nodes/Integrations/Slack/Slack*Node.php'))) + 1); // every builtin slack node + 1 custom
});

it('lists only custom nodes on the dedicated endpoint', function () {
    [$workspace, $owner] = ownerWorkspaceForNodes();
    $category = NodeCategory::factory()->create();
    CustomNode::factory()->forWorkspace($workspace)->inCategory($category)->create(['name' => 'Only Custom']);

    Passport::actingAs($owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/nodes/custom");

    $response->assertOk();
    expect($response->json('data.nodes'))->toHaveCount(1);
    expect($response->json('data.nodes.0.name'))->toBe('Only Custom');
});

it('defaults recently-used to the first builtins when there is no run history', function () {
    [$workspace, $owner] = ownerWorkspaceForNodes();

    Passport::actingAs($owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/nodes/recently-used");

    $response->assertOk();
    expect($response->json('data.is_default'))->toBeTrue();
    expect($response->json('data.nodes'))->toHaveCount(6);
});

it('ranks recently-used nodes by run frequency for this workspace', function () {
    [$workspace, $owner] = ownerWorkspaceForNodes();
    $run = Run::factory()->forWorkspace($workspace)->create();
    [$otherWorkspace] = ownerWorkspaceForNodes();
    $otherRun = Run::factory()->forWorkspace($otherWorkspace)->create();

    NodeRun::factory()->forRun($run)->create(['type' => 'slack_post_message']);
    NodeRun::factory()->forRun($run)->create(['type' => 'slack_post_message']);
    NodeRun::factory()->forRun($run)->create(['type' => 'transform']);
    NodeRun::factory()->forRun($otherRun)->create(['type' => 'github_create_issue']);

    Passport::actingAs($owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/nodes/recently-used");

    $response->assertOk();
    expect($response->json('data.is_default'))->toBeFalse();
    $types = collect($response->json('data.nodes'))->pluck('type');
    expect($types->first())->toBe('slack_post_message');
    expect($types)->toContain('transform');
    expect($types)->not->toContain('github_create_issue');
});

it('lets a viewer read nodes but not create or manage them', function () {
    [$workspace, $owner] = ownerWorkspaceForNodes();
    $viewer = User::factory()->create();
    $workspace->members()->create(['user_id' => $viewer->id, 'role' => Role::Viewer, 'joined_at' => now()]);
    $category = NodeCategory::factory()->create();

    Passport::actingAs($viewer);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/nodes")->assertOk();
    $this->postJson("/api/v1/workspaces/{$workspace->id}/nodes", [
        'category_id' => $category->id,
        'name' => 'Nope',
        'config_schema' => ['type' => 'object', 'properties' => []],
    ])->assertForbidden();
});
