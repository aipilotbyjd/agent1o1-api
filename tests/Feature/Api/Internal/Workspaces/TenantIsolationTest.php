<?php

use App\Enums\Triggers\TriggerTargetType;
use App\Models\Agents\Agent;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Templates\WorkflowTemplate;
use App\Models\User;
use App\Models\Workflows\Folder;
use App\Models\Workflows\Workflow;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

/*
 * Two tenants. Every request is made as the owner of `$this->workspace`
 * (Acme) and tries to reach something that lives in `$this->other` (Globex).
 */
beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);

    $this->outsider = User::factory()->create();
    $this->other = app(WorkspaceService::class)->create($this->outsider, ['name' => 'Globex']);

    $this->url = "/api/v1/workspaces/{$this->workspace->id}";

    Passport::actingAs($this->owner);
});

describe('folders', function () {
    it('refuses to file a workflow under another workspaces folder', function () {
        $foreign = Folder::factory()->forWorkspace($this->other)->create();
        $own = Folder::factory()->forWorkspace($this->workspace)->create();
        $workflow = Workflow::factory()->forWorkspace($this->workspace)->create();

        $this->postJson("{$this->url}/workflows", ['name' => 'Mine', 'folder_id' => $foreign->id])
            ->assertUnprocessable()->assertJsonValidationErrors('folder_id');

        $this->patchJson("{$this->url}/workflows/{$workflow->id}", ['folder_id' => $foreign->id])
            ->assertUnprocessable()->assertJsonValidationErrors('folder_id');

        $this->postJson("{$this->url}/workflows", ['name' => 'Mine', 'folder_id' => $own->id])
            ->assertCreated();
        $this->patchJson("{$this->url}/workflows/{$workflow->id}", ['folder_id' => $own->id])
            ->assertOk();
    });

    it('refuses to file an agent under another workspaces folder', function () {
        $foreign = Folder::factory()->forWorkspace($this->other)->forAgents()->create();
        $own = Folder::factory()->forWorkspace($this->workspace)->forAgents()->create();
        $agent = Agent::factory()->forWorkspace($this->workspace)->create();

        $this->postJson("{$this->url}/agents", ['name' => 'Mine', 'folder_id' => $foreign->id])
            ->assertUnprocessable()->assertJsonValidationErrors('folder_id');

        $this->patchJson("{$this->url}/agents/{$agent->id}", ['folder_id' => $foreign->id])
            ->assertUnprocessable()->assertJsonValidationErrors('folder_id');

        $this->patchJson("{$this->url}/agents/{$agent->id}", ['folder_id' => $own->id])
            ->assertOk();
    });

    it('refuses to bulk-move items into another workspaces folder', function () {
        $workflow = Workflow::factory()->forWorkspace($this->workspace)->create();
        $agent = Agent::factory()->forWorkspace($this->workspace)->create();
        $foreignWorkflowFolder = Folder::factory()->forWorkspace($this->other)->create();
        $foreignAgentFolder = Folder::factory()->forWorkspace($this->other)->forAgents()->create();

        $this->postJson("{$this->url}/folders/move-workflows", [
            'workflow_ids' => [$workflow->id],
            'folder_id' => $foreignWorkflowFolder->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('folder_id');

        $this->postJson("{$this->url}/folders/move-agents", [
            'agent_ids' => [$agent->id],
            'folder_id' => $foreignAgentFolder->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('folder_id');

        expect($workflow->fresh()->folder_id)->toBeNull()
            ->and($agent->fresh()->folder_id)->toBeNull();
    });

    it('refuses to move workflows into an agent folder, and agents into a workflow folder', function () {
        $workflow = Workflow::factory()->forWorkspace($this->workspace)->create();
        $agent = Agent::factory()->forWorkspace($this->workspace)->create();
        $workflowFolder = Folder::factory()->forWorkspace($this->workspace)->create();
        $agentFolder = Folder::factory()->forWorkspace($this->workspace)->forAgents()->create();

        $this->postJson("{$this->url}/folders/move-workflows", ['workflow_ids' => [$workflow->id], 'folder_id' => $agentFolder->id])
            ->assertUnprocessable();
        $this->postJson("{$this->url}/folders/move-agents", ['agent_ids' => [$agent->id], 'folder_id' => $workflowFolder->id])
            ->assertUnprocessable();

        $this->postJson("{$this->url}/folders/move-workflows", ['workflow_ids' => [$workflow->id], 'folder_id' => $workflowFolder->id])
            ->assertOk();
        expect($workflow->fresh()->folder_id)->toBe($workflowFolder->id);
    });

    it('refuses to nest a folder under another workspaces folder', function () {
        $foreign = Folder::factory()->forWorkspace($this->other)->create();
        $own = Folder::factory()->forWorkspace($this->workspace)->create();
        $child = Folder::factory()->forWorkspace($this->workspace)->create();

        $this->postJson("{$this->url}/folders", [
            'name' => 'Nested', 'type' => TriggerTargetType::Workflow->value, 'parent_id' => $foreign->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('parent_id');

        $this->patchJson("{$this->url}/folders/{$child->id}", ['parent_id' => $foreign->id])
            ->assertUnprocessable()->assertJsonValidationErrors('parent_id');

        $this->patchJson("{$this->url}/folders/{$child->id}", ['parent_id' => $own->id])
            ->assertOk();
    });

    it('refuses to create a workflow from a template into another workspaces folder', function () {
        $template = WorkflowTemplate::factory()->global()->create();
        $foreign = Folder::factory()->forWorkspace($this->other)->create();
        $own = Folder::factory()->forWorkspace($this->workspace)->create();

        $this->postJson("{$this->url}/workflow-templates/{$template->id}/use", ['name' => 'From template', 'folder_id' => $foreign->id])
            ->assertUnprocessable()->assertJsonValidationErrors('folder_id');

        $this->postJson("{$this->url}/workflow-templates/{$template->id}/use", ['name' => 'From template', 'folder_id' => $own->id])
            ->assertCreated();
    });
});

it('refuses agent memory about a user who is not a member of the workspace', function () {
    $agent = Agent::factory()->forWorkspace($this->workspace)->create();
    $payload = ['key' => 'tone', 'value' => 'formal'];

    $this->postJson("{$this->url}/agents/{$agent->id}/memories", [...$payload, 'user_id' => $this->outsider->id])
        ->assertUnprocessable()->assertJsonValidationErrors('user_id');

    $this->postJson("{$this->url}/agents/{$agent->id}/memories", [...$payload, 'user_id' => $this->owner->id])
        ->assertCreated();
});

it('refuses a trigger credential that belongs to another workspace', function () {
    $workflow = Workflow::factory()->forWorkspace($this->workspace)->published()->create();
    $foreign = ConnectorCredential::factory()->forWorkspace($this->other)->create();
    $own = ConnectorCredential::factory()->forWorkspace($this->workspace)->create();

    $payload = [
        'target_type' => TriggerTargetType::Workflow->value,
        'target_id' => $workflow->id,
        'type' => 'manual',
    ];

    $this->postJson("{$this->url}/triggers", [...$payload, 'credential_id' => $foreign->id])
        ->assertUnprocessable()->assertJsonValidationErrors('credential_id');

    $this->postJson("{$this->url}/triggers", [...$payload, 'credential_id' => $own->id])
        ->assertCreated();
});
