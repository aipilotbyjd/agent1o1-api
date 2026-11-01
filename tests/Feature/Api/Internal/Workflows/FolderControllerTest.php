<?php

use App\Enums\Workspaces\Role;
use App\Models\Agents\Agent;
use App\Models\User;
use App\Models\Workflows\Folder;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

/**
 * @return array{0: Workspace, 1: User}
 */
function ownerWorkspaceForFolder(): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    return [$workspace, $owner];
}

it('creates a folder', function () {
    [$workspace, $owner] = ownerWorkspaceForFolder();
    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/folders", [
        'name' => 'Marketing',
        'type' => 'workflow',
        'color' => '#ff0000',
    ]);

    $response->assertCreated();
    expect($response->json('data.folder.name'))->toBe('Marketing');
    expect($response->json('data.folder.type'))->toBe('workflow');
});

it('creates an agent folder', function () {
    [$workspace, $owner] = ownerWorkspaceForFolder();
    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/folders", [
        'name' => 'Support Bots',
        'type' => 'agent',
    ]);

    $response->assertCreated();
    expect($response->json('data.folder.type'))->toBe('agent');
});

it('rejects an agent folder nested under a workflow folder', function () {
    [$workspace, $owner] = ownerWorkspaceForFolder();
    $workflowFolder = Folder::factory()->forWorkspace($workspace)->create();
    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/folders", [
        'name' => 'Nested',
        'type' => 'agent',
        'parent_id' => $workflowFolder->id,
    ])->assertUnprocessable();
});

it('lists top-level folders with nested children and workflow counts', function () {
    [$workspace, $owner] = ownerWorkspaceForFolder();
    $parent = Folder::factory()->forWorkspace($workspace)->create(['name' => 'Parent']);
    Folder::factory()->forParent($parent)->create(['name' => 'Child']);
    Workflow::factory()->forWorkspace($workspace)->create(['folder_id' => $parent->id]);

    Passport::actingAs($owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/folders");

    $response->assertOk();
    expect($response->json('data.folders'))->toHaveCount(1);
    expect($response->json('data.folders.0.children'))->toHaveCount(1);
    expect($response->json('data.folders.0.workflow_count'))->toBe(1);
});

it('lists agent folders separately from workflow folders', function () {
    [$workspace, $owner] = ownerWorkspaceForFolder();
    Folder::factory()->forWorkspace($workspace)->create(['name' => 'Workflow Folder']);
    $agentFolder = Folder::factory()->forWorkspace($workspace)->forAgents()->create(['name' => 'Agent Folder']);
    Agent::factory()->forWorkspace($workspace)->create(['folder_id' => $agentFolder->id]);

    Passport::actingAs($owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/folders?type=agent");

    $response->assertOk();
    expect($response->json('data.folders'))->toHaveCount(1);
    expect($response->json('data.folders.0.name'))->toBe('Agent Folder');
    expect($response->json('data.folders.0.agent_count'))->toBe(1);
});

it('updates a folder', function () {
    [$workspace, $owner] = ownerWorkspaceForFolder();
    $folder = Folder::factory()->forWorkspace($workspace)->create();

    Passport::actingAs($owner);

    $response = $this->patchJson("/api/v1/workspaces/{$workspace->id}/folders/{$folder->id}", [
        'name' => 'Renamed',
    ]);

    $response->assertOk();
    expect($response->json('data.folder.name'))->toBe('Renamed');
});

it('404s updating a folder that belongs to a different workspace', function () {
    [$workspace, $owner] = ownerWorkspaceForFolder();
    [$otherWorkspace] = ownerWorkspaceForFolder();
    $foreign = Folder::factory()->forWorkspace($otherWorkspace)->create();

    Passport::actingAs($owner);

    $this->patchJson("/api/v1/workspaces/{$workspace->id}/folders/{$foreign->id}", ['name' => 'x'])
        ->assertNotFound();
});

it('deletes a folder and nulls out its workflows folder_id', function () {
    [$workspace, $owner] = ownerWorkspaceForFolder();
    $folder = Folder::factory()->forWorkspace($workspace)->create();
    $workflow = Workflow::factory()->forWorkspace($workspace)->create(['folder_id' => $folder->id]);

    Passport::actingAs($owner);

    $this->deleteJson("/api/v1/workspaces/{$workspace->id}/folders/{$folder->id}")->assertNoContent();

    expect($workflow->fresh()->folder_id)->toBeNull();
});

it('bulk moves workflows into a folder', function () {
    [$workspace, $owner] = ownerWorkspaceForFolder();
    $folder = Folder::factory()->forWorkspace($workspace)->create();
    $workflows = Workflow::factory()->forWorkspace($workspace)->count(2)->create();

    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/folders/move-workflows", [
        'workflow_ids' => $workflows->pluck('id')->all(),
        'folder_id' => $folder->id,
    ]);

    $response->assertOk();
    expect($workflows->first()->fresh()->folder_id)->toBe($folder->id);
});

it('bulk moves agents into a folder', function () {
    [$workspace, $owner] = ownerWorkspaceForFolder();
    $folder = Folder::factory()->forWorkspace($workspace)->forAgents()->create();
    $agents = Agent::factory()->forWorkspace($workspace)->count(2)->create();

    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/folders/move-agents", [
        'agent_ids' => $agents->pluck('id')->all(),
        'folder_id' => $folder->id,
    ]);

    $response->assertOk();
    expect($agents->first()->fresh()->folder_id)->toBe($folder->id);
});

it('lets a viewer read folders but not manage them', function () {
    [$workspace, $owner] = ownerWorkspaceForFolder();
    $viewer = User::factory()->create();
    $workspace->members()->create(['user_id' => $viewer->id, 'role' => Role::Viewer, 'joined_at' => now()]);

    Passport::actingAs($viewer);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/folders")->assertOk();
    $this->postJson("/api/v1/workspaces/{$workspace->id}/folders", ['name' => 'x', 'type' => 'workflow'])->assertForbidden();
});
