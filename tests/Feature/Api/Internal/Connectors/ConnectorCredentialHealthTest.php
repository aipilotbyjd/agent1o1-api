<?php

use App\Enums\Agents\KnowledgeSourceType;
use App\Enums\Connectors\ConnectorCredentialScope;
use App\Models\Agents\Agent;
use App\Models\Agents\KnowledgeSource;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowNode;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
    $this->gmail = Connector::query()->where('key', 'gmail')->first() ?? Connector::factory()->oauth()->create(['key' => 'gmail', 'name' => 'Gmail']);
    $this->gmail->forceFill(['auth_type' => 'oauth2', 'oauth' => ['authorize_url' => 'https://accounts.google.com/o/oauth2/v2/auth', 'token_url' => 'https://oauth2.googleapis.com/token', 'scopes' => []]])->save();
    config(['services.gmail.client_id' => 'cid', 'services.gmail.client_secret' => 'secret']);

    $this->credential = fn (array $attributes = []) => ConnectorCredential::factory()->forWorkspace($this->workspace)->forConnector($this->gmail)->create([
        'data' => ['access_token' => 'live-token', 'refresh_token' => 'refresh-1'],
        'expires_at' => now()->addHour(),
        'created_by' => $this->owner->id,
        ...$attributes,
    ]);
    $this->url = fn (ConnectorCredential $credential, string $action) => "/api/v1/workspaces/{$this->workspace->id}/connector-credentials/{$credential->id}/{$action}";

    Passport::actingAs($this->owner);
});

it('reports a working connection with the account it belongs to', function () {
    Http::fake(['www.googleapis.com/oauth2/v3/tokeninfo*' => Http::response(['email' => 'me@example.com'])]);
    $credential = ($this->credential)(['last_used_at' => null]);

    $response = $this->postJson(($this->url)($credential, 'test'));

    $response->assertOk();
    expect($response->json('data.result.ok'))->toBeTrue()
        ->and($response->json('data.result.account'))->toBe('me@example.com')
        ->and($credential->fresh()->last_used_at)->toBeNull();
    Http::assertSent(fn (HttpRequest $request) => str_contains($request->url(), 'access_token=live-token'));
});

it('reports a token the provider rejects as needing a reconnect', function () {
    Http::fake(['www.googleapis.com/oauth2/v3/tokeninfo*' => Http::response(['error' => 'invalid_token'], 400)]);

    $response = $this->postJson(($this->url)(($this->credential)(), 'test'));

    $response->assertOk();
    expect($response->json('data.result.ok'))->toBeFalse()
        ->and($response->json('data.result.message'))->toContain('Reconnect');
});

it('reports an expired connection that cannot be refreshed without calling the provider', function () {
    Http::fake();
    $credential = ($this->credential)([
        'data' => ['access_token' => 'old-token'],
        'expires_at' => now()->subHour(),
    ]);

    $response = $this->postJson(($this->url)($credential, 'test'));

    $response->assertOk();
    expect($response->json('data.result.ok'))->toBeFalse()
        ->and($response->json('data.result.message'))->toContain('has expired');
    Http::assertNothingSent();
});

it('reports an unreachable provider without failing the request', function () {
    Http::fake(fn () => throw new ConnectionException('timed out'));

    $response = $this->postJson(($this->url)(($this->credential)(), 'test'));

    $response->assertOk();
    expect($response->json('data.result.ok'))->toBeFalse()
        ->and($response->json('data.result.message'))->toContain("Couldn't reach");
});

it('hides another member\'s personal credential from the test endpoint', function () {
    $other = User::factory()->create();
    $credential = ($this->credential)(['scope' => ConnectorCredentialScope::Personal, 'created_by' => $other->id]);

    $this->postJson(($this->url)($credential, 'test'))->assertNotFound();
});

it('lists workflows and agents that pin the credential or fall back to it as the default', function () {
    $credential = ($this->credential)(['is_default' => true]);
    $other = ($this->credential)(['is_default' => false]);

    $pinned = Workflow::factory()->forWorkspace($this->workspace)->create(['name' => 'Pinned flow']);
    WorkflowNode::create(['workflow_id' => $pinned->id, 'key' => 'send', 'type' => 'gmail_send_email', 'config' => ['credential_id' => $credential->id]]);

    $unpinned = Workflow::factory()->forWorkspace($this->workspace)->create(['name' => 'Default flow']);
    WorkflowNode::create(['workflow_id' => $unpinned->id, 'key' => 'send', 'type' => 'gmail_send_email', 'config' => []]);

    $elsewhere = Workflow::factory()->forWorkspace($this->workspace)->create(['name' => 'Other account flow']);
    WorkflowNode::create(['workflow_id' => $elsewhere->id, 'key' => 'send', 'type' => 'gmail_send_email', 'config' => ['credential_id' => $other->id]]);

    $agent = Agent::factory()->forWorkspace($this->workspace)->create(['name' => 'Inbox agent']);
    $agent->toolBindings()->create(['node_type' => 'gmail_send_email', 'config' => ['credential_id' => $credential->id], 'exposed_fields' => []]);

    KnowledgeSource::query()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->owner->id,
        'collection' => 'default',
        'type' => KnowledgeSourceType::Url,
        'name' => 'Mail archive',
        'connector_credential_id' => $credential->id,
    ]);

    $response = $this->getJson(($this->url)($credential, 'usage'));

    $response->assertOk();
    expect($response->json('data.usage.is_default_for_unpinned'))->toBeTrue()
        ->and(collect($response->json('data.usage.workflows'))->pluck('via', 'name')->all())
        ->toBe(['Default flow' => 'default', 'Pinned flow' => 'pinned'])
        ->and(collect($response->json('data.usage.agents'))->pluck('name')->all())->toBe(['Inbox agent'])
        ->and(collect($response->json('data.usage.knowledge_sources'))->pluck('name')->all())->toBe(['Mail archive'])
        ->and($response->json('data.usage.total'))->toBe(4);
});

it('does not count unpinned nodes for a credential that is not the default', function () {
    ($this->credential)(['is_default' => true]);
    $credential = ($this->credential)(['is_default' => false]);

    $workflow = Workflow::factory()->forWorkspace($this->workspace)->create();
    WorkflowNode::create(['workflow_id' => $workflow->id, 'key' => 'send', 'type' => 'gmail_send_email', 'config' => []]);

    $response = $this->getJson(($this->url)($credential, 'usage'));

    $response->assertOk();
    expect($response->json('data.usage.is_default_for_unpinned'))->toBeFalse()
        ->and($response->json('data.usage.total'))->toBe(0);
});

it('leaves out deleted workflows and other workspaces', function () {
    $credential = ($this->credential)();

    $deleted = Workflow::factory()->forWorkspace($this->workspace)->create();
    WorkflowNode::create(['workflow_id' => $deleted->id, 'key' => 'send', 'type' => 'gmail_send_email', 'config' => ['credential_id' => $credential->id]]);
    $deleted->delete();

    $otherWorkspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Elsewhere']);
    $foreign = Workflow::factory()->forWorkspace($otherWorkspace)->create();
    WorkflowNode::create(['workflow_id' => $foreign->id, 'key' => 'send', 'type' => 'gmail_send_email', 'config' => ['credential_id' => $credential->id]]);

    $response = $this->getJson(($this->url)($credential, 'usage'));

    $response->assertOk();
    expect($response->json('data.usage.workflows'))->toBe([]);
});
