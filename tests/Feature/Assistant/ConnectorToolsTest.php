<?php

use App\Ai\Assistant\AssistantAgent;
use App\Ai\Assistant\Tools\ConnectorNodeTool;
use App\Enums\Assistant\AssistantActionStatus;
use App\Enums\Assistant\AssistantToolEffect;
use App\Enums\Assistant\AssistantTurnStatus;
use App\Enums\Connectors\ConnectorCredentialScope;
use App\Enums\Workspaces\Role;
use App\Models\Assistant\Assistant;
use App\Models\Assistant\AssistantAction;
use App\Models\Assistant\AssistantSession;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\User;
use App\Services\Assistant\Runtime\AssistantLoop;
use App\Services\Assistant\Tools\ConnectorToolProvider;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
    $this->assistant = Assistant::factory()->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->owner->id]);
    $this->session = AssistantSession::factory()->create(['assistant_id' => $this->assistant->id]);

    $this->gmail = Connector::factory()->create(['key' => 'gmail', 'name' => 'Gmail']);
    $this->slack = Connector::factory()->create(['key' => 'slack', 'name' => 'Slack']);

    $this->credential = fn (Connector $connector, ConnectorCredentialScope $scope, ?User $creator = null, array $attributes = []) => ConnectorCredential::factory()
        ->forWorkspace($this->workspace)
        ->forConnector($connector)
        ->create([
            'scope' => $scope,
            'created_by' => ($creator ?? $this->owner)->id,
            'data' => ['access_token' => 'token-'.fake()->uuid()],
            ...$attributes,
        ]);

    $this->toolNames = fn (): array => collect(app(ConnectorToolProvider::class)->toolsFor($this->assistant, $this->session))
        ->map(fn (ConnectorNodeTool $tool) => $tool->name())
        ->all();
});

it('offers the tools of apps the owner connected, and only those', function () {
    ($this->credential)($this->gmail, ConnectorCredentialScope::Personal);

    $names = ($this->toolNames)();

    expect($names)->toContain('gmail_send_email', 'gmail_list_messages')
        ->and(collect($names)->filter(fn ($name) => str_starts_with($name, 'slack_')))->toBeEmpty();
});

it('falls back to the workspace shared account', function () {
    ($this->credential)($this->slack, ConnectorCredentialScope::Team);

    expect(($this->toolNames)())->toContain('slack_post_message');
});

it('never uses another member personal account', function () {
    $member = User::factory()->create();
    $this->workspace->members()->create(['user_id' => $member->id, 'role' => Role::Member, 'joined_at' => now()]);
    ($this->credential)($this->gmail, ConnectorCredentialScope::Personal, $member);

    expect(($this->toolNames)())->toBeEmpty();
});

it('skips expired accounts', function () {
    ($this->credential)($this->gmail, ConnectorCredentialScope::Personal, null, ['expires_at' => now()->subDay()]);

    expect(($this->toolNames)())->toBeEmpty();
});

it('prefers the owner personal account over a shared one', function () {
    ($this->credential)($this->gmail, ConnectorCredentialScope::Team);
    $personal = ($this->credential)($this->gmail, ConnectorCredentialScope::Personal);

    expect(app(ConnectorToolProvider::class)->credentialsFor($this->assistant)->get('gmail')->id)->toBe($personal->id);
});

it('takes each tool effect from its node and hides credential fields', function () {
    ($this->credential)($this->gmail, ConnectorCredentialScope::Personal);
    $tools = collect(app(ConnectorToolProvider::class)->toolsFor($this->assistant, $this->session))->keyBy(fn ($tool) => $tool->name());

    expect($tools['gmail_list_messages']->effect())->toBe(AssistantToolEffect::Read)
        ->and($tools['gmail_send_email']->effect())->toBe(AssistantToolEffect::External)
        ->and(array_keys($tools['gmail_send_email']->schema(new JsonSchemaTypeFactory)))->not->toContain('credential_id', 'access_token');
});

it('reads an app straight away, always with the pinned account', function () {
    $credential = ($this->credential)($this->gmail, ConnectorCredentialScope::Personal);
    Http::fake(['gmail.googleapis.com/*' => Http::response(['messages' => [['id' => 'm1']]])]);
    AssistantAgent::fake([
        new ToolCall('call_1', 'gmail_list_messages', ['query' => 'is:unread', 'access_token' => 'stolen', 'credential_id' => 'other']),
        'You have one unread email.',
    ]);

    $turn = app(AssistantLoop::class)->send($this->session, 'Any unread email?');

    expect($turn->refresh()->status)->toBe(AssistantTurnStatus::Completed)
        ->and($turn->assistantMessage->tool_results[0]['result'])->toContain('m1');

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer '.$credential->data['access_token']));
});

it('asks before sending email, then sends it once approved', function () {
    ($this->credential)($this->gmail, ConnectorCredentialScope::Personal);
    Http::fake(['gmail.googleapis.com/*' => Http::response(['id' => 'sent-1'])]);
    AssistantAgent::fake([
        new ToolCall('call_1', 'gmail_send_email', ['to' => 'sam@example.com', 'subject' => 'Hi', 'body' => 'Hello']),
        'Sent.',
    ]);

    $loop = app(AssistantLoop::class);
    $turn = $loop->send($this->session, 'Email Sam');

    expect($turn->refresh()->status)->toBe(AssistantTurnStatus::AwaitingApproval);
    Http::assertNothingSent();

    $loop->decide($turn, ['call_1' => ['approve' => true]]);

    expect($turn->refresh()->status)->toBe(AssistantTurnStatus::Completed)
        ->and(AssistantAction::query()->sole()->status)->toBe(AssistantActionStatus::Executed);
    Http::assertSentCount(1);
});

it('lists the apps and which account the assistant uses', function () {
    ($this->credential)($this->gmail, ConnectorCredentialScope::Personal, null, ['name' => 'Work Gmail']);
    Passport::actingAs($this->owner);

    $apps = collect($this->getJson("/api/v1/workspaces/{$this->workspace->id}/assistant/apps")->assertSuccessful()->json('data.apps'))->keyBy('key');

    expect($apps['gmail'])->connected->toBeTrue()->account->toBe('Work Gmail')->shared->toBeFalse()
        ->and($apps['slack'])->connected->toBeFalse();
});
