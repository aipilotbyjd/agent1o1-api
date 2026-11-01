<?php

use App\Enums\Triggers\SubscriptionStatus;
use App\Jobs\Triggers\FireTriggerEvent;
use App\Jobs\Triggers\PollTrigger;
use App\Jobs\Triggers\SyncTriggerSubscription;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Runs\Run;
use App\Models\Triggers\ProviderWatch;
use App\Models\Triggers\Trigger;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Nodes\Integrations\Postmark\AbstractPostmarkNode;
use App\Nodes\Integrations\Postmark\PostmarkActivateBounceNode;
use App\Nodes\Integrations\Postmark\PostmarkAddSuppressionsNode;
use App\Nodes\Integrations\Postmark\PostmarkGetStatsNode;
use App\Nodes\Integrations\Postmark\PostmarkListBouncesNode;
use App\Nodes\Integrations\Postmark\PostmarkListInboundMessagesNode;
use App\Nodes\Integrations\Postmark\PostmarkListOutboundMessagesNode;
use App\Nodes\Integrations\Postmark\PostmarkListSendersNode;
use App\Nodes\Integrations\Postmark\PostmarkListSpamComplaintsNode;
use App\Nodes\Integrations\Postmark\PostmarkSendBatchNode;
use App\Nodes\Integrations\Postmark\PostmarkSendEmailNode;
use App\Nodes\Integrations\Postmark\PostmarkSendTemplateEmailNode;
use App\Services\Triggers\Push\PostmarkSource;
use App\Services\Triggers\Push\WatchManager;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use App\Services\Workflows\NodeOptions\Sources\PostmarkOptions;
use App\Services\Workflows\NodeRegistry;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/**
 * @param  array<string, mixed>  $config
 * @return array<string, mixed>
 */
function postmarkConfig(array $config = []): array
{
    return ['access_token' => 'pm_server', ...$config];
}

/**
 * Answer Postmark. A key is "METHOD /path" (a trailing * takes any id); the value
 * is the JSON body (null is a 204), or a closure returning a response. A route
 * nobody answers is a 404, so a node that calls something unexpected fails the
 * test.
 *
 * @param  array<string, mixed>  $routes
 */
function fakePostmark(array $routes): void
{
    Http::swap(new Factory);
    Http::fake(['api.postmarkapp.com/*' => function (Request $request) use ($routes) {
        $key = $request->method().' '.urldecode((string) parse_url($request->url(), PHP_URL_PATH));
        $route = array_key_exists($key, $routes) ? $key : collect(array_keys($routes))->first(fn (string $pattern): bool => Str::is($pattern, $key));

        if ($route === null) {
            return Http::response(['ErrorCode' => 404, 'Message' => "Nothing fakes {$key}"], 404);
        }

        $answer = value($routes[$route], $request);

        return $answer instanceof GuzzleHttp\Promise\PromiseInterface ? $answer : ($answer === null ? Http::response(null, 204) : Http::response($answer));
    }]);
}

/**
 * @return array<string, mixed>
 */
function postmarkSent(string $route): array
{
    $found = array_values(array_filter(Http::recorded()->all(), fn (array $pair): bool => $pair[0]->method().' '.urldecode((string) parse_url($pair[0]->url(), PHP_URL_PATH)) === $route));

    expect($found)->toHaveCount(1);

    return (array) json_decode($found[0][0]->body(), true);
}

