<?php

use App\Models\Agents\Agent;
use App\Models\Ai\ModelCatalog;
use App\Models\Templates\AgentTemplate;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

/**
 * @return array{0: Workspace, 1: User}
 */
function ownerWorkspaceForAgentTemplate(): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    return [$workspace, $owner];
}

it('creates an agent template', function () {
    [$workspace, $owner] = ownerWorkspaceForAgentTemplate();
    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/agent-templates", [
        'name' => 'Support Bot',
        'config' => ['instructions' => 'You help customers.'],
    ]);

    $response->assertCreated();
    expect($response->json('data.agent_template.name'))->toBe('Support Bot');
});

it('lists a workspace own templates and global public templates', function () {
    [$workspace, $owner] = ownerWorkspaceForAgentTemplate();
    [$otherWorkspace] = ownerWorkspaceForAgentTemplate();

    AgentTemplate::factory()->forWorkspace($workspace)->create(['name' => 'Mine']);
    AgentTemplate::factory()->global()->create(['name' => 'Global Bot']);
    AgentTemplate::factory()->forWorkspace($otherWorkspace)->create(['name' => 'Not Mine']);

    Passport::actingAs($owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/agent-templates");

    $response->assertOk();
    $names = collect($response->json('data.agent_templates'))->pluck('name');
    expect($names)->toContain('Mine')->toContain('Global Bot')->not->toContain('Not Mine');
});

it('saves an agent as a template with credentials stripped from tool bindings', function () {
    [$workspace, $owner] = ownerWorkspaceForAgentTemplate();
    $agent = Agent::factory()->forWorkspace($workspace)->create(['instructions' => 'Be nice.']);
    $agent->toolBindings()->create([
        'node_type' => 'slack.send_message',
        'config' => ['credential_id' => 7, 'channel' => '#support'],
    ]);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $agent->workflows()->attach($workflow);

    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/save-as-template", [
        'name' => 'Support Bot Template',
    ]);

    $response->assertCreated();
    $template = AgentTemplate::find($response->json('data.agent_template.id'));
    expect($template->source_agent_id)->toBe($agent->id);
    expect($template->config['instructions'])->toBe('Be nice.');
    expect($template->config['tool_bindings'][0]['config'])->toBe(['channel' => '#support']);
    expect($template->config['workflow_ids'])->toBe([$workflow->id]);
});

it('creates a new agent from a template including its tool bindings', function () {
    [$workspace, $owner] = ownerWorkspaceForAgentTemplate();
    $template = AgentTemplate::factory()->forWorkspace($workspace)->create([
        'config' => [
            'instructions' => 'You are a helpful assistant.',
            'provider' => 'anthropic',
            'tool_bindings' => [
                ['node_type' => 'slack.send_message', 'config' => ['channel' => '#general'], 'exposed_fields' => null],
            ],
        ],
    ]);

    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/agent-templates/{$template->id}/use", [
        'name' => 'My New Agent',
    ]);

    $response->assertCreated();
    $agent = Agent::find($response->json('data.agent.id'));
    expect($agent->name)->toBe('My New Agent');
    expect($agent->toolBindings)->toHaveCount(1);
    expect($template->fresh()->usage_count)->toBe(1);
});

it('404s using a private agent template that belongs to a different workspace', function () {
    [$workspace, $owner] = ownerWorkspaceForAgentTemplate();
    [$otherWorkspace] = ownerWorkspaceForAgentTemplate();
    $foreign = AgentTemplate::factory()->forWorkspace($otherWorkspace)->create();

    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/agent-templates/{$foreign->id}/use", ['name' => 'x'])
        ->assertNotFound();
});

it('carries a template\'s look and the chosen model onto the new agent', function () {
    [$workspace, $owner] = ownerWorkspaceForAgentTemplate();
    $catalog = ModelCatalog::factory()->create();
    $template = AgentTemplate::factory()->create([
        'workspace_id' => $workspace->id,
        'icon' => 'megaphone',
        'color' => 'teal',
        'config' => ['instructions' => 'You do outreach.'],
    ]);

    Passport::actingAs($owner);

    $agent = Agent::find($this->postJson("/api/v1/workspaces/{$workspace->id}/agent-templates/{$template->id}/use", [
        'name' => 'Outreach',
        'model_catalog_id' => $catalog->id,
    ])->assertCreated()->json('data.agent.id'));

    expect($agent->icon)->toBe('megaphone');
    expect($agent->color)->toBe('teal');
    expect($agent->model_catalog_id)->toBe($catalog->id);
    expect($agent->instructions)->toBe('You do outreach.');
});

it('seeds global starter templates every workspace can see', function () {
    $this->syncSetup('agent-templates');
    $this->syncSetup('agent-templates');

    [$workspace, $owner] = ownerWorkspaceForAgentTemplate();
    Passport::actingAs($owner);

    $templates = $this->getJson("/api/v1/workspaces/{$workspace->id}/agent-templates")->assertOk()->json('data.agent_templates');

    expect($templates)->toHaveCount(count(require database_path('setup/agent-templates.php')));
    expect(collect($templates)->every(fn (array $template): bool => in_array($template['icon'], Agent::ICONS, true)
        && in_array($template['color'], Agent::COLORS, true)
        && filled($template['config']['instructions'])))->toBeTrue();
});
