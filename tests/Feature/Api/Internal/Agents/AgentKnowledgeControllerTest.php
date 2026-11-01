<?php

use App\Enums\Agents\KnowledgeMode;
use App\Enums\Workspaces\Role;
use App\Models\Agents\Agent;
use App\Models\Agents\DocumentEmbedding;
use App\Models\Agents\KnowledgeSource;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Agents\SkillInjector;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

/**
 * @return array{0: Workspace, 1: User, 2: Agent}
 */
function agentWorkspaceForKnowledge(): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    return [$workspace, $owner, Agent::factory()->forWorkspace($workspace)->create()];
}

it('lists attached collections with their mode and size, and available ones', function () {
    [$workspace, $owner, $agent] = agentWorkspaceForKnowledge();
    Passport::actingAs($owner);

    DocumentEmbedding::factory()->forWorkspace($workspace)->create(['collection' => 'support', 'source' => 'FAQ', 'chunk_text' => 'abcd']);
    DocumentEmbedding::factory()->forWorkspace($workspace)->create(['collection' => 'sales', 'chunk_text' => 'b']);
    DocumentEmbedding::factory()->forWorkspace($workspace)->create(['collection' => 'mine', 'chunk_text' => 'c', 'owner_id' => $owner->id]);
    $agent->knowledgeCollections()->create(['collection' => 'support', 'mode' => KnowledgeMode::Always]);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/knowledge");

    $response->assertOk()
        ->assertJsonPath('data.attached', [[
            'collection' => 'support',
            'mode' => 'always',
            'synced' => false,
            'documents' => 1,
            'characters' => 4,
        ]])
        ->assertJsonPath('data.always.budget', (int) config('knowledge_base.max_injected_characters'))
        ->assertJsonPath('data.always.skipped', []);
    expect($response->json('data.available'))->toBe(['sales', 'support']);
    expect($response->json('data.always.used'))->toBeGreaterThan(4);
});

it('attaches and detaches a collection', function () {
    [$workspace, $owner, $agent] = agentWorkspaceForKnowledge();
    DocumentEmbedding::factory()->forWorkspace($workspace)->create(['collection' => 'support']);
    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/knowledge/support")
        ->assertOk()
        ->assertJsonPath('data.attached.0.collection', 'support')
        ->assertJsonPath('data.attached.0.mode', 'search');

    expect($agent->knowledgeCollections()->pluck('collection')->all())->toBe(['support']);

    // Attaching the same collection twice is a no-op, not a duplicate row.
    $this->postJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/knowledge/support")->assertOk();
    expect($agent->knowledgeCollections()->count())->toBe(1);

    $this->deleteJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/knowledge/support")
        ->assertNoContent();
    expect($agent->knowledgeCollections()->count())->toBe(0);
});

it('does not let a viewer attach knowledge', function () {
    [$workspace, , $agent] = agentWorkspaceForKnowledge();
    $viewer = User::factory()->create();
    $workspace->members()->create(['user_id' => $viewer->id, 'role' => Role::Viewer, 'joined_at' => now()]);
    Passport::actingAs($viewer);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/knowledge/support")
        ->assertForbidden();
});

it('attaches a collection that has no chunks yet, scoped to the agent\'s own workspace', function () {
    [$workspace, $owner, $agent] = agentWorkspaceForKnowledge();
    $other = app(WorkspaceService::class)->create($owner, ['name' => 'Other']);
    DocumentEmbedding::factory()->forWorkspace($other)->create(['collection' => 'elsewhere', 'chunk_text' => 'Other workspace.']);
    Passport::actingAs($owner);

    // A synced source or long upload is still being read when it's attached.
    $this->postJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/knowledge/elsewhere", ['mode' => 'always'])
        ->assertOk()
        ->assertJsonPath('data.attached.0.documents', 0);

    expect(app(SkillInjector::class)->instructionsFor($agent->fresh()))->not->toContain('Other workspace.');
});

it('does not offer or attach another agent\'s artifact collection', function () {
    [$workspace, $owner, $agent] = agentWorkspaceForKnowledge();
    $collection = Agent::factory()->forWorkspace($workspace)->create()->artifactKnowledgeCollection();
    DocumentEmbedding::factory()->forWorkspace($workspace)->create(['collection' => $collection]);
    Passport::actingAs($owner);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/knowledge")
        ->assertJsonPath('data.available', []);
    $this->postJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/knowledge/".rawurlencode($collection))
        ->assertNotFound();
});