/**
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function postmarkBounce(int $id = 1, array $extra = []): array
{
    return ['ID' => $id, 'Type' => 'HardBounce', 'Email' => "b{$id}@example.com", 'MessageID' => "m{$id}", 'Subject' => 'Hi', 'Details' => 'Mailbox not found', 'BouncedAt' => '2026-03-01T10:00:00Z', 'Inactive' => true, 'CanActivate' => true, 'Tag' => null, ...$extra];
}

describe('postmark nodes', function () {
    it('registers the nodes under the postmark connector', function () {
        $registry = app(NodeRegistry::class);
        $types = array_filter(array_column($registry->placeableTypes(), 'type'), fn (string $type): bool => str_starts_with($type, 'postmark_'));

        expect(count($types))->toBeGreaterThanOrEqual(23);

        foreach ($types as $type) {
            expect($registry->resolve($type))->toBeInstanceOf(AbstractPostmarkNode::class)->and($registry->resolve($type)->category())->toBe('postmark');
        }
    });

    it('sends an email with the server token, tracking, metadata and headers', function () {
        fakePostmark(['POST /email' => ['MessageID' => 'pm-1', 'SubmittedAt' => '2026-03-01T10:00:00Z', 'ErrorCode' => 0]]);

        $output = (new PostmarkSendEmailNode)->execute(Run::factory()->create(), postmarkConfig([
            'sender_email' => 'hello@acme.com', 'sender_name' => 'Acme <Team>', 'to' => 'ada@example.com, bob@example.com', 'cc' => 'carol@example.com', 'subject' => "Hi\r\nBcc: x@y.z",
            'html' => '<p>Hello</p>', 'tag' => 'welcome', 'track_opens' => true, 'track_links' => 'HtmlOnly', 'metadata' => '{"order": "1001"}', 'headers' => '{"X-Ref": "a\nb"}', 'stream' => 'outbound',
        ]), []);

        expect($output)->toBe(['sent' => true, 'message_id' => 'pm-1', 'to' => ['ada@example.com', 'bob@example.com'], 'submitted_at' => '2026-03-01T10:00:00Z'])
            ->and(postmarkSent('POST /email'))->toEqual([
                'From' => 'Acme Team <hello@acme.com>', 'To' => 'ada@example.com,bob@example.com', 'Cc' => 'carol@example.com', 'Subject' => 'Hi Bcc: x@y.z', 'HtmlBody' => '<p>Hello</p>',
                'Tag' => 'welcome', 'TrackOpens' => true, 'TrackLinks' => 'HtmlOnly', 'Metadata' => ['order' => '1001'], 'Headers' => [['Name' => 'X-Ref', 'Value' => 'a b']], 'MessageStream' => 'outbound',
            ]);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('X-Postmark-Server-Token', 'pm_server'));
    });

    it('checks what it is asked to send', function () {
        $node = new PostmarkSendEmailNode;
        $run = Run::factory()->create();
        $base = ['sender_email' => 'hello@acme.com', 'to' => 'ada@example.com', 'subject' => 'x', 'text' => 'y'];

        expect(fn () => $node->execute($run, postmarkConfig([...$base, 'text' => '']), []))->toThrow(InvalidArgumentException::class, 'HTML or plain text')
            ->and(fn () => $node->execute($run, postmarkConfig([...$base, 'to' => '']), []))->toThrow(InvalidArgumentException::class, 'between 1 and 50')
            ->and(fn () => $node->execute($run, postmarkConfig([...$base, 'to' => 'nope']), []))->toThrow(InvalidArgumentException::class, 'is not an email address')
            ->and(fn () => $node->execute($run, postmarkConfig([...$base, 'metadata' => '{bad']), []))->toThrow(InvalidArgumentException::class, 'valid JSON');
    });

    it('sends from a template by id or by alias', function () {
        fakePostmark(['POST /email/withTemplate' => ['MessageID' => 'pm-2']]);
        $node = new PostmarkSendTemplateEmailNode;
        $run = Run::factory()->create();
        $base = ['sender_email' => 'hello@acme.com', 'to' => 'ada@example.com', 'template_model' => '{"name": "Ada"}'];

        $node->execute($run, postmarkConfig([...$base, 'template' => '12345']), []);
        expect(postmarkSent('POST /email/withTemplate'))->toMatchArray(['TemplateId' => 12345, 'TemplateModel' => ['name' => 'Ada'], 'InlineCss' => true])->not->toHaveKey('TemplateAlias');

        fakePostmark(['POST /email/withTemplate' => ['MessageID' => 'pm-3']]);
        $node->execute($run, postmarkConfig([...$base, 'template' => 'welcome']), []);
        expect(postmarkSent('POST /email/withTemplate'))->toMatchArray(['TemplateAlias' => 'welcome'])->not->toHaveKey('TemplateId');
    });

    it('sends a batch and says which messages Postmark refused', function () {
        fakePostmark(['POST /email/batch' => [['ErrorCode' => 0, 'MessageID' => 'a'], ['ErrorCode' => 300, 'Message' => 'Invalid email request'], ['ErrorCode' => 0, 'MessageID' => 'c']]]);
        $node = new PostmarkSendBatchNode;
        $message = ['From' => 'hello@acme.com', 'To' => 'a@x.co', 'Subject' => 'Hi', 'TextBody' => 'x'];

        expect($node->execute(Run::factory()->create(), postmarkConfig(['messages' => json_encode([$message, $message, $message])]), []))->toBe(['sent' => 2, 'message_ids' => ['a', 'c'], 'failed' => [['position' => 2, 'error' => 'Invalid email request']]])
            ->and(fn () => $node->execute(Run::factory()->create(), postmarkConfig(['messages' => json_encode([['From' => 'hello@acme.com']])]), []))->toThrow(InvalidArgumentException::class, 'Message 1 needs')
            ->and(fn () => $node->execute(Run::factory()->create(), postmarkConfig(['messages' => '[]']), []))->toThrow(InvalidArgumentException::class, 'between 1 and 500');
    });

    it('searches sent messages page by page, with the filters Postmark takes', function () {
        $message = fn (int $i): array => ['MessageID' => "m{$i}", 'From' => 'hello@acme.com', 'To' => [['Email' => 'ada@example.com']], 'Subject' => 'Hi', 'Status' => 'Sent', 'ReceivedAt' => '2026-03-01T10:00:00Z', 'Tag' => 't'];
        fakePostmark(['GET /messages/outbound' => fn (Request $request) => str_contains($request->url(), 'offset=500')
            ? ['TotalCount' => 600, 'Messages' => array_map($message, range(501, 600))]
            : ['TotalCount' => 600, 'Messages' => array_map($message, range(1, 500))]]);

        $output = (new PostmarkListOutboundMessagesNode)->execute(Run::factory()->create(), postmarkConfig(['limit' => 600, 'status' => 'sent', 'recipient' => 'ada@example.com', 'since' => '2026-03-01T00:00:00Z']), []);

        expect($output['count'])->toBe(600)->and($output['messages'][0])->toBe(['id' => 'm1', 'from' => 'hello@acme.com', 'to' => ['ada@example.com'], 'subject' => 'Hi', 'status' => 'Sent', 'tag' => 't', 'stream' => null, 'sent_at' => '2026-03-01T10:00:00Z']);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'status=sent') && str_contains($request->url(), 'recipient=ada%40example.com') && str_contains(urldecode($request->url()), 'fromdate=2026-03-01T00:00:00'));
    });

    it('lists bounces by type and reactivates one', function () {
        fakePostmark(['GET /bounces' => ['TotalCount' => 1, 'Bounces' => [postmarkBounce(7)]], 'PUT /bounces/7/activate' => ['Message' => 'OK', 'Bounce' => postmarkBounce(7, ['Inactive' => false])]]);

        $output = (new PostmarkListBouncesNode)->execute(Run::factory()->create(), postmarkConfig(['type' => 'HardBounce']), []);
        $activated = (new PostmarkActivateBounceNode)->execute(Run::factory()->create(), postmarkConfig(['bounce_id' => '7']), []);

        expect($output['bounces'][0])->toMatchArray(['id' => 7, 'type' => 'HardBounce', 'email' => 'b7@example.com', 'inactive' => true])->and($activated)->toMatchArray(['activated' => true, 'id' => 7, 'inactive' => false]);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/bounces?') && str_contains($request->url(), 'type=HardBounce'));
    });

    it('reads spam complaints as bounces of that type', function () {
        fakePostmark(['GET /bounces' => ['TotalCount' => 1, 'Bounces' => [postmarkBounce(8, ['Type' => 'SpamComplaint'])]]]);

        $output = (new PostmarkListSpamComplaintsNode)->execute(Run::factory()->create(), postmarkConfig(), []);

        expect($output['complaints'][0]['type'])->toBe('SpamComplaint');
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'type=SpamComplaint'));
    });

    it('reads a received email with its body, and looks it up for a trigger', function () {
        $detail = ['MessageID' => 'in1', 'From' => 'ada@example.com', 'FromName' => 'Ada', 'To' => 'support@inbound.postmarkapp.com', 'Subject' => 'Help', 'Date' => 'Mon, 1 Mar 2026 10:00:00', 'TextBody' => 'Hello', 'HtmlBody' => '<p>Hello</p>', 'StrippedTextReply' => 'Hello', 'MailboxHash' => 'support', 'Attachments' => [['Name' => 'a.pdf', 'ContentType' => 'application/pdf', 'ContentLength' => 100]]];
        fakePostmark(['GET /messages/inbound' => ['TotalCount' => 1, 'InboundMessages' => [['MessageID' => 'in1', 'From' => 'ada@example.com', 'FromName' => 'Ada', 'To' => 'support@inbound.postmarkapp.com', 'Subject' => 'Help', 'Date' => 'Mon', 'MailboxHash' => 'support']]], 'GET /messages/inbound/in1/details' => $detail]);
        $node = new PostmarkListInboundMessagesNode;
        $run = Run::factory()->create();

        $listed = $node->execute($run, postmarkConfig(), []);
        $expanded = $node->expandTriggerItem($run, postmarkConfig(), $listed['messages'][0]);

        expect($listed['messages'][0])->toMatchArray(['id' => 'in1', 'from_name' => 'Ada', 'mailbox_hash' => 'support'])
            ->and($expanded)->toMatchArray(['id' => 'in1', 'text' => 'Hello', 'html' => '<p>Hello</p>', 'reply_text' => 'Hello', 'attachments' => [['name' => 'a.pdf', 'content_type' => 'application/pdf', 'size' => 100]]]);
    });

    it('reads the stats for a range', function () {
        fakePostmark(['GET /stats/outbound' => ['Sent' => 100, 'Bounced' => 2, 'BounceRate' => 2.0, 'SpamComplaints' => 1, 'Opens' => 50, 'UniqueOpens' => 40, 'TotalClicks' => 10, 'UniqueLinksClicked' => 8, 'TotalTrackedLinksSent' => 30]]);
        $node = new PostmarkGetStatsNode;

        expect($node->execute(Run::factory()->create(), postmarkConfig(['from_date' => '2026-03-01', 'to_date' => '2026-03-31', 'tag' => 'welcome']), []))->toMatchArray(['sent' => 100, 'bounced' => 2, 'opens' => 50, 'clicks' => 10, 'from' => '2026-03-01', 'to' => '2026-03-31'])
            ->and(fn () => $node->execute(Run::factory()->create(), postmarkConfig(['from_date' => '2026-03-05', 'to_date' => '2026-03-01']), []))->toThrow(InvalidArgumentException::class, 'before');
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'fromdate=2026-03-01') && str_contains($request->url(), 'tag=welcome'));
    });

    it('suppresses addresses in a stream and reports the ones Postmark refused', function () {
        fakePostmark(['POST /message-streams/outbound/suppressions' => ['Suppressions' => [['EmailAddress' => 'a@x.co', 'Status' => 'Suppressed'], ['EmailAddress' => 'b@x.co', 'Status' => 'Failed', 'Message' => 'Not allowed']]]]);

        $output = (new PostmarkAddSuppressionsNode)->execute(Run::factory()->create(), postmarkConfig(['emails' => 'a@x.co, b@x.co, A@x.co']), []);

        expect($output)->toBe(['suppressed' => 1, 'failed' => [['email' => 'b@x.co', 'error' => 'Not allowed']]])
            ->and(postmarkSent('POST /message-streams/outbound/suppressions'))->toBe(['Suppressions' => [['EmailAddress' => 'a@x.co'], ['EmailAddress' => 'b@x.co']]]);
    });

    it('uses the account token for the account-wide calls, and asks for it when it is missing', function () {
        $connect = function (array $data): Run {
            $run = Run::factory()->create();

            ConnectorCredential::factory()->create([
                'workspace_id' => $run->workspace_id,
                'connector_id' => (Connector::query()->firstWhere('key', 'postmark') ?? Connector::factory()->create(['key' => 'postmark']))->id,
                'data' => $data,
                'is_default' => true,
            ]);

            return $run;
        };
        fakePostmark(['GET /senders' => ['TotalCount' => 1, 'SenderSignatures' => [['ID' => 1, 'EmailAddress' => 'hello@acme.com', 'Name' => 'Acme', 'Confirmed' => true, 'Domain' => 'acme.com']]]]);

        $output = (new PostmarkListSendersNode)->execute($connect(['api_key' => 'pm_server', 'account_token' => 'pm_account']), [], []);

        expect($output['senders'][0])->toMatchArray(['email' => 'hello@acme.com', 'confirmed' => true])
            ->and(fn () => (new PostmarkListSendersNode)->execute($connect(['api_key' => 'pm_server']), [], []))->toThrow(InvalidArgumentException::class, 'Account API token');
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('X-Postmark-Account-Token', 'pm_account'));
    });

    it('says what Postmark said when it refuses', function () {
        fakePostmark(['POST /email' => fn () => Http::response(['ErrorCode' => 400, 'Message' => 'Sender signature not found'], 422)]);

        expect(fn () => (new PostmarkSendEmailNode)->execute(Run::factory()->create(), postmarkConfig(['sender_email' => 'hello@acme.com', 'to' => 'a@b.co', 'subject' => 'x', 'text' => 'y']), []))
            ->toThrow(RuntimeException::class, 'Postmark error: Sender signature not found (code 400).');
    });
});

describe('postmark dropdowns', function () {
    it('lists message streams (not archived) and templates by alias', function () {
        fakePostmark([
            'GET /message-streams' => ['MessageStreams' => [['ID' => 'outbound', 'Name' => 'Default Transactional Stream', 'MessageStreamType' => 'Transactional'], ['ID' => 'old', 'Name' => 'Old', 'ArchivedAt' => '2026-01-01T00:00:00Z']]],
            'GET /templates' => ['TotalCount' => 1, 'Templates' => [['TemplateId' => 5, 'Name' => 'Welcome', 'Alias' => 'welcome']]],
        ]);
        $query = new NodeOptionsQuery(app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']), User::factory()->create(), [], 'pm_server');
        $source = app(PostmarkOptions::class);

        expect($source->load('postmark.streams', $query)->toArray()['options'])->toBe([['value' => 'outbound', 'label' => 'Default Transactional Stream', 'description' => 'Transactional']])
            ->and($source->load('postmark.templates', $query)->toArray()['options'])->toBe([['value' => 'welcome', 'label' => 'Welcome', 'description' => 'welcome']]);
    });
});

/**
 * A published workflow whose first step is a Postmark reader switched on as a
 * trigger, with its subscription synced against a faked Postmark.
 *
 * @param  array<string, mixed>  $nodeConfig
 * @param  array<string, mixed>  $routes
 */
