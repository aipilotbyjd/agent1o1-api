<?php

use App\Ai\Tools\SearchKnowledgeTool;
use App\Enums\Agents\KnowledgeSourceStatus;
use App\Enums\Connectors\ConnectorCredentialScope;
use App\Enums\Workspaces\Role;
use App\Models\Agents\DocumentEmbedding;
use App\Models\Agents\KnowledgeSource;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\User;
use App\Services\Agents\Knowledge\KnowledgeSync;
use App\Services\Agents\KnowledgeBase;
use App\Services\Http\SsrfGuard;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Tools\Request;
use Laravel\Passport\Passport;

beforeEach(function () {
    Embeddings::fake();

    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
    $this->member = User::factory()->create();
    $this->workspace->members()->create(['user_id' => $this->member->id, 'role' => Role::Member, 'joined_at' => now()]);
    $this->base = "/api/v1/workspaces/{$this->workspace->id}/knowledge-base";

    app()->instance(SsrfGuard::class, new SsrfGuard(fn (string $host): array => str_ends_with($host, 'example.com') ? ['93.184.216.34'] : ['10.0.0.5']));
});

it('keeps private knowledge to its owner and out of agents', function () {
    Passport::actingAs($this->member);
    $this->postJson($this->base, ['text' => 'My salary target is confidential.', 'source' => 'Career notes', 'private' => true])->assertCreated();

    $chunk = DocumentEmbedding::query()->sole();
    expect($chunk)->owner_id->toBe($this->member->id)->collection->toBe('personal');

    $this->postJson("{$this->base}/search", ['query' => 'salary target'])->assertOk()->assertJsonPath('data.results.0.private', true);

    Passport::actingAs($this->owner);
    $this->getJson($this->base)->assertOk()->assertJsonCount(0, 'data');
    $this->getJson("{$this->base}/collections")->assertOk()->assertJsonCount(0, 'data.collections');
    $this->postJson("{$this->base}/search", ['query' => 'salary target'])->assertOk()->assertJsonCount(0, 'data.results');
    $this->getJson("{$this->base}/document?source=Career%20notes")->assertNotFound();
    $this->deleteJson("{$this->base}/{$chunk->id}")->assertNotFound();

    $agentSearch = (string) (new SearchKnowledgeTool($this->workspace, app(KnowledgeBase::class)))->handle(new Request(['query' => 'salary target']));
    expect($agentSearch)->toContain('Nothing in the knowledge base matches');
});

it('lets members add private knowledge but only managers add shared knowledge', function () {
    Passport::actingAs($this->member);

    $this->postJson($this->base, ['text' => 'Team-wide policy.'])->assertForbidden();
    $this->postJson("{$this->base}/sources", ['type' => 'url', 'name' => 'Docs', 'config' => ['url' => 'https://docs.example.com']])->assertForbidden();
});

it('syncs a web page and re-embeds only when it changes', function () {
    Http::fakeSequence('https://docs.example.com/*')
        ->push('<html><head><title>Refunds</title></head><body><p>Refunds within 30 days.</p></body></html>', 200, ['Content-Type' => 'text/html'])
        ->push('<html><head><title>Refunds</title></head><body><p>Refunds within 30 days.</p></body></html>', 200, ['Content-Type' => 'text/html'])
        ->push('<html><head><title>Refunds</title></head><body><p>Refunds within 60 days.</p></body></html>', 200, ['Content-Type' => 'text/html']);
    Passport::actingAs($this->owner);

    $this->postJson("{$this->base}/sources", ['type' => 'url', 'name' => 'Refund policy', 'config' => ['url' => 'https://docs.example.com/refunds']])
        ->assertCreated();

    $source = KnowledgeSource::query()->sole();
    $first = $source->chunks()->sole();
    expect($source->refresh())->status->toBe(KnowledgeSourceStatus::Ready)->documents_count->toBe(1)
        ->and($first)->source->toBe('Refunds')->chunk_text->toBe('Refunds within 30 days.')->owner_id->toBeNull()
        ->and($first->metadata['url'])->toBe('https://docs.example.com/refunds');

    app(KnowledgeSync::class)->sync($source);
    expect($source->chunks()->sole()->id)->toBe($first->id);

    app(KnowledgeSync::class)->sync($source);
    expect($source->chunks()->sole()->chunk_text)->toBe('Refunds within 60 days.');
});

