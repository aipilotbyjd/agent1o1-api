<?php

use App\Models\Agents\Agent;
use App\Models\User;
use App\Models\Workflows\Tag;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

/**
 * @return array{0: Workspace, 1: User}
 */
function ownerWorkspaceForTag(): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    return [$workspace, $owner];
}

it('creates a tag', function () {
    [$workspace, $owner] = ownerWorkspaceForTag();
    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/tags", [
        'name' => 'urgent',
        'color' => '#ff0000',
    ]);

    $response->assertCreated();
    expect($response->json('data.tag.name'))->toBe('urgent');
});

it('rejects a duplicate tag name in the same workspace', function () {
    [$workspace, $owner] = ownerWorkspaceForTag();
    Tag::factory()->forWorkspace($workspace)->create(['name' => 'urgent']);

    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/tags", ['name' => 'urgent'])
        ->assertStatus(422);
});

it('lists tags with workflow counts', function () {
    [$workspace, $owner] = ownerWorkspaceForTag();
    $tag = Tag::factory()->forWorkspace($workspace)->create();
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $tag->workflows()->attach($workflow);

    Passport::actingAs($owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/tags");

    $response->assertOk();
    expect($response->json('data.tags.0.workflow_count'))->toBe(1);
});

it('syncs tags onto a workflow', function () {
    [$workspace, $owner] = ownerWorkspaceForTag();
    $tags = Tag::factory()->forWorkspace($workspace)->count(2)->create();
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    Passport::actingAs($owner);

    $response = $this->putJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/tags", [
        'tag_ids' => $tags->pluck('id')->all(),
    ]);

    $response->assertOk();
    expect($workflow->fresh()->tags)->toHaveCount(2);
});

it('rejects syncing a tag from another workspace onto a workflow', function () {
    [$workspace, $owner] = ownerWorkspaceForTag();
    [$otherWorkspace] = ownerWorkspaceForTag();
    $foreignTag = Tag::factory()->forWorkspace($otherWorkspace)->create();
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    Passport::actingAs($owner);

    $this->putJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/tags", [
        'tag_ids' => [$foreignTag->id],
    ])->assertUnprocessable()->assertJsonValidationErrors('tag_ids.0');

    expect($workflow->fresh()->tags)->toBeEmpty();
});

it('syncs tags onto an agent', function () {
    [$workspace, $owner] = ownerWorkspaceForTag();
    $tags = Tag::factory()->forWorkspace($workspace)->count(2)->create();
    $agent = Agent::factory()->forWorkspace($workspace)->create();

    Passport::actingAs($owner);

    $response = $this->putJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/tags", [
        'tag_ids' => $tags->pluck('id')->all(),
    ]);

    $response->assertOk();
    expect($agent->fresh()->tags)->toHaveCount(2);
});

it('rejects syncing a tag from another workspace onto an agent', function () {
    [$workspace, $owner] = ownerWorkspaceForTag();
    [$otherWorkspace] = ownerWorkspaceForTag();
    $foreignTag = Tag::factory()->forWorkspace($otherWorkspace)->create();
    $agent = Agent::factory()->forWorkspace($workspace)->create();

    Passport::actingAs($owner);

    $this->putJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/tags", [
        'tag_ids' => [$foreignTag->id],
    ])->assertUnprocessable()->assertJsonValidationErrors('tag_ids.0');

    expect($agent->fresh()->tags)->toBeEmpty();
});

it('includes tags when listing and showing agents', function () {
    [$workspace, $owner] = ownerWorkspaceForTag();
    $tag = Tag::factory()->forWorkspace($workspace)->create(['name' => 'support']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $agent->tags()->attach($tag);

    Passport::actingAs($owner);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/agents")
        ->assertOk()
        ->assertJsonPath('data.agents.0.tags.0.name', 'support');

    $this->getJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}")
        ->assertOk()
        ->assertJsonPath('data.agent.tags.0.name', 'support');
});

it('filters the agent list by tag', function () {
    [$workspace, $owner] = ownerWorkspaceForTag();
    $tag = Tag::factory()->forWorkspace($workspace)->create();
    $tagged = Agent::factory()->forWorkspace($workspace)->create();
    Agent::factory()->forWorkspace($workspace)->create();
    $tagged->tags()->attach($tag);

    Passport::actingAs($owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/agents?tag_id={$tag->id}");

    $response->assertOk();
    expect($response->json('data.agents'))->toHaveCount(1);
    expect($response->json('data.agents.0.id'))->toBe($tagged->id);
});

it('returns no agents for a malformed tag filter', function () {
    [$workspace, $owner] = ownerWorkspaceForTag();
    Agent::factory()->forWorkspace($workspace)->create();

    Passport::actingAs($owner);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/agents?tag_id=not-a-uuid")
        ->assertOk()
        ->assertJsonCount(0, 'data.agents');
});

it('shares a tag across a workflow and an agent, counted separately', function () {
    [$workspace, $owner] = ownerWorkspaceForTag();
    $tag = Tag::factory()->forWorkspace($workspace)->create();
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $tag->workflows()->attach($workflow);
    $tag->agents()->attach($agent);

    Passport::actingAs($owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/tags");

    $response->assertOk();
    expect($response->json('data.tags.0.workflow_count'))->toBe(1);
    expect($response->json('data.tags.0.agent_count'))->toBe(1);
});

it('deletes a tag', function () {
    [$workspace, $owner] = ownerWorkspaceForTag();
    $tag = Tag::factory()->forWorkspace($workspace)->create();

    Passport::actingAs($owner);

    $this->deleteJson("/api/v1/workspaces/{$workspace->id}/tags/{$tag->id}")->assertNoContent();
    expect(Tag::find($tag->id))->toBeNull();
});

it('404s deleting a tag that belongs to a different workspace', function () {
    [$workspace, $owner] = ownerWorkspaceForTag();
    [$otherWorkspace] = ownerWorkspaceForTag();
    $foreign = Tag::factory()->forWorkspace($otherWorkspace)->create();

    Passport::actingAs($owner);

    $this->deleteJson("/api/v1/workspaces/{$workspace->id}/tags/{$foreign->id}")->assertNotFound();
});
