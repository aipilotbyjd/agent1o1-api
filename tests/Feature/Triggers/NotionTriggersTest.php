<?php

use App\Jobs\Triggers\FireTriggerEvent;
use App\Jobs\Triggers\PollTrigger;
use App\Jobs\Triggers\SyncTriggerSubscription;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Connectors\OAuthConnectorState;
use App\Models\Nodes\NodeCategory;
use App\Models\Triggers\Trigger;
use App\Models\Triggers\TriggerSubscription;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Services\Connectors\ConnectorCredentialTester;
use App\Services\Connectors\OAuthConnectorFlowService;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

const NOTION_TEST_DB = '11111111-2222-4333-8444-555555555555';

/**
 * A published workflow whose first step is a Notion reader node switched on as
 * a trigger, with Notion push on and its subscription synced.
 *
 * @param  array<string, mixed>  $nodeConfig
 */
function notionTrigger(string $nodeType, array $nodeConfig = [], ?string $workspaceId = 'W1'): Trigger
{
    config(['triggers.push.notion.enabled' => true, 'triggers.push.notion.verification_token' => 'secret_verify']);
    Queue::fake([FireTriggerEvent::class, PollTrigger::class]);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $credential = ConnectorCredential::factory()->forWorkspace($workspace)
        ->forConnector(Connector::query()->firstWhere('key', 'notion') ?? Connector::factory()->create(['key' => 'notion']))
        ->create(['data' => array_filter(['access_token' => 'secret_test', 'workspace_id' => $workspaceId]), 'created_by' => $owner->id]);

    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'start', 'type' => $nodeType, 'config' => ['credential_id' => $credential->id, 'activate' => true, ...$nodeConfig]],
            ['key' => 'next', 'type' => 'transform', 'config' => ['mapping' => []]],
        ],
        'edges' => [['from' => 'start', 'to' => 'next']],
    ]);
    $workflow->fresh()->publishVersion(publisher: $owner);

    $trigger = Trigger::query()->where('target_id', $workflow->id)->where('trigger_node_key', 'start')->firstOrFail();
    SyncTriggerSubscription::dispatchSync($trigger->id);

    return $trigger->fresh();
}

/**
 * Deliver a Notion webhook the way Notion does: signed with the token.
 *
 * @param  array<string, mixed>  $overrides
 */
function notionWebhook(string $type, array $overrides = [], string $id = 'evt-1', ?string $secret = 'secret_verify'): TestResponse
{
    $json = json_encode(['id' => $id, 'type' => $type, 'workspace_id' => 'W1', 'entity' => ['id' => 'p1', 'type' => 'page'], 'data' => [], ...$overrides]);

    return test()->call('POST', '/api/hooks/apps/notion', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_NOTION_SIGNATURE' => 'sha256='.hash_hmac('sha256', $json, (string) $secret),
    ], $json);
}

describe('connecting', function () {
    it('seeds the notion connector and node category', function () {
        $this->syncSetup('connectors', 'app-node-categories');

        $connector = Connector::query()->where('key', 'notion')->firstOrFail();

        expect($connector->oauth)->toMatchArray([
            'authorize_url' => 'https://api.notion.com/v1/oauth/authorize',
            'token_url' => 'https://api.notion.com/v1/oauth/token',
            'token_auth' => 'basic',
            'authorize_params' => ['owner' => 'user'],
            'scopes' => [],
        ])->and(NodeCategory::query()->where('slug', 'notion')->exists())->toBeTrue();
    });

    it('sends the person to notion as the owner, with no scope', function () {
        $this->syncSetup('connectors');
        config(['services.notion.client_id' => 'cid', 'services.notion.client_secret' => 'csecret']);
        $owner = User::factory()->create();
        $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

        $url = app(OAuthConnectorFlowService::class)->initiate($workspace, $owner, Connector::query()->where('key', 'notion')->firstOrFail(), 'Notion', 'https://app.test/cb')['authorize_url'];
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        expect($url)->toStartWith('https://api.notion.com/v1/oauth/authorize?')
            ->and($query)->toMatchArray(['owner' => 'user', 'client_id' => 'cid', 'response_type' => 'code'])
            ->and($query)->not->toHaveKey('scope');
    });

    it('trades the code with basic auth and keeps the workspace id for webhooks', function () {
        $this->syncSetup('connectors');
        config(['services.notion.client_id' => 'cid', 'services.notion.client_secret' => 'csecret']);
        $connector = Connector::query()->where('key', 'notion')->firstOrFail();
        $owner = User::factory()->create();
        $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
        Http::fake(['api.notion.com/v1/oauth/token' => Http::response([
            'access_token' => 'secret_new', 'token_type' => 'bearer', 'bot_id' => 'b1', 'workspace_id' => 'W1', 'workspace_name' => 'Acme HQ',
        ])]);
        OAuthConnectorState::create([
            'workspace_id' => $workspace->id, 'user_id' => $owner->id, 'connector_id' => $connector->id,
            'state' => 'st', 'name' => 'Notion', 'redirect_uri' => 'https://app.test/cb', 'expires_at' => now()->addMinutes(10),
        ]);

        $credential = app(OAuthConnectorFlowService::class)->handleCallback('st', 'code-1');

        expect($credential->data)->toMatchArray(['access_token' => 'secret_new', 'workspace_id' => 'W1', 'workspace_name' => 'Acme HQ']);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.notion.com/v1/oauth/token'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('cid:csecret'))
            && $request->isJson()
            && $request->data() === ['code' => 'code-1', 'redirect_uri' => 'https://app.test/cb', 'grant_type' => 'authorization_code']);
    });

    it('checks the connection against the bot and names the workspace', function () {
        Http::fake(['api.notion.com/v1/users/me' => Http::response(['type' => 'bot', 'name' => 'Flows', 'bot' => ['workspace_name' => 'Acme HQ']])]);
        $owner = User::factory()->create();
        $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
        $credential = ConnectorCredential::factory()->forWorkspace($workspace)
            ->forConnector(Connector::factory()->create(['key' => 'notion', 'name' => 'Notion']))
            ->create(['data' => ['access_token' => 'secret_test'], 'created_by' => $owner->id]);

        $result = app(ConnectorCredentialTester::class)->test($credential);

        expect($result)->toMatchArray(['ok' => true, 'account' => 'Acme HQ']);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Notion-Version', '2022-06-28'));
    });
});