function postmarkTrigger(string $nodeType = 'postmark_list_bounces', array $nodeConfig = [], array $routes = [], bool $sync = true): Trigger
{
    Queue::fake([FireTriggerEvent::class, PollTrigger::class]);
    fakePostmark($routes);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $credential = ConnectorCredential::factory()->forWorkspace($workspace)
        ->forConnector(Connector::query()->firstWhere('key', 'postmark') ?? Connector::factory()->create(['key' => 'postmark']))
        ->create(['data' => ['api_key' => 'pm_server'], 'created_by' => $owner->id]);

    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'start', 'type' => $nodeType, 'config' => ['credential_id' => $credential->id, 'activate' => true, ...$nodeConfig]],
            ['key' => 'next', 'type' => 'transform', 'config' => ['mapping' => []]],
        ],
        'edges' => [['from' => 'start', 'to' => 'next']],
    ]);
    $workflow->fresh()->publishVersion(publisher: $owner);

    $trigger = Trigger::query()->where('target_id', $workflow->id)->where('trigger_node_key', 'start')->firstOrFail();

    if ($sync) {
        SyncTriggerSubscription::dispatchSync($trigger->id);
    }

    return $trigger->fresh();
}

describe('postmark triggers', function () {
    it('polls bounces: a baseline first, then one run for each new one', function () {
        $trigger = postmarkTrigger(sync: false);
        $poll = fn (array $ids) => fakePostmark(['GET /bounces' => ['TotalCount' => count($ids), 'Bounces' => array_map(fn (int $id): array => postmarkBounce($id), $ids)]]);

        $poll([1]);
        dispatch_sync(new PollTrigger($trigger));
        expect($trigger->events()->where('status', 'queued')->count())->toBe(0);

        $poll([3, 2, 1]);
        dispatch_sync(new PollTrigger($trigger->fresh()));

        expect($trigger->events()->where('status', 'queued')->get()->pluck('payload.id')->sort()->values()->all())->toBe([2, 3]);
    });
});

