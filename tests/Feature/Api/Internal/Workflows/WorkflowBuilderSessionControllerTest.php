<?php

use App\Enums\Workflows\BuilderMessageStatus;
use App\Enums\Workflows\BuilderSessionStatus;
use App\Enums\Workspaces\Role;
use App\Jobs\Workflows\ProcessWorkflowBuilderMessageJob;
use App\Models\Nodes\CustomNode;
use App\Models\User;
use App\Models\Workflows\Builder\WorkflowBuilderSession;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;

/**
 * @return array{0: Workspace, 1: User}
 */
function ownerWorkspaceForBuilderSession(): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    return [$workspace, $owner];
}

it('creates a session with an empty draft graph', function () {
    [$workspace, $owner] = ownerWorkspaceForBuilderSession();
    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/workflow-builder-sessions", [
        'title' => 'New automation',
    ]);

    $response->assertCreated();
    expect($response->json('data.session.title'))->toBe('New automation');
    expect($response->json('data.session.draft_graph'))->toBe(['nodes' => [], 'edges' => []]);
});

it('seeds the draft graph from an existing workflow', function () {
    [$workspace, $owner] = ownerWorkspaceForBuilderSession();
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph([
        'nodes' => [['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]]],
        'edges' => [],
    ]);

    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/workflow-builder-sessions", [
        'workflow_id' => $workflow->id,
    ]);

    $response->assertCreated();
    expect($response->json('data.session.draft_graph.nodes'))->toHaveCount(1);
    expect($response->json('data.session.draft_graph.nodes.0.key'))->toBe('a');
});

it('404s reading a session that belongs to a different workspace', function () {
    [$workspace, $owner] = ownerWorkspaceForBuilderSession();
    [$otherWorkspace] = ownerWorkspaceForBuilderSession();

    $foreign = WorkflowBuilderSession::factory()->forWorkspace($otherWorkspace, $owner)->create();

    Passport::actingAs($owner);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/workflow-builder-sessions/{$foreign->id}")->assertNotFound();
});

it('promotes a draft to a new workflow', function () {
    [$workspace, $owner] = ownerWorkspaceForBuilderSession();
    $session = WorkflowBuilderSession::factory()->forWorkspace($workspace, $owner)->create([
        'draft_graph' => [
            'nodes' => [['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]]],
            'edges' => [],
        ],
    ]);

    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/workflow-builder-sessions/{$session->id}/promote", [
        'name' => 'Published Workflow',
    ]);

    $response->assertOk();
    expect($response->json('data.workflow.name'))->toBe('Published Workflow');
    expect($response->json('data.workflow.nodes'))->toHaveCount(1);
    expect($session->fresh()->workflow_id)->toBe($response->json('data.workflow.id'));
    expect($session->fresh()->status)->toBe(BuilderSessionStatus::Promoted);
});

it('deletes a session', function () {
    [$workspace, $owner] = ownerWorkspaceForBuilderSession();
    $session = WorkflowBuilderSession::factory()->forWorkspace($workspace, $owner)->create();

    Passport::actingAs($owner);

    $this->deleteJson("/api/v1/workspaces/{$workspace->id}/workflow-builder-sessions/{$session->id}")->assertNoContent();
    expect(WorkflowBuilderSession::find($session->id))->toBeNull();
});

it('renames and archives a session, and hides archived sessions from the default list', function () {
    [$workspace, $owner] = ownerWorkspaceForBuilderSession();
    $session = WorkflowBuilderSession::factory()->forWorkspace($workspace, $owner)->create();
    $base = "/api/v1/workspaces/{$workspace->id}/workflow-builder-sessions";

    Passport::actingAs($owner);

    $this->patchJson("{$base}/{$session->id}", ['title' => 'Lead Router'])
        ->assertOk()
        ->assertJsonPath('data.session.title', 'Lead Router');

    $this->patchJson("{$base}/{$session->id}", ['status' => 'archived'])->assertOk();

    expect($this->getJson($base)->json('data.sessions'))->toBe([]);
    expect($this->getJson("{$base}?status=archived")->json('data.sessions.*.id'))->toBe([$session->id]);
});