describe('instant delivery: a webhook wakes the trigger\'s own poll', function () {
    it('polls when something happens that the trigger is about', function (string $nodeType, string $event) {
        $trigger = notionTrigger($nodeType, ['database' => NOTION_TEST_DB, 'page' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc']);

        notionWebhook($event, ['data' => ['parent' => ['id' => NOTION_TEST_DB, 'type' => 'database']]])->assertOk();

        Queue::assertPushed(PollTrigger::class, fn (PollTrigger $job): bool => $job->trigger->is($trigger));
        expect(TriggerSubscription::query()->where('trigger_id', $trigger->id)->first()->routing_key)->toBe('W1');
    })->with([
        'new item' => ['notion_list_new_database_items', 'page.created'],
        'edited item' => ['notion_list_updated_database_items', 'page.properties_updated'],
        'edited content' => ['notion_list_updated_pages', 'page.content_updated'],
        'new page' => ['notion_list_new_pages', 'page.created'],
        'new database' => ['notion_list_new_databases', 'database.created'],
        'comment' => ['notion_list_new_comments', 'comment.created'],
    ]);

    it('ignores a change in another database', function () {
        notionTrigger('notion_list_new_database_items', ['database' => NOTION_TEST_DB]);

        notionWebhook('page.created', ['data' => ['parent' => ['id' => '99999999-9999-4999-8999-999999999999', 'type' => 'database']]])->assertOk();

        Queue::assertNotPushed(PollTrigger::class);
    });

    it('ignores a comment on another page', function () {
        notionTrigger('notion_list_new_comments', ['page' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc']);

        notionWebhook('comment.created', ['data' => ['page_id' => 'dddddddd-dddd-4ddd-8ddd-dddddddddddd']])->assertOk();
        Queue::assertNotPushed(PollTrigger::class);

        notionWebhook('comment.created', ['data' => ['page_id' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc']], 'evt-2')->assertOk();
        Queue::assertPushed(PollTrigger::class);
    });

    it('ignores an event of another kind, and one for another workspace', function () {
        notionTrigger('notion_list_new_database_items', ['database' => NOTION_TEST_DB]);

        notionWebhook('page.deleted')->assertOk();
        notionWebhook('page.created', ['workspace_id' => 'OTHER'], 'evt-2')->assertOk();

        Queue::assertNotPushed(PollTrigger::class);
        Queue::assertNotPushed(FireTriggerEvent::class);
    });

    it('handles the same delivery once', function () {
        notionTrigger('notion_list_new_pages');

        notionWebhook('page.created')->assertOk();
        notionWebhook('page.created')->assertOk();

        Queue::assertPushed(PollTrigger::class, 1);
    });

    it('does not trust a delivery with a missing or wrong signature', function () {
        notionTrigger('notion_list_new_pages');

        notionWebhook('page.created', secret: 'guess')->assertUnauthorized();
        test()->postJson('/api/hooks/apps/notion', ['id' => 'x', 'type' => 'page.created', 'workspace_id' => 'W1'])->assertUnauthorized();

        Queue::assertNotPushed(PollTrigger::class);
    });

    it('answers the verification request, and logs the token while none is set', function () {
        config(['triggers.push.notion.enabled' => true, 'triggers.push.notion.verification_token' => 'secret_verify']);
        Log::spy();

        test()->postJson('/api/hooks/apps/notion', ['verification_token' => 'secret_verify'])->assertOk()->assertJson(['ok' => true]);
        Log::shouldNotHaveReceived('info');

        // Not set yet: the request is still answered, and the operator reads the token from the log.
        config(['triggers.push.notion.verification_token' => null]);
        test()->postJson('/api/hooks/apps/notion', ['verification_token' => 'secret_from_notion'])->assertOk();
        Log::shouldHaveReceived('info')->once()->withArgs(fn (string $message, array $context): bool => $context === ['verification_token' => 'secret_from_notion']);

        // Events stay refused until the token is set.
        notionWebhook('page.created', secret: 'secret_from_notion')->assertUnauthorized();
    });

    it('keeps polling when push is off or the account has no workspace id', function () {
        $withoutWorkspace = notionTrigger('notion_list_new_pages', workspaceId: null);

        expect(TriggerSubscription::query()->where('trigger_id', $withoutWorkspace->id)->where('status', 'active')->exists())->toBeFalse();

        config(['triggers.push.notion.enabled' => false]);
        test()->postJson('/api/hooks/apps/notion', ['verification_token' => 'x'])->assertNotFound();
    });

    it('leaves new workspace members to the timer: notion sends nothing for them', function () {
        $trigger = notionTrigger('notion_list_new_users');

        expect(TriggerSubscription::query()->where('trigger_id', $trigger->id)->exists())->toBeFalse();
    });
});
