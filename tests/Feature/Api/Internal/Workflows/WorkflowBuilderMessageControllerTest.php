<?php

use App\Ai\Agents\WorkflowBuilderAgent;
use App\Ai\Agents\WorkflowBuilderTitleAgent;
use App\Enums\Queue as QueueName;
use App\Enums\Workflows\BuilderMessageMode;
use App\Enums\Workflows\BuilderMessageStatus;
use App\Enums\Workflows\BuilderSessionStatus;
use App\Enums\Workspaces\Role;
use App\Jobs\Workflows\ProcessWorkflowBuilderMessageJob;
use App\Models\User;
use App\Models\Workflows\Builder\WorkflowBuilderMessage;
use App\Models\Workflows\Builder\WorkflowBuilderSession;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Tools\Request as ToolRequest;
use Laravel\Passport\Passport;

it('accepts a chat message and writes the reply in the background', function () {
    WorkflowBuilderAgent::fake(["Sure, I've added the node."]);
    WorkflowBuilderTitleAgent::fake([]);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $session = WorkflowBuilderSession::factory()->forWorkspace($workspace, $owner)->create();

    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/workflow-builder-sessions/{$session->id}/messages", [
        'message' => 'Add a node that sends a Slack message.',
    ]);

    $response->assertAccepted();
    expect($response->json('data.message.role'))->toBe('assistant');

    // The sync queue has already run the turn by the time the request returns.
    $reply = WorkflowBuilderMessage::find($response->json('data.message.id'));
    expect($reply->content)->toBe("Sure, I've added the node.");
    expect($reply->processing_status)->toBe(BuilderMessageStatus::Completed);
    expect($session->fresh()->messages)->toHaveCount(2);
});

it('returns the pending reply and queues the turn', function () {
    Queue::fake();

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $session = WorkflowBuilderSession::factory()->forWorkspace($workspace, $owner)->create();

    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/workflow-builder-sessions/{$session->id}/messages", [
        'message' => 'Build me something.',
    ])->assertAccepted();

    expect($response->json('data.message.processing_status'))->toBe('pending');

    Queue::assertPushedOn(QueueName::WorkflowBuilder->value, ProcessWorkflowBuilderMessageJob::class, fn ($job) => $job->assistantMessageId === $response->json('data.message.id'));
});

it('refuses a second message while a reply is still being written', function () {
    Queue::fake();

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $session = WorkflowBuilderSession::factory()->forWorkspace($workspace, $owner)->create();
    $url = "/api/v1/workspaces/{$workspace->id}/workflow-builder-sessions/{$session->id}/messages";

    Passport::actingAs($owner);

    $this->postJson($url, ['message' => 'first'])->assertAccepted();
    $this->postJson($url, ['message' => 'second'])->assertConflict();

    expect($session->messages()->count())->toBe(2);
});

it('fails a reply that has been in flight too long so the session is not stuck', function () {
    Queue::fake();

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $session = WorkflowBuilderSession::factory()->forWorkspace($workspace, $owner)->create();
    $url = "/api/v1/workspaces/{$workspace->id}/workflow-builder-sessions/{$session->id}/messages";

    Passport::actingAs($owner);

    $stuck = $this->postJson($url, ['message' => 'first'])->json('data.message.id');

    $this->travel(WorkflowBuilderSession::STALE_REPLY_MINUTES + 1)->minutes();

    $this->postJson($url, ['message' => 'second'])->assertAccepted();

    expect(WorkflowBuilderMessage::find($stuck)->processing_status)->toBe(BuilderMessageStatus::Failed);
});

it('refuses messages to an archived session', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $session = WorkflowBuilderSession::factory()->forWorkspace($workspace, $owner)->create(['status' => BuilderSessionStatus::Archived]);

    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/workflow-builder-sessions/{$session->id}/messages", [
        'message' => 'hi',
    ])->assertConflict();
});