it('refuses to set a session to promoted directly', function () {
    [$workspace, $owner] = ownerWorkspaceForBuilderSession();
    $session = WorkflowBuilderSession::factory()->forWorkspace($workspace, $owner)->create();

    Passport::actingAs($owner);

    $this->patchJson("/api/v1/workspaces/{$workspace->id}/workflow-builder-sessions/{$session->id}", ['status' => 'promoted'])
        ->assertUnprocessable();
});

it('syncs the draft from the canvas', function () {
    [$workspace, $owner] = ownerWorkspaceForBuilderSession();
    $session = WorkflowBuilderSession::factory()->forWorkspace($workspace, $owner)->create();

    Passport::actingAs($owner);

    $this->patchJson("/api/v1/workspaces/{$workspace->id}/workflow-builder-sessions/{$session->id}/draft", [
        'draft_lock_version' => 0,
        'nodes' => [['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []], 'position' => ['x' => 10, 'y' => 20]]],
        'edges' => [],
    ])
        ->assertOk()
        ->assertJsonPath('data.session.draft_lock_version', 1)
        ->assertJsonPath('data.session.draft_graph.nodes.0.position', ['x' => 10, 'y' => 20]);
});

it('409s a canvas sync that is behind the assistant', function () {
    [$workspace, $owner] = ownerWorkspaceForBuilderSession();
    $session = WorkflowBuilderSession::factory()->forWorkspace($workspace, $owner)->create();
    $session->addNode('from_assistant', 'transform', ['mapping' => []]);

    Passport::actingAs($owner);

    $this->patchJson("/api/v1/workspaces/{$workspace->id}/workflow-builder-sessions/{$session->id}/draft", [
        'draft_lock_version' => 0,
        'nodes' => [],
        'edges' => [],
    ])->assertConflict();

    expect($session->fresh()->currentGraph()['nodes'])->toHaveCount(1);
});

it('422s a canvas sync with an invalid node config', function () {
    [$workspace, $owner] = ownerWorkspaceForBuilderSession();
    $session = WorkflowBuilderSession::factory()->forWorkspace($workspace, $owner)->create();

    Passport::actingAs($owner);

    $this->patchJson("/api/v1/workspaces/{$workspace->id}/workflow-builder-sessions/{$session->id}/draft", [
        'draft_lock_version' => 0,
        'nodes' => [['key' => 'a', 'type' => 'does_not_exist']],
        'edges' => [],
    ])->assertUnprocessable();
});

it('starts a session from a prompt and queues its first reply', function () {
    Queue::fake();
    [$workspace, $owner] = ownerWorkspaceForBuilderSession();

    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/workflow-builder-sessions", [
        'prompt' => 'Every morning, email me yesterday\'s signups.',
    ])->assertCreated();

    expect($response->json('data.message.processing_status'))->toBe('pending');
    Queue::assertPushed(ProcessWorkflowBuilderMessageJob::class);
});

it('records where a session seeded from a workflow started, and names it after the workflow', function () {
    [$workspace, $owner] = ownerWorkspaceForBuilderSession();
    $workflow = Workflow::factory()->forWorkspace($workspace)->create(['name' => 'Lead Router']);
    $workflow->replaceGraph([
        'nodes' => [['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]]],
        'edges' => [],
    ]);

    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/workflow-builder-sessions", [
        'workflow_id' => $workflow->id,
    ])->assertCreated();

    $session = WorkflowBuilderSession::find($response->json('data.session.id'));
    expect($session->title)->toBe('Lead Router');
    expect($session->draftVersions()->sole()->label)->toBe('Loaded from Lead Router');
});

it('refuses to promote an archived session', function () {
    [$workspace, $owner] = ownerWorkspaceForBuilderSession();
    $session = WorkflowBuilderSession::factory()->forWorkspace($workspace, $owner)->create(['status' => BuilderSessionStatus::Archived]);

    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/workflow-builder-sessions/{$session->id}/promote")->assertConflict();
});

function promoteBuilderSession(Workspace $workspace, WorkflowBuilderSession $session, array $payload = []): TestResponse
{
    return test()->postJson("/api/v1/workspaces/{$workspace->id}/workflow-builder-sessions/{$session->id}/promote", $payload);
}