it('404s for an agent from another workspace', function () {
    [$workspace, $owner, $agent] = agentWorkspaceForKnowledge();
    // Same owner, a second workspace they're actually a member of, so the
    // 404 comes from the workspace mismatch, not from lacking permission.
    $otherWorkspace = app(WorkspaceService::class)->create($owner, ['name' => 'Other']);
    Passport::actingAs($owner);

    $this->getJson("/api/v1/workspaces/{$otherWorkspace->id}/agents/{$agent->id}/knowledge")
        ->assertNotFound();
});

it('attaches a collection in always mode, and switches its mode', function () {
    [$workspace, $owner, $agent] = agentWorkspaceForKnowledge();
    DocumentEmbedding::factory()->forWorkspace($workspace)->create(['collection' => 'policies']);
    Passport::actingAs($owner);
    $url = "/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/knowledge/policies";

    $this->postJson($url, ['mode' => 'always'])->assertOk()->assertJsonPath('data.attached.0.mode', 'always');

    // Attaching again without a mode keeps the one it has.
    $this->postJson($url)->assertOk()->assertJsonPath('data.attached.0.mode', 'always');

    $this->patchJson($url, ['mode' => 'search'])->assertOk()->assertJsonPath('data.attached.0.mode', 'search');
    expect($agent->knowledgeCollections()->sole()->mode)->toBe(KnowledgeMode::Search);
});

it('validates the mode', function () {
    [$workspace, $owner, $agent] = agentWorkspaceForKnowledge();
    DocumentEmbedding::factory()->forWorkspace($workspace)->create(['collection' => 'policies']);
    $agent->knowledgeCollections()->create(['collection' => 'policies']);
    Passport::actingAs($owner);
    $url = "/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/knowledge/policies";

    $this->patchJson($url, [])->assertUnprocessable()->assertJsonValidationErrors('mode');
    $this->patchJson($url, ['mode' => 'sometimes'])->assertUnprocessable()->assertJsonValidationErrors('mode');
});

it('only lets a synced collection be searched', function () {
    [$workspace, $owner, $agent] = agentWorkspaceForKnowledge();
    $source = KnowledgeSource::create(['workspace_id' => $workspace->id, 'collection' => 'site', 'type' => 'url', 'name' => 'Site', 'config' => ['url' => 'https://example.com']]);
    DocumentEmbedding::factory()->forWorkspace($workspace)->create(['collection' => 'site', 'knowledge_source_id' => $source->id]);
    Passport::actingAs($owner);
    $url = "/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/knowledge/site";

    $this->postJson($url, ['mode' => 'always'])->assertUnprocessable();
    expect($agent->knowledgeCollections()->count())->toBe(0);

    $this->postJson($url)->assertOk()->assertJsonPath('data.attached.0.synced', true);
    $this->patchJson($url, ['mode' => 'always'])->assertUnprocessable();
    expect($agent->knowledgeCollections()->sole()->mode)->toBe(KnowledgeMode::Search);
});

it('404s changing the mode of a collection that is not attached', function () {
    [$workspace, $owner, $agent] = agentWorkspaceForKnowledge();
    DocumentEmbedding::factory()->forWorkspace($workspace)->create(['collection' => 'policies']);
    Passport::actingAs($owner);

    $this->patchJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/knowledge/policies", ['mode' => 'always'])
        ->assertNotFound();
});

it('does not let a viewer change how knowledge is used', function () {
    [$workspace, , $agent] = agentWorkspaceForKnowledge();
    $agent->knowledgeCollections()->create(['collection' => 'policies']);
    $viewer = User::factory()->create();
    $workspace->members()->create(['user_id' => $viewer->id, 'role' => Role::Viewer, 'joined_at' => now()]);
    Passport::actingAs($viewer);

    $this->patchJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/knowledge/policies", ['mode' => 'always'])
        ->assertForbidden();
});

it('rejects attaching a collection name the knowledge base could never hold', function () {
    [$workspace, $owner, $agent] = agentWorkspaceForKnowledge();
    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/knowledge/".rawurlencode('LinkedIn Outreach'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('collection');
    expect($agent->knowledgeCollections()->count())->toBe(0);
});