it('refuses web pages on internal addresses', function () {
    Passport::actingAs($this->owner);

    $this->postJson("{$this->base}/sources", ['type' => 'url', 'name' => 'Intranet', 'config' => ['url' => 'https://intranet.local/']])->assertCreated();

    expect(KnowledgeSource::query()->sole())->status->toBe(KnowledgeSourceStatus::Failed)->documents_count->toBe(0);
});

it('syncs a Google Drive folder privately through the member\'s own account', function () {
    $connector = Connector::query()->where('key', 'google_drive')->first() ?? Connector::factory()->create(['key' => 'google_drive', 'name' => 'Google Drive']);
    ConnectorCredential::factory()->forWorkspace($this->workspace)->forConnector($connector)->create([
        'scope' => ConnectorCredentialScope::Personal, 'created_by' => $this->member->id, 'data' => ['access_token' => 'drive-token'],
    ]);
    Http::fake([
        'https://www.googleapis.com/drive/v3/files?*' => Http::response(['files' => [['id' => 'f1', 'name' => 'Roadmap'], ['id' => 'f2', 'name' => 'Logo.png']]]),
        'https://www.googleapis.com/drive/v3/files/f1/export*' => Http::response('Q4 roadmap: launch the assistant.'),
        'https://www.googleapis.com/drive/v3/files/f1*' => Http::response(['id' => 'f1', 'name' => 'Roadmap', 'mimeType' => 'application/vnd.google-apps.document', 'webViewLink' => 'https://docs.google.com/f1']),
        'https://www.googleapis.com/drive/v3/files/f2*' => Http::response(['id' => 'f2', 'name' => 'Logo.png', 'mimeType' => 'image/png']),
    ]);
    Passport::actingAs($this->member);

    $this->postJson("{$this->base}/sources", ['type' => 'google_drive', 'name' => 'My Drive', 'private' => true, 'config' => ['folder_id' => 'abc123']])
        ->assertCreated()
        ->assertJsonPath('data.source.private', true);

    $source = KnowledgeSource::query()->sole();
    expect($source)->documents_count->toBe(1)->sync_cursor->not->toBeNull()
        ->and($source->chunks()->sole())->source->toBe('Roadmap')->owner_id->toBe($this->member->id);

    Http::assertSent(fn (HttpRequest $request) => str_contains(urldecode($request->url()), "'abc123' in parents") && $request->hasHeader('Authorization', 'Bearer drive-token'));

    Passport::actingAs($this->owner);
    $this->getJson("{$this->base}/sources")->assertOk()->assertJsonCount(0, 'data.sources');
    $this->deleteJson("{$this->base}/sources/{$source->id}")->assertNotFound();
});

it('needs the app connected before syncing it', function () {
    Passport::actingAs($this->owner);

    $this->postJson("{$this->base}/sources", ['type' => 'github', 'name' => 'Repo', 'config' => ['repo' => 'acme/app']])
        ->assertUnprocessable()->assertJsonValidationErrors('credential_id');
});

it('lists a source\'s documents and lets the owner edit it', function () {
    Http::fake(['https://docs.example.com/*' => Http::response('<html><head><title>Refunds</title></head><body><p>Refunds within 30 days.</p></body></html>', 200, ['Content-Type' => 'text/html'])]);
    Passport::actingAs($this->member);

    $id = $this->postJson("{$this->base}/sources", ['type' => 'url', 'name' => 'Policy', 'private' => true, 'config' => ['url' => 'https://docs.example.com/refunds']])
        ->json('data.source.id');

    $this->getJson("{$this->base}/sources/{$id}/documents")->assertOk()
        ->assertJsonPath('data.documents.0.title', 'Refunds')
        ->assertJsonPath('data.documents.0.url', 'https://docs.example.com/refunds')
        ->assertJsonPath('data.documents.0.chunks_count', 1);

    $this->patchJson("{$this->base}/sources/{$id}", ['name' => 'Refund policy'])->assertOk()->assertJsonPath('data.source.name', 'Refund policy');

    Passport::actingAs($this->owner);
    $this->getJson("{$this->base}/sources/{$id}/documents")->assertNotFound();
    $this->patchJson("{$this->base}/sources/{$id}", ['name' => 'Mine now'])->assertNotFound();
});

