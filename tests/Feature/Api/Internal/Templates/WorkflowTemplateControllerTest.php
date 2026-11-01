<?php

use App\Enums\Workspaces\Role;
use App\Models\Templates\WorkflowTemplate;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

/**
 * @return array{0: Workspace, 1: User}
 */
function ownerWorkspaceForWorkflowTemplate(): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    return [$workspace, $owner];
}

it('creates a workflow template', function () {
    [$workspace, $owner] = ownerWorkspaceForWorkflowTemplate();
    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/workflow-templates", [
        'name' => 'Lead Intake',
        'graph' => ['nodes' => [], 'edges' => []],
    ]);

    $response->assertCreated();
    expect($response->json('data.workflow_template.name'))->toBe('Lead Intake');
    expect($response->json('data.workflow_template.visibility'))->toBe('private');
});

it('lists a workspace own templates and global public templates but not other workspaces private templates', function () {
    [$workspace, $owner] = ownerWorkspaceForWorkflowTemplate();
    [$otherWorkspace] = ownerWorkspaceForWorkflowTemplate();

    WorkflowTemplate::factory()->forWorkspace($workspace)->create(['name' => 'Mine']);
    WorkflowTemplate::factory()->global()->create(['name' => 'Global Pack']);
    WorkflowTemplate::factory()->forWorkspace($otherWorkspace)->create(['name' => 'Not Mine']);

    Passport::actingAs($owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/workflow-templates");

    $response->assertOk();
    $names = collect($response->json('data.workflow_templates'))->pluck('name');
    expect($names)->toContain('Mine')->toContain('Global Pack')->not->toContain('Not Mine');
});

it('saves a workflow as a template with credentials stripped from node config', function () {
    [$workspace, $owner] = ownerWorkspaceForWorkflowTemplate();
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $node = $workflow->nodes()->create([
        'key' => 'send_slack',
        'type' => 'slack.send_message',
        'config' => ['credential_id' => 42, 'channel' => '#general'],
    ]);

    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/save-as-template", [
        'name' => 'Slack Notifier',
    ]);

    $response->assertCreated();
    $template = WorkflowTemplate::find($response->json('data.workflow_template.id'));
    expect($template->source_workflow_id)->toBe($workflow->id);
    expect($template->graph['nodes'][0]['config'])->toBe(['channel' => '#general']);
    expect($template->graph['nodes'][0]['key'])->toBe($node->key);
});

it('creates a new workflow from a template', function () {
    [$workspace, $owner] = ownerWorkspaceForWorkflowTemplate();
    $template = WorkflowTemplate::factory()->forWorkspace($workspace)->create([
        'graph' => [
            'nodes' => [['key' => 'a', 'type' => 'unknown.node', 'config' => [], 'position' => null]],
            'edges' => [],
        ],
    ]);

    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/workflow-templates/{$template->id}/use", [
        'name' => 'My New Workflow',
    ]);

    $response->assertCreated();
    expect($response->json('data.workflow.name'))->toBe('My New Workflow');
    expect($response->json('data.workflow.nodes'))->toHaveCount(1);
    expect($template->fresh()->usage_count)->toBe(1);
});

it('404s using a private template that belongs to a different workspace', function () {
    [$workspace, $owner] = ownerWorkspaceForWorkflowTemplate();
    [$otherWorkspace] = ownerWorkspaceForWorkflowTemplate();
    $foreign = WorkflowTemplate::factory()->forWorkspace($otherWorkspace)->create();

    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/workflow-templates/{$foreign->id}/use", ['name' => 'x'])
        ->assertNotFound();
});

it('deletes a workspace owned template', function () {
    [$workspace, $owner] = ownerWorkspaceForWorkflowTemplate();
    $template = WorkflowTemplate::factory()->forWorkspace($workspace)->create();

    Passport::actingAs($owner);

    $this->deleteJson("/api/v1/workspaces/{$workspace->id}/workflow-templates/{$template->id}")->assertNoContent();
    expect(WorkflowTemplate::find($template->id))->toBeNull();
});

it('lets a viewer read templates but not manage them', function () {
    [$workspace, $owner] = ownerWorkspaceForWorkflowTemplate();
    $viewer = User::factory()->create();
    $workspace->members()->create(['user_id' => $viewer->id, 'role' => Role::Viewer, 'joined_at' => now()]);

    Passport::actingAs($viewer);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/workflow-templates")->assertOk();
    $this->postJson("/api/v1/workspaces/{$workspace->id}/workflow-templates", [
        'name' => 'x',
        'graph' => ['nodes' => [], 'edges' => []],
    ])->assertForbidden();
});

it('lists featured templates first, then by their order, then the newest', function () {
    [$workspace, $owner] = ownerWorkspaceForWorkflowTemplate();

    WorkflowTemplate::factory()->global()->create(['name' => 'Plain', 'is_featured' => false, 'sort_order' => 0]);
    WorkflowTemplate::factory()->global()->create(['name' => 'Featured second', 'is_featured' => true, 'sort_order' => 2]);
    WorkflowTemplate::factory()->global()->create(['name' => 'Featured first', 'is_featured' => true, 'sort_order' => 1]);
    WorkflowTemplate::factory()->forWorkspace($workspace)->create(['name' => 'Mine', 'is_featured' => false, 'sort_order' => 0]);
    Passport::actingAs($owner);

    $names = collect($this->getJson("/api/v1/workspaces/{$workspace->id}/workflow-templates")->json('data.workflow_templates'))->pluck('name')->all();

    expect(array_slice($names, 0, 2))->toBe(['Featured first', 'Featured second'])
        ->and($names)->toContain('Plain', 'Mine');
});
