<?php

use App\Ai\Agents\WorkflowBuilderAgent;
use App\Ai\Agents\WorkflowBuilderTitleAgent;
use App\Ai\Tools\WorkflowBuilder\ListConnectedAccountsTool;
use App\Enums\Connectors\ConnectorCredentialScope;
use App\Enums\Workspaces\Role;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\User;
use App\Models\Workflows\Builder\WorkflowBuilderSession;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Tools\Request as ToolRequest;
use Laravel\Passport\Passport;

function builderMessagesUrl(WorkflowBuilderSession $session): string
{
    return "/api/v1/workspaces/{$session->workspace_id}/workflow-builder-sessions/{$session->id}/messages";
}

it('stores the steps and accounts a message referenced and hands them to the builder', function () {
    WorkflowBuilderAgent::fake(['Added.']);
    WorkflowBuilderTitleAgent::fake([]);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $session = WorkflowBuilderSession::factory()->forWorkspace($workspace, $owner)->create();
    $gmail = Connector::factory()->create(['key' => 'gmail', 'name' => 'Gmail']);
    $account = ConnectorCredential::factory()->forWorkspace($workspace)->forConnector($gmail)->create(['name' => 'Work inbox', 'created_by' => $owner->id]);

    Passport::actingAs($owner);

    $this->postJson(builderMessagesUrl($session), [
        'message' => 'Email me a summary from [Gmail: Send Email] using @Gmail (Work inbox)',
        'context' => ['nodes' => ['gmail_send_email'], 'credentials' => [$account->id]],
    ])->assertAccepted();

    $this->getJson(builderMessagesUrl($session))
        ->assertOk()
        ->assertJsonPath('data.messages.0.context', ['nodes' => ['gmail_send_email'], 'credentials' => [$account->id]]);

    WorkflowBuilderAgent::assertPrompted(fn ($prompt): bool => str_starts_with($prompt->prompt, 'Email me a summary')
        && str_contains($prompt->prompt, '- gmail_send_email (')
        && str_contains($prompt->prompt, "\"Work inbox\", a gmail account: credential_id {$account->id}"));
});

it('sends the message as written when nothing was referenced', function () {
    WorkflowBuilderAgent::fake(['Done.']);
    WorkflowBuilderTitleAgent::fake([]);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $session = WorkflowBuilderSession::factory()->forWorkspace($workspace, $owner)->create();

    Passport::actingAs($owner);

    $this->postJson(builderMessagesUrl($session), ['message' => 'Add a transform step.', 'context' => []])->assertAccepted();

    expect($session->messages()->where('role', 'user')->sole()->context)->toBeNull();
    WorkflowBuilderAgent::assertPrompted(fn ($prompt): bool => $prompt->prompt === 'Add a transform step.');
});

it('refuses accounts the sender cannot use and steps the builder cannot place', function () {
    Queue::fake();

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $colleague = User::factory()->create();
    $workspace->members()->create(['user_id' => $colleague->id, 'role' => Role::Editor, 'joined_at' => now()]);
    $session = WorkflowBuilderSession::factory()->forWorkspace($workspace, $owner)->create();

    $elsewhere = ConnectorCredential::factory()->create(['scope' => ConnectorCredentialScope::Team]);
    $colleaguesOwn = ConnectorCredential::factory()->forWorkspace($workspace)->create(['scope' => ConnectorCredentialScope::Personal, 'created_by' => $colleague->id]);

    Passport::actingAs($owner);

    $this->postJson(builderMessagesUrl($session), [
        'message' => 'Build it.',
        'context' => ['nodes' => ['no_such_step', 'custom:abc'], 'credentials' => [$elsewhere->id, $colleaguesOwn->id]],
    ])->assertUnprocessable()->assertJsonValidationErrors([
        'context.nodes.0', 'context.nodes.1', 'context.credentials.0', 'context.credentials.1',
    ]);

    $this->postJson(builderMessagesUrl($session), [
        'message' => 'Build it.',
        'context' => ['somethingElse' => ['x']],
    ])->assertUnprocessable()->assertJsonValidationErrors('context');

    expect($session->messages()->count())->toBe(0);
});

it('accepts referenced accounts sent with attachments as multipart', function () {
    Queue::fake();

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $session = WorkflowBuilderSession::factory()->forWorkspace($workspace, $owner)->create();
    $account = ConnectorCredential::factory()->forWorkspace($workspace)->create(['created_by' => $owner->id]);

    Passport::actingAs($owner);

    $this->post(builderMessagesUrl($session), [
        'message' => 'Build from this spec.',
        'context' => ['credentials' => [$account->id]],
        'attachments' => [UploadedFile::fake()->createWithContent('spec.txt', 'Send a weekly digest.')],
    ], ['Accept' => 'application/json'])->assertAccepted();

    expect($session->messages()->where('role', 'user')->sole()->context)->toBe(['credentials' => [$account->id]]);
});

it('lists the team accounts and the acting member\'s own personal ones', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $colleague = User::factory()->create();
    $session = WorkflowBuilderSession::factory()->forWorkspace($workspace, $owner)->create();
    $slack = Connector::factory()->create(['key' => 'slack', 'name' => 'Slack']);

    $team = ConnectorCredential::factory()->forWorkspace($workspace)->forConnector($slack)->create(['name' => 'Team Slack', 'scope' => ConnectorCredentialScope::Team, 'created_by' => $colleague->id]);
    $mine = ConnectorCredential::factory()->forWorkspace($workspace)->create(['name' => 'My account', 'scope' => ConnectorCredentialScope::Personal, 'created_by' => $owner->id]);
    ConnectorCredential::factory()->forWorkspace($workspace)->create(['name' => 'Not mine', 'scope' => ConnectorCredentialScope::Personal, 'created_by' => $colleague->id]);
    ConnectorCredential::factory()->create(['name' => 'Other workspace', 'scope' => ConnectorCredentialScope::Team]);

    $listed = json_decode((string) (new ListConnectedAccountsTool($session, $owner))->handle(new ToolRequest([], 'call-1')), true);

    expect(collect($listed)->pluck('id')->all())->toBe([$mine->id, $team->id]);
    expect($listed[1])->toMatchArray(['connector' => 'slack', 'connector_name' => 'Slack', 'scope' => 'team', 'usable' => true]);
});

it('says so when no account is connected', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $session = WorkflowBuilderSession::factory()->forWorkspace($workspace, $owner)->create();

    expect((string) (new ListConnectedAccountsTool($session, $owner))->handle(new ToolRequest([], 'call-1')))
        ->toContain('No accounts are connected');
});
