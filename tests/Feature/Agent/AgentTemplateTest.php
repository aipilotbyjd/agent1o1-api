<?php

use App\Enums\Role;
use App\Models\Agent;
use App\Models\AgentTemplate;
use App\Models\User;
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
});

test('the public catalog lists active templates only', function () {
    AgentTemplate::factory()->count(2)->create();
    AgentTemplate::factory()->inactive()->create();

    $this->getJson('/api/v1/agent-templates')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('an inactive template returns 404 on show', function () {
    $template = AgentTemplate::factory()->inactive()->create();

    $this->getJson("/api/v1/agent-templates/{$template->id}")
        ->assertNotFound();
});

test('a non-admin cannot create templates', function () {
    $this->actingAs($this->user, 'api')
        ->postJson('/api/v1/agent-templates', [
            'name' => 'New Template',
            'category' => 'support',
            'system_prompt' => 'Be helpful.',
            'llm_provider' => 'anthropic',
            'llm_model' => 'claude-sonnet-4-6',
        ])
        ->assertForbidden();
});

test('a template can be deployed into a workspace as an inactive agent', function () {
    $template = AgentTemplate::factory()->create();

    $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/agent-templates/{$template->id}/deploy")
        ->assertCreated()
        ->assertJsonPath('data.is_active', false);

    expect(Agent::where('workspace_id', $this->workspace->id)->count())->toBe(1)
        ->and($template->fresh()->usage_count)->toBe(1);
});