describe('postmark instant delivery', function () {
    it('registers a webhook for the event on the stream with a token URL, and starts a poll when it arrives', function () {
        $trigger = postmarkTrigger(nodeConfig: ['stream' => 'broadcast'], routes: ['POST /webhooks' => ['ID' => 77]]);
        $watch = ProviderWatch::query()->sole();

        expect($watch->resource_key)->toBe('Bounce:broadcast')
            ->and($watch->external_id)->toBe('77')
            ->and($trigger->subscription->status)->toBe(SubscriptionStatus::Active)
            ->and(postmarkSent('POST /webhooks'))->toBe(['Url' => url('/api/hooks/apps/postmark').'?token='.$watch->secret, 'MessageStream' => 'broadcast', 'Triggers' => ['Bounce' => ['Enabled' => true]]]);

        $body = ['RecordType' => 'Bounce', 'ID' => 42, 'MessageID' => 'm1', 'BouncedAt' => '2026-03-01T10:00:00Z'];
        test()->postJson('/api/hooks/apps/postmark?token='.$watch->secret, $body)->assertOk();
        test()->postJson('/api/hooks/apps/postmark?token='.$watch->secret, $body)->assertOk();

        Queue::assertPushed(PollTrigger::class, 1);
    });

    it('refuses a wrong or missing token and is closed while the switch is off', function () {
        postmarkTrigger(routes: ['POST /webhooks' => ['ID' => 77]]);
        $watch = ProviderWatch::query()->sole();

        test()->postJson('/api/hooks/apps/postmark?token=nope', ['RecordType' => 'Bounce', 'ID' => 1])->assertUnauthorized();
        test()->postJson('/api/hooks/apps/postmark', ['RecordType' => 'Bounce', 'ID' => 1])->assertUnauthorized();
        config(['triggers.push.postmark.enabled' => false]);
        test()->postJson('/api/hooks/apps/postmark?token='.$watch->secret, ['RecordType' => 'Bounce', 'ID' => 1])->assertNotFound();

        Queue::assertNotPushed(PollTrigger::class);
    });

    it('says so when Postmark refuses the webhook, and drops the watch', function () {
        $trigger = postmarkTrigger(routes: ['POST /webhooks' => fn () => Http::response(['ErrorCode' => 300, 'Message' => 'Invalid URL'], 422)]);

        expect($trigger->subscription->status)->toBe(SubscriptionStatus::Failed)->and($trigger->subscription->last_error)->toContain('422')->and(ProviderWatch::query()->count())->toBe(0);
    });

    it('makes the webhook again when Postmark disabled or lost it, and removes it with the watch', function () {
        postmarkTrigger(routes: ['POST /webhooks' => ['ID' => 77]]);
        $watch = ProviderWatch::query()->sole();
        $source = app(PostmarkSource::class);

        fakePostmark(['GET /webhooks/77' => ['ID' => 77, 'Enabled' => false], 'POST /webhooks' => ['ID' => 90]]);
        app(WatchManager::class)->renew($source, $watch->fresh());
        expect($watch->fresh()->external_id)->toBe('90');

        fakePostmark(['GET /webhooks/90' => ['ID' => 90, 'Enabled' => true]]);
        app(WatchManager::class)->renew($source, $watch->fresh());
        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');

        fakePostmark(['DELETE /webhooks/90' => null]);
        app(WatchManager::class)->remove($source, $watch->fresh());
        expect(ProviderWatch::query()->count())->toBe(0);
    });

    it('takes the inbound webhook only when the server has none, and clears it with the watch', function () {
        postmarkTrigger('postmark_list_inbound_messages', routes: ['GET /server' => ['InboundHookUrl' => ''], 'PUT /server' => []]);
        $watch = ProviderWatch::query()->sole();

        expect($watch->resource_key)->toBe('Inbound')
            ->and(postmarkSent('PUT /server'))->toBe(['InboundHookUrl' => url('/api/hooks/apps/postmark').'?token='.$watch->secret]);

        test()->postJson('/api/hooks/apps/postmark?token='.$watch->secret, ['MessageID' => 'in1', 'From' => 'ada@example.com', 'FromFull' => ['Email' => 'ada@example.com'], 'Date' => 'Mon'])->assertOk();
        Queue::assertPushed(PollTrigger::class, 1);

        fakePostmark(['GET /server' => ['InboundHookUrl' => url('/api/hooks/apps/postmark').'?token='.$watch->secret], 'PUT /server' => []]);
        app(WatchManager::class)->remove(app(PostmarkSource::class), $watch->fresh());

        expect(ProviderWatch::query()->count())->toBe(0)->and(postmarkSent('PUT /server'))->toBe(['InboundHookUrl' => '']);
    });

    it('leaves an inbound webhook that is already in use alone and keeps polling', function () {
        $trigger = postmarkTrigger('postmark_list_inbound_messages', routes: ['GET /server' => ['InboundHookUrl' => 'https://crm.example.com/inbound']]);

        expect($trigger->subscription->status)->toBe(SubscriptionStatus::Failed)->and($trigger->subscription->last_error)->toContain('crm.example.com')->and(ProviderWatch::query()->count())->toBe(0);
        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'PUT');
    });
});