it('leaves no workflow behind when the workflow refuses the draft', function () {
    [$workspace, $owner] = ownerWorkspaceForBuilderSession();
    // Valid when it was drafted; the node's schema has since tightened.
    $session = WorkflowBuilderSession::factory()->forWorkspace($workspace, $owner)->create([
        'draft_graph' => ['nodes' => [['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => 'not-an-object']]], 'edges' => []],
    ]);

    Passport::actingAs($owner);

    promoteBuilderSession($workspace, $session, ['name' => 'Broken'])->assertUnprocessable();
    promoteBuilderSession($workspace, $session, ['name' => 'Broken'])->assertUnprocessable();

    expect(Workflow::where('workspace_id', $workspace->id)->count())->toBe(0);
    expect($session->fresh()->workflow_id)->toBeNull();
    expect($session->fresh()->status)->toBe(BuilderSessionStatus::Active);
});

it('refuses to promote while the assistant is still editing the draft', function () {
    [$workspace, $owner] = ownerWorkspaceForBuilderSession();
    $session = WorkflowBuilderSession::factory()->forWorkspace($workspace, $owner)->create();
    $session->messages()->create(['role' => 'assistant', 'content' => '', 'processing_status' => BuilderMessageStatus::Processing]);

    Passport::actingAs($owner);

    promoteBuilderSession($workspace, $session)->assertConflict()->assertJsonPath('message', 'The assistant is still replying to your last message.');

    expect(Workflow::where('workspace_id', $workspace->id)->count())->toBe(0);
});

it('promotes once a reply stuck in flight has gone stale', function () {
    [$workspace, $owner] = ownerWorkspaceForBuilderSession();
    $session = WorkflowBuilderSession::factory()->forWorkspace($workspace, $owner)->create([
        'draft_graph' => ['nodes' => [['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]]], 'edges' => []],
    ]);
    $stuck = $session->messages()->create(['role' => 'assistant', 'content' => '', 'processing_status' => BuilderMessageStatus::Processing]);

    $this->travel(WorkflowBuilderSession::STALE_REPLY_MINUTES + 1)->minutes();
    Passport::actingAs($owner);

    promoteBuilderSession($workspace, $session)->assertOk();

    expect($stuck->fresh()->processing_status)->toBe(BuilderMessageStatus::Failed);
});

it('refuses to overwrite edits made to the workflow outside the session, unless told to', function () {
    [$workspace, $owner] = ownerWorkspaceForBuilderSession();
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph(['nodes' => [['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]]], 'edges' => []]);

    Passport::actingAs($owner);

    $sessionId = $this->postJson("/api/v1/workspaces/{$workspace->id}/workflow-builder-sessions", ['workflow_id' => $workflow->id])
        ->assertCreated()
        ->json('data.session.id');
    $session = WorkflowBuilderSession::find($sessionId);

    // Someone edits the workflow in the regular editor meanwhile.
    $workflow->replaceGraph(['nodes' => [
        ['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]],
        ['key' => 'from_editor', 'type' => 'transform', 'config' => ['mapping' => []]],
    ], 'edges' => []]);

    promoteBuilderSession($workspace, $session)->assertConflict();
    expect($workflow->nodes()->pluck('key')->all())->toContain('from_editor');

    promoteBuilderSession($workspace, $session, ['overwrite' => true])->assertOk();
    expect($workflow->nodes()->pluck('key')->all())->toBe(['a']);
});

it('promotes again into its own workflow when nothing else changed it', function () {
    [$workspace, $owner] = ownerWorkspaceForBuilderSession();
    $session = WorkflowBuilderSession::factory()->forWorkspace($workspace, $owner)->create([
        'draft_graph' => ['nodes' => [['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]]], 'edges' => []],
    ]);

    Passport::actingAs($owner);

    $workflowId = promoteBuilderSession($workspace, $session)->assertOk()->json('data.workflow.id');

    $session->fresh()->addNode('b', 'transform', ['mapping' => []]);

    promoteBuilderSession($workspace, $session)
        ->assertOk()
        ->assertJsonPath('data.workflow.id', $workflowId)
        ->assertJsonPath('message', 'Draft saved to the workflow.');

    expect(Workflow::find($workflowId)->nodes()->count())->toBe(2);
});

it('gives a workflow a usable slug whatever its name is written in', function () {
    [$workspace, $owner] = ownerWorkspaceForBuilderSession();
    $session = WorkflowBuilderSession::factory()->forWorkspace($workspace, $owner)->create(['title' => '見込み客の振り分け']);

    Passport::actingAs($owner);

    $workflowId = promoteBuilderSession($workspace, $session)->assertOk()->json('data.workflow.id');

    expect(Workflow::find($workflowId)->slug)->toMatch('/^workflow-[A-Za-z0-9]{6}$/');
});

it('lists sessions without their graphs, and only the caller\'s when asked', function () {
    [$workspace, $owner] = ownerWorkspaceForBuilderSession();
    $colleague = User::factory()->create();
    $workspace->members()->create(['user_id' => $colleague->id, 'role' => Role::Editor, 'joined_at' => now()]);
    $mine = WorkflowBuilderSession::factory()->forWorkspace($workspace, $owner)->create();
    WorkflowBuilderSession::factory()->forWorkspace($workspace, $colleague)->create();

    Passport::actingAs($owner);

    $all = $this->getJson("/api/v1/workspaces/{$workspace->id}/workflow-builder-sessions")->assertOk()->json('data.sessions');
    expect($all)->toHaveCount(2);
    expect($all[0])->not->toHaveKey('draft_graph');

    $this->getJson("/api/v1/workspaces/{$workspace->id}/workflow-builder-sessions?mine=1")
        ->assertOk()
        ->assertJsonCount(1, 'data.sessions')
        ->assertJsonPath('data.sessions.0.id', $mine->id);
});

it('syncs the canvas of a session opened on a workflow holding a custom node', function () {
    [$workspace, $owner] = ownerWorkspaceForBuilderSession();
    $custom = CustomNode::factory()->create(['workspace_id' => $workspace->id]);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph(['nodes' => [['key' => 'c', 'type' => "custom:{$custom->id}", 'config' => []]], 'edges' => []]);

    Passport::actingAs($owner);

    $session = $this->postJson("/api/v1/workspaces/{$workspace->id}/workflow-builder-sessions", ['workflow_id' => $workflow->id])
        ->assertCreated()
        ->json('data.session');

    $this->patchJson("/api/v1/workspaces/{$workspace->id}/workflow-builder-sessions/{$session['id']}/draft", [
        'draft_lock_version' => $session['draft_lock_version'],
        'nodes' => [
            ['key' => 'c', 'type' => "custom:{$custom->id}", 'config' => [], 'position' => ['x' => 40, 'y' => 0]],
            ['key' => 'recovery', 'type' => 'transform', 'config' => ['mapping' => []]],
        ],
        'edges' => [['from' => 'c', 'to' => 'recovery', 'condition' => 'error']],
    ])->assertOk()->assertJsonCount(2, 'data.session.draft_graph.nodes');
});

it('syncs a canvas whose steps are not filled in yet, like any draft', function () {
    [$workspace, $owner] = ownerWorkspaceForBuilderSession();
    $session = WorkflowBuilderSession::factory()->forWorkspace($workspace, $owner)->create();

    Passport::actingAs($owner);

    $this->patchJson("/api/v1/workspaces/{$workspace->id}/workflow-builder-sessions/{$session->id}/draft", [
        'nodes' => [['key' => 'ask', 'type' => 'ask_ai', 'config' => ['instructions' => 'Be brief.', 'prompt' => null]]],
        'edges' => [],
        'draft_lock_version' => $session->draft_lock_version,
    ])->assertOk();
});

it('returns messages saved in the same second in the order they were sent', function () {
    [$workspace, $owner] = ownerWorkspaceForBuilderSession();
    $session = WorkflowBuilderSession::factory()->forWorkspace($workspace, $owner)->create();
    $sentAt = now()->startOfSecond();

    // Ids are time-ordered; created_at alone ties within a second.
    $session->messages()->forceCreate(['role' => 'assistant', 'content' => 'reply', 'created_at' => $sentAt, 'id' => '01a12b73-d132-724d-ba2a-e6b2ab0aa2a3']);
    $session->messages()->forceCreate(['role' => 'user', 'content' => 'question', 'created_at' => $sentAt, 'id' => '01a12b73-d12b-7079-8e17-031e55277a9b']);

    Passport::actingAs($owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/workflow-builder-sessions/{$session->id}")->assertOk();

    expect(collect($response->json('data.session.messages'))->pluck('content')->all())->toBe(['question', 'reply']);
});
