<?php

use App\Agents\Internal\WorkflowBuilderAgent;
use App\Enums\Role;
use App\Models\User;
use App\Models\Workflow;
use App\Models\Workspace;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(PlanSeeder::class);

    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['owner_id' => $this->user->id]);
    $this->workspace->members()->attach($this->user->id, [
        'id' => Str::uuid()->toString(),
        'role' => Role::Owner,
        'joined_at' => now(),
    ]);

    WorkflowBuilderAgent::fake([
        [
            'workflow_name' => 'Slack Alerter',
            'workflow_description' => 'Posts a Slack message on a webhook.',
            'nodes' => [
                ['key' => 'trigger-1', 'type' => 'trigger', 'name' => 'Webhook', 'config' => [], 'position' => ['x' => 0, 'y' => 0]],
                ['key' => 'slack-1', 'type' => 'slack', 'name' => 'Notify', 'config' => [], 'position' => ['x' => 250, 'y' => 0]],
            ],
            'edges' => [
                ['source' => 'trigger-1', 'target' => 'slack-1', 'source_handle' => 'output', 'target_handle' => 'input'],
            ],
        ],
    ]);
});

test('the builder generates a workflow definition from a prompt', function () {
    $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/workflow-builder", [
            'prompt' => 'Send a Slack message when a webhook fires',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Slack Alerter')
        ->assertJsonPath('data.nodes.0.id', 'trigger-1');
});

test('the builder can generate and save the workflow', function () {
    $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/workflow-builder", [
            'prompt' => 'Send a Slack message when a webhook fires',
            'save' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Slack Alerter');

    $workflow = Workflow::where('workspace_id', $this->workspace->id)->first();
    expect($workflow)->not->toBeNull()
        ->and($workflow->currentVersion->nodes_data)->toHaveCount(2);
});
