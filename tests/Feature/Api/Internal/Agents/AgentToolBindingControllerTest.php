<?php

use App\Models\Agents\Agent;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

it('attaches a node as a tool with bound config and exposed fields', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();

    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/tool-bindings", [
        'node_type' => 'call_api',
        'config' => ['url' => 'https://internal.example.com', 'method' => 'POST'],
        'exposed_fields' => ['body'],
    ]);

    $response->assertCreated();
    expect($response->json('data.tool_binding.node_type'))->toBe('call_api');
    expect($response->json('data.tool_binding.config.url'))->toBe('https://internal.example.com');
});

it('rejects attaching an unregistered node type', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();

    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/tool-bindings", [
        'node_type' => 'not_a_real_node',
    ])->assertStatus(422);
});

it('detaches a tool binding', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $binding = $agent->toolBindings()->create(['node_type' => 'call_api', 'config' => [], 'exposed_fields' => []]);

    Passport::actingAs($owner);

    $this->deleteJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/tool-bindings/{$binding->id}")
        ->assertNoContent();

    expect($agent->toolBindings()->count())->toBe(0);
});

it('attaches and detaches a workflow as a tool', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/workflows/{$workflow->id}")
        ->assertOk();
    expect($agent->workflows()->count())->toBe(1);

    $this->deleteJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/workflows/{$workflow->id}")
        ->assertNoContent();
    expect($agent->workflows()->count())->toBe(0);
});

it('404s attaching a workflow from a different workspace', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $otherWorkspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Other']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $foreignWorkflow = Workflow::factory()->forWorkspace($otherWorkspace)->create();

    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/workflows/{$foreignWorkflow->id}")
        ->assertNotFound();
});

it('rejects attaching the same node type to an agent twice', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();

    Passport::actingAs($owner);

    $url = "/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/tool-bindings";

    $this->postJson($url, ['node_type' => 'call_api'])->assertCreated();
    $this->postJson($url, ['node_type' => 'call_api'])->assertStatus(422);
});