it('lists the accounts a member can sync each app with', function () {
    $connector = Connector::query()->where('key', 'slack')->first() ?? Connector::factory()->create(['key' => 'slack', 'name' => 'Slack']);
    ConnectorCredential::factory()->forWorkspace($this->workspace)->forConnector($connector)->create(['scope' => ConnectorCredentialScope::Team, 'name' => 'Acme Slack', 'data' => ['access_token' => 'x']]);
    ConnectorCredential::factory()->forWorkspace($this->workspace)->forConnector($connector)->create(['scope' => ConnectorCredentialScope::Personal, 'created_by' => $this->owner->id, 'name' => 'Owner only', 'data' => ['access_token' => 'y']]);
    Passport::actingAs($this->member);

    $slack = collect($this->getJson("{$this->base}/sources/apps")->assertOk()->json('data.apps'))->firstWhere('type', 'slack');

    expect($slack['accounts'])->toHaveCount(1)
        ->and($slack['accounts'][0])->toMatchArray(['name' => 'Acme Slack', 'shared' => true]);
});

it('offers folders, labels, repos and channels to pick from', function (string $type, array $fake, array $expected) {
    $connector = Connector::query()->where('key', $type)->first() ?? Connector::factory()->create(['key' => $type, 'name' => $type]);
    ConnectorCredential::factory()->forWorkspace($this->workspace)->forConnector($connector)->create(['scope' => ConnectorCredentialScope::Team, 'data' => ['access_token' => 'token']]);
    Http::fake(collect($fake)->map(fn (array $body) => Http::response($body))->all());
    Passport::actingAs($this->owner);

    $options = $this->getJson("{$this->base}/sources/options?type={$type}")->assertOk()->json('data.options');

    expect(collect($options)->map(fn ($option) => [$option['value'], $option['label']])->all())->toBe($expected);
})->with([
    'drive folders' => ['google_drive', ['https://www.googleapis.com/drive/v3/files*' => ['files' => [['id' => 'f1', 'name' => 'Roadmaps']]]], [['f1', 'Roadmaps']]],
    'gmail labels' => ['gmail', ['https://gmail.googleapis.com/*' => ['labels' => [
        ['id' => 'INBOX', 'name' => 'INBOX', 'type' => 'system'], ['id' => 'SPAM', 'name' => 'SPAM', 'type' => 'system'], ['id' => 'Label_1', 'name' => 'Clients', 'type' => 'user'],
    ]]], [['Clients', 'Clients'], ['INBOX', 'Inbox']]],
    'outlook folders' => ['outlook', ['https://graph.microsoft.com/*' => ['value' => [['id' => 'AQ1', 'displayName' => 'Invoices', 'totalItemCount' => 4]]]], [['AQ1', 'Invoices']]],
    'github repos' => ['github', ['https://api.github.com/*' => [['full_name' => 'acme/app', 'description' => 'Main app']]], [['acme/app', 'acme/app']]],
    'slack channels' => ['slack', ['https://slack.com/api/*' => ['ok' => true, 'channels' => [['id' => 'C1', 'name' => 'general'], ['id' => 'C2', 'name' => 'old', 'is_archived' => true]]]], [['C1', '#general']]],
]);

it('reads a picked Gmail label', function () {
    $connector = Connector::query()->where('key', 'gmail')->first() ?? Connector::factory()->create(['key' => 'gmail', 'name' => 'Gmail']);
    ConnectorCredential::factory()->forWorkspace($this->workspace)->forConnector($connector)->create(['scope' => ConnectorCredentialScope::Team, 'data' => ['access_token' => 'g']]);
    Http::fake(['https://gmail.googleapis.com/*' => Http::response(['messages' => []])]);
    Passport::actingAs($this->owner);

    $this->postJson("{$this->base}/sources", ['type' => 'gmail', 'name' => 'Clients', 'config' => ['label' => 'Key Clients']])->assertCreated();

    Http::assertSent(fn (HttpRequest $request) => str_contains(urldecode($request->url()), 'q=label:key-clients'));
});

it('tells a member when their connection expired rather than missing', function () {
    $connector = Connector::query()->where('key', 'gmail')->first() ?? Connector::factory()->create(['key' => 'gmail', 'name' => 'Gmail']);
    ConnectorCredential::factory()->forWorkspace($this->workspace)->forConnector($connector)->create(['scope' => ConnectorCredentialScope::Team, 'data' => ['access_token' => 'old'], 'expires_at' => now()->subDay()]);
    Passport::actingAs($this->owner);

    $gmail = collect($this->getJson("{$this->base}/sources/apps")->json('data.apps'))->firstWhere('type', 'gmail');

    expect($gmail)->accounts->toBe([])->expired->toBeTrue();
});
