<?php

use App\Enums\Agents\KnowledgeSourceType;
use App\Enums\Connectors\ConnectorCategory;
use App\Enums\Connectors\ConnectorCredentialScope;
use App\Enums\Workspaces\AuditAction;
use App\Jobs\Connectors\CheckConnectorCredentialHealthJob;
use App\Models\Agents\Agent;
use App\Models\Agents\KnowledgeSource;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Connectors\OAuthConnectorState;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowNode;
use App\Models\Workspaces\AuditLog;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
    $this->gmail = Connector::query()->where('key', 'gmail')->first() ?? Connector::factory()->oauth()->create(['key' => 'gmail', 'name' => 'Gmail']);
    $this->credential = fn (array $attributes = []) => ConnectorCredential::factory()->forWorkspace($this->workspace)->forConnector($this->gmail)->create([
        'data' => ['access_token' => 'token', 'refresh_token' => 'refresh'],
        'expires_at' => now()->addHour(),
        'created_by' => $this->owner->id,
        ...$attributes,
    ]);
    $this->base = "/api/v1/workspaces/{$this->workspace->id}/connector-credentials";

    Passport::actingAs($this->owner);
});

it('serves catalog category and featured flags from the backend', function () {
    Connector::factory()->create(['key' => 'crm_app', 'category' => ConnectorCategory::Marketing, 'is_featured' => true]);

    $connector = collect($this->getJson('/api/v1/connectors')->assertOk()->json('data.connectors'))->firstWhere('key', 'crm_app');

    expect($connector)->toMatchArray([
        'category' => 'marketing',
        'category_label' => 'Marketing',
        'category_icon' => 'megaphone',
        'is_featured' => true,
    ]);
});

it('records whose account a test belongs to and keeps the last result', function () {
    Http::fake(['www.googleapis.com/oauth2/v3/tokeninfo*' => Http::response(['email' => 'team@example.com'])]);
    $credential = ($this->credential)();

    $this->postJson("{$this->base}/{$credential->id}/test")->assertOk();

    $credential->refresh();
    expect($credential->account_label)->toBe('team@example.com')
        ->and($credential->last_test_ok)->toBeTrue()
        ->and($credential->last_tested_at)->not->toBeNull();

    $this->getJson("{$this->base}/{$credential->id}")
        ->assertOk()
        ->assertJsonPath('data.connector_credential.account_label', 'team@example.com')
        ->assertJsonPath('data.connector_credential.last_test_ok', true);
});

it('rate limits connection tests per member', function () {
    Http::fake(['*' => Http::response(['email' => 'team@example.com'])]);
    $credential = ($this->credential)();

    foreach (range(1, 10) as $attempt) {
        $this->postJson("{$this->base}/{$credential->id}/test")->assertOk();
    }

    $this->postJson("{$this->base}/{$credential->id}/test")->assertTooManyRequests();
});

it('audits renames and default changes', function () {
    $credential = ($this->credential)();

    $this->patchJson("{$this->base}/{$credential->id}", ['name' => 'Renamed'])->assertOk();
    $this->postJson("{$this->base}/{$credential->id}/default")->assertOk();

    expect(AuditLog::query()->where('workspace_id', $this->workspace->id)->pluck('action')->map(fn ($action) => $action instanceof BackedEnum ? $action->value : $action)->all())
        ->toContain(AuditAction::ConnectorCredentialUpdated->value, AuditAction::ConnectorCredentialDefaultChanged->value);
});

it('moves everything pinned to an account onto its replacement before disconnecting', function () {
    $old = ($this->credential)(['is_default' => true]);
    $replacement = ($this->credential)(['is_default' => false]);

    $workflow = Workflow::factory()->forWorkspace($this->workspace)->create();
    $node = WorkflowNode::create(['workflow_id' => $workflow->id, 'key' => 'send', 'type' => 'gmail_send_email', 'config' => ['credential_id' => $old->id, 'to' => 'a@b.c']]);
    $version = $workflow->versions()->create([
        'version' => 1,
        'graph' => ['nodes' => [['key' => 'send', 'type' => 'gmail_send_email', 'config' => ['credential_id' => $old->id], 'pinned_data' => null]], 'edges' => []],
    ]);
    $workflow->forceFill(['current_version_id' => $version->id])->save();

    $agent = Agent::factory()->forWorkspace($this->workspace)->create();
    $binding = $agent->toolBindings()->create(['node_type' => 'gmail_send_email', 'config' => ['credential_id' => $old->id], 'exposed_fields' => []]);

    $source = KnowledgeSource::query()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->owner->id,
        'collection' => 'default',
        'type' => KnowledgeSourceType::Url,
        'name' => 'Inbox',
        'connector_credential_id' => $old->id,
    ]);

    $this->deleteJson("{$this->base}/{$old->id}", ['replace_with' => $replacement->id])->assertNoContent();

    expect($old->fresh()->trashed())->toBeTrue()
        ->and($node->fresh()->config)->toBe(['credential_id' => $replacement->id, 'to' => 'a@b.c'])
        ->and($version->fresh()->graph['nodes'][0]['config']['credential_id'])->toBe($replacement->id)
        ->and($binding->fresh()->config['credential_id'])->toBe($replacement->id)
        ->and($source->fresh()->connector_credential_id)->toBe($replacement->id)
        ->and($replacement->fresh()->is_default)->toBeTrue();
});

it('refuses a replacement that cannot stand in for a shared account', function () {
    $old = ($this->credential)();
    $personal = ($this->credential)(['scope' => ConnectorCredentialScope::Personal]);

    $this->deleteJson("{$this->base}/{$old->id}", ['replace_with' => $personal->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('replace_with');

    expect($old->fresh())->not->toBeNull();
});

it('learns the account identity right after an oauth connect', function () {
    config(['services.gmail.client_id' => 'cid', 'services.gmail.client_secret' => 'secret']);
    $this->gmail->forceFill(['auth_type' => 'oauth2', 'oauth' => ['authorize_url' => 'https://accounts.google.com/o/oauth2/v2/auth', 'token_url' => 'https://oauth2.googleapis.com/token', 'scopes' => []]])->save();

    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'fresh', 'refresh_token' => 'r', 'expires_in' => 3600]),
        'www.googleapis.com/oauth2/v3/tokeninfo*' => Http::response(['email' => 'new@example.com']),
    ]);

    OAuthConnectorState::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->owner->id,
        'connector_id' => $this->gmail->id,
        'state' => 'identity-state',
        'name' => 'My Gmail',
        'redirect_uri' => 'https://app.test/callback',
        'expires_at' => now()->addMinutes(10),
    ]);

    $this->getJson('/api/oauth/connectors/callback?state=identity-state&code=abc')
        ->assertCreated()
        ->assertJsonPath('data.connector_credential.account_label', 'new@example.com');
});

it('queues health checks only for usable connections not checked recently', function () {
    Queue::fake();
    $stale = ($this->credential)();
    ($this->credential)()->forceFill(['last_tested_at' => now()->subHour()])->save();
    ($this->credential)(['data' => ['access_token' => 'dead'], 'expires_at' => now()->subDay()]);

    $this->artisan('connectors:check-health')->assertSuccessful();

    Queue::assertPushed(CheckConnectorCredentialHealthJob::class, 1);
    Queue::assertPushed(CheckConnectorCredentialHealthJob::class, fn ($job) => $job->credential->is($stale));
});