it('lists and shows a session\'s messages in order', function () {
    Queue::fake();

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $session = WorkflowBuilderSession::factory()->forWorkspace($workspace, $owner)->create();
    $base = "/api/v1/workspaces/{$workspace->id}/workflow-builder-sessions/{$session->id}/messages";

    Passport::actingAs($owner);

    $replyId = $this->postJson($base, ['message' => 'hello'])->json('data.message.id');

    expect($this->getJson($base)->assertOk()->json('data.messages.*.role'))->toBe(['user', 'assistant']);
    $this->getJson("{$base}/{$replyId}")->assertOk()->assertJsonPath('data.message.processing_status', 'pending');
});

it('404s sending a message to a session in a different workspace', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $otherOwner = User::factory()->create();
    $otherWorkspace = app(WorkspaceService::class)->create($otherOwner, ['name' => 'Other']);

    $foreign = WorkflowBuilderSession::factory()->forWorkspace($otherWorkspace, $otherOwner)->create();

    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/workflow-builder-sessions/{$foreign->id}/messages", [
        'message' => 'hi',
    ])->assertNotFound();
});

it('records who sent each message and credits their edits to them', function () {
    Queue::fake();

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $colleague = User::factory()->create();
    $workspace->members()->create(['user_id' => $colleague->id, 'role' => Role::Editor, 'joined_at' => now()]);
    $session = WorkflowBuilderSession::factory()->forWorkspace($workspace, $owner)->create();

    Passport::actingAs($colleague);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/workflow-builder-sessions/{$session->id}/messages", [
        'message' => 'Add a transform step.',
    ])->assertAccepted();

    $sent = $session->messages()->where('role', 'user')->sole();
    expect($sent->user_id)->toBe($colleague->id);

    $agent = new WorkflowBuilderAgent($session, $sent->id, $sent->user);
    collect(iterator_to_array($agent->tools()))
        ->firstOrFail(fn ($tool) => $tool->name() === 'add_node')
        ->handle(new ToolRequest(['key' => 'a', 'type' => 'transform', 'config_json' => '{"mapping": {}}'], 'call-1'));

    expect($session->draftVersions()->value('triggered_by'))->toBe($colleague->id);
});

it('answers an ask-mode message without the tools that edit the draft', function () {
    WorkflowBuilderAgent::fake(['It posts to Slack.']);
    WorkflowBuilderTitleAgent::fake([]);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $session = WorkflowBuilderSession::factory()->forWorkspace($workspace, $owner)->create();

    Passport::actingAs($owner);

    $url = "/api/v1/workspaces/{$workspace->id}/workflow-builder-sessions/{$session->id}/messages";
    $this->postJson($url, ['message' => 'What does this do?', 'mode' => 'ask'])->assertAccepted();

    // Stored as asked: the instruction lives on the turn, not in the text.
    $this->getJson($url)
        ->assertOk()
        ->assertJsonPath('data.messages.0.content', 'What does this do?')
        ->assertJsonPath('data.messages.0.mode', 'ask')
        ->assertJsonPath('data.messages.1.mode', 'ask');

    WorkflowBuilderAgent::assertPrompted(fn ($prompt): bool => $prompt->prompt === 'What does this do?'
        && $prompt->agent->mode === BuilderMessageMode::Ask
        && ! collect(iterator_to_array($prompt->agent->tools()))->contains(fn ($tool) => $tool->name() === 'add_node'));
});

it('builds by default and refuses an unknown mode', function () {
    WorkflowBuilderAgent::fake(['Done.']);
    WorkflowBuilderTitleAgent::fake([]);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $session = WorkflowBuilderSession::factory()->forWorkspace($workspace, $owner)->create();

    Passport::actingAs($owner);

    $url = "/api/v1/workspaces/{$workspace->id}/workflow-builder-sessions/{$session->id}/messages";
    $this->postJson($url, ['message' => 'Add a Slack step.'])->assertAccepted();
    $this->postJson($url, ['message' => 'Hmm', 'mode' => 'explain'])->assertUnprocessable()->assertJsonValidationErrors('mode');

    expect($session->messages()->where('role', 'user')->sole()->mode)->toBe(BuilderMessageMode::Build);
    WorkflowBuilderAgent::assertPrompted(fn ($prompt): bool => $prompt->agent->mode === BuilderMessageMode::Build);
});
