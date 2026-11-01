<?php

use App\Models\User;
use App\Models\Workflows\Builder\WorkflowBuilderSession;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

it('lists draft versions newest first and restores one', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $session = WorkflowBuilderSession::factory()->forWorkspace($workspace, $owner)->create();
    $session->addNode('fetch', 'transform', ['mapping' => []]);
    $session->addNode('send', 'transform', ['mapping' => []]);
    $base = "/api/v1/workspaces/{$workspace->id}/workflow-builder-sessions/{$session->id}/versions";

    Passport::actingAs($owner);

    $versions = $this->getJson($base)->assertOk()->json('data');
    expect(array_column($versions, 'label'))->toBe(['Added node send', 'Added node fetch']);
    expect($versions[1]['node_count'])->toBe(1);

    $this->postJson("{$base}/{$versions[1]['id']}/restore")
        ->assertOk()
        ->assertJsonPath('data.session.draft_graph.nodes.0.key', 'fetch')
        ->assertJsonCount(1, 'data.session.draft_graph.nodes');
});

it('404s restoring a version from another session', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $session = WorkflowBuilderSession::factory()->forWorkspace($workspace, $owner)->create();
    $other = WorkflowBuilderSession::factory()->forWorkspace($workspace, $owner)->create();
    $other->addNode('fetch', 'transform', ['mapping' => []]);

    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/workflow-builder-sessions/{$session->id}/versions/{$other->draftVersions()->value('id')}/restore")
        ->assertNotFound();
});

it('409s a restore from behind the draft, so unseen edits are not thrown away', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $session = WorkflowBuilderSession::factory()->forWorkspace($workspace, $owner)->create();
    $session->addNode('fetch', 'transform', ['mapping' => []]);
    $version = $session->draftVersions()->sole();
    $session->addNode('send', 'transform', ['mapping' => []]);
    $url = "/api/v1/workspaces/{$workspace->id}/workflow-builder-sessions/{$session->id}/versions/{$version->id}/restore";

    Passport::actingAs($owner);

    $this->postJson($url, ['draft_lock_version' => 1])->assertConflict();
    expect($session->fresh()->currentGraph()['nodes'])->toHaveCount(2);

    $this->postJson($url, ['draft_lock_version' => 2])
        ->assertOk()
        ->assertJsonCount(1, 'data.session.draft_graph.nodes');
});
