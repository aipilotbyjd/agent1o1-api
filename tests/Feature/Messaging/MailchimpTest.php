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
use App\Nodes\Integrations\Mailchimp\AbstractMailchimpNode;
use App\Nodes\Integrations\Mailchimp\MailchimpAddOrUpdateMemberNode;
use App\Nodes\Integrations\Mailchimp\MailchimpAddTagsNode;
use App\Nodes\Integrations\Mailchimp\MailchimpCheckSendReadinessNode;
use App\Nodes\Integrations\Mailchimp\MailchimpCreateCampaignNode;
use App\Nodes\Integrations\Mailchimp\MailchimpListMembersNode;
use App\Nodes\Integrations\Mailchimp\MailchimpScheduleCampaignNode;
use App\Nodes\Integrations\Mailchimp\MailchimpSearchMembersNode;
use App\Nodes\Integrations\Mailchimp\MailchimpUnsubscribeMemberNode;
use App\Nodes\Integrations\Mailchimp\MailchimpUpdateMemberNode;
use App\Services\Triggers\Push\MailchimpSource;
use App\Services\Triggers\Push\WatchManager;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use App\Services\Workflows\NodeOptions\Sources\MailchimpOptions;
use App\Services\Workflows\NodeRegistry;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

const MAILCHIMP_KEY = 'abcdef0123456789-us21';
const MAILCHIMP_LIST = 'a1b2c3d4e5';

/**
 * @param  array<string, mixed>  $config
 * @return array<string, mixed>
 */
function mailchimpConfig(array $config = []): array
{
    return ['access_token' => MAILCHIMP_KEY, 'list_id' => MAILCHIMP_LIST, ...$config];
}

/**
 * Answer Mailchimp. A key is "METHOD /path" under /3.0 (a trailing * takes any id);
 * the value is the JSON body, a closure taking the request, or a ready response.
 * A route nobody answers is a 404, so a node that calls something unexpected
 * fails the test.
 *
 * @param  array<string, mixed>  $routes
 */
function fakeMailchimp(array $routes): void
{
    Http::swap(new Factory);
    Http::fake(['*.api.mailchimp.com/*' => function (Request $request) use ($routes) {
        $key = $request->method().' '.preg_replace('~^/3\.0~', '', (string) parse_url($request->url(), PHP_URL_PATH));
        $route = array_key_exists($key, $routes) ? $key : collect(array_keys($routes))->first(fn (string $pattern): bool => Str::is($pattern, $key));

        if ($route === null) {
            return Http::response(['title' => 'Resource Not Found', 'status' => 404, 'detail' => "Nothing fakes {$key}"], 404);
        }

        $answer = value($routes[$route], $request);

        return $answer instanceof GuzzleHttp\Promise\PromiseInterface ? $answer : ($answer === null ? Http::response(null, 204) : Http::response($answer));
    }]);
}

/**
 * The JSON body of the one request to a route.
 *
 * @return array<string, mixed>
 */
function mailchimpSent(string $route): array
{
    $found = array_values(array_filter(Http::recorded()->all(), fn (array $pair): bool => $pair[0]->method().' '.preg_replace('~^/3\.0~', '', (string) parse_url($pair[0]->url(), PHP_URL_PATH)) === $route));

    expect($found)->toHaveCount(1);

    return (array) json_decode($found[0][0]->body(), true);
}

/**
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function mailchimpMember(string $email = 'ada@example.com', array $extra = []): array
{
    return [
        'id' => md5($email), 'email_address' => $email, 'status' => 'subscribed', 'list_id' => MAILCHIMP_LIST,
        'merge_fields' => ['FNAME' => 'Ada', 'LNAME' => 'Lovelace', 'PHONE' => ''], 'tags' => [['id' => 1, 'name' => 'customer']], 'vip' => false, 'language' => 'en',
        'timestamp_opt' => '2026-03-01T10:00:00+00:00', 'last_changed' => '2026-03-01T10:00:00+00:00', ...$extra,
    ];
}

describe('mailchimp nodes', function () {
    it('registers the nodes under the mailchimp connector', function () {
        $registry = app(NodeRegistry::class);
        $types = array_filter(array_column($registry->placeableTypes(), 'type'), fn (string $type): bool => str_starts_with($type, 'mailchimp_'));

        expect(count($types))->toBeGreaterThanOrEqual(40);

        foreach ($types as $type) {
            expect($registry->resolve($type))->toBeInstanceOf(AbstractMailchimpNode::class)->and($registry->resolve($type)->category())->toBe('mailchimp');
        }
    });

    it('works out the host from the data center the key ends with, and refuses a key without one', function () {
        expect(AbstractMailchimpNode::baseUrl('k-us21'))->toBe('https://us21.api.mailchimp.com/3.0')
            ->and(fn () => AbstractMailchimpNode::baseUrl('nokeysuffix'))->toThrow(InvalidArgumentException::class, 'ends with its data center')
            ->and(fn () => AbstractMailchimpNode::baseUrl('k-nodatacenter!'))->toThrow(InvalidArgumentException::class, 'ends with its data center');
    });

    it('adds or updates a member by the hash of the lower-case email, with only what is set', function () {
        $hash = md5('ada@example.com');
        fakeMailchimp(["PUT /lists/".MAILCHIMP_LIST."/members/{$hash}" => mailchimpMember()]);

        $output = (new MailchimpAddOrUpdateMemberNode)->execute(Run::factory()->create(), mailchimpConfig([
            'email' => ' Ada@Example.com ', 'first_name' => 'Ada', 'merge_fields' => '{"COMPANY": "Acme"}', 'tags' => 'customer, vip', 'status_if_new' => 'pending', 'status' => 'unsubscribed', 'vip' => 'yes',
        ]), []);

        expect($output)->toMatchArray(['id' => $hash, 'email' => 'ada@example.com', 'status' => 'subscribed', 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'phone' => null, 'tags' => ['customer']])
            ->and(mailchimpSent("PUT /lists/".MAILCHIMP_LIST."/members/{$hash}"))->toBe([
                'email_address' => 'ada@example.com', 'status_if_new' => 'pending', 'status' => 'unsubscribed', 'merge_fields' => ['COMPANY' => 'Acme', 'FNAME' => 'Ada'], 'tags' => ['customer', 'vip'], 'vip' => true,
            ]);
        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://us21.api.mailchimp.com/3.0/') && $request->hasHeader('Authorization', 'Basic '.base64_encode('flows:'.MAILCHIMP_KEY)));
    });

    it('checks the audience, the email and the status before calling', function () {
        fakeMailchimp([]);
        $node = new MailchimpAddOrUpdateMemberNode;
        $run = Run::factory()->create();

        expect(fn () => $node->execute($run, mailchimpConfig(['email' => 'not-an-email']), []))->toThrow(InvalidArgumentException::class, 'is not an email address')
            ->and(fn () => $node->execute($run, mailchimpConfig(['list_id' => 'Newsletter', 'email' => 'a@b.co']), []))->toThrow(InvalidArgumentException::class, 'not a Mailchimp audience id')
            ->and(fn () => $node->execute($run, mailchimpConfig(['email' => 'a@b.co', 'status_if_new' => 'cleaned']), []))->toThrow(InvalidArgumentException::class, 'not a status for a new member');
        Http::assertNothingSent();
    });

    it('updates a member and refuses an update that changes nothing', function () {
        $hash = md5('ada@example.com');
        fakeMailchimp(["PATCH /lists/".MAILCHIMP_LIST."/members/{$hash}" => mailchimpMember()]);
        $node = new MailchimpUpdateMemberNode;

        $node->execute(Run::factory()->create(), mailchimpConfig(['email' => 'ada@example.com', 'last_name' => 'Byron', 'new_email' => 'ada@new.test']), []);

        expect(mailchimpSent("PATCH /lists/".MAILCHIMP_LIST."/members/{$hash}"))->toBe(['email_address' => 'ada@new.test', 'merge_fields' => ['LNAME' => 'Byron']])
            ->and(fn () => $node->execute(Run::factory()->create(), mailchimpConfig(['email' => 'ada@example.com']), []))->toThrow(InvalidArgumentException::class, 'Change at least one thing');
    });

    it('unsubscribes, tags and untags', function () {
        $hash = md5('ada@example.com');
        fakeMailchimp([
            "PATCH /lists/".MAILCHIMP_LIST."/members/{$hash}" => mailchimpMember('ada@example.com', ['status' => 'unsubscribed']),
            "POST /lists/".MAILCHIMP_LIST."/members/{$hash}/tags" => null,
        ]);
        $run = Run::factory()->create();

        $unsubscribed = (new MailchimpUnsubscribeMemberNode)->execute($run, mailchimpConfig(['email' => 'ada@example.com']), []);
        (new MailchimpAddTagsNode)->execute($run, mailchimpConfig(['email' => 'ada@example.com', 'tags' => ['a', 'b']]), []);

        expect($unsubscribed['status'])->toBe('unsubscribed')
            ->and(mailchimpSent("POST /lists/".MAILCHIMP_LIST."/members/{$hash}/tags"))->toBe(['tags' => [['name' => 'a', 'status' => 'active'], ['name' => 'b', 'status' => 'active']]])
            ->and(fn () => (new MailchimpAddTagsNode)->execute($run, mailchimpConfig(['email' => 'ada@example.com', 'tags' => '']), []))->toThrow(InvalidArgumentException::class, 'at least one tag');
    });

    it('lists members page by page and filters by tag', function () {
        fakeMailchimp(['GET /lists/'.MAILCHIMP_LIST.'/members' => fn (Request $request) => str_contains($request->url(), 'offset=0')
            ? ['members' => [mailchimpMember('a@x.co'), mailchimpMember('b@x.co', ['tags' => []])]]
            : ['members' => [mailchimpMember('c@x.co')]]]);

        $output = (new MailchimpListMembersNode)->execute(Run::factory()->create(), mailchimpConfig(['status' => 'subscribed', 'changed_since' => '2026-03-01 00:00:00', 'tag' => 'Customer', 'limit' => 2000]), []);

        expect(array_column($output['members'], 'email'))->toBe(['a@x.co']);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'status=subscribed') && str_contains(urldecode($request->url()), 'since_last_changed=2026-03-01T00:00:00') && str_contains($request->url(), 'count=1000'));
    });

    it('searches members across all audiences and reads exact matches first', function () {
        fakeMailchimp(['GET /search-members' => ['exact_matches' => ['members' => [mailchimpMember('ada@example.com')]], 'full_search' => ['members' => [mailchimpMember('adam@example.com')]]]]);

        $output = (new MailchimpSearchMembersNode)->execute(Run::factory()->create(), mailchimpConfig(['list_id' => '', 'query' => 'ada']), []);

        expect(array_column($output['members'], 'email'))->toBe(['ada@example.com', 'adam@example.com']);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'query=ada') && ! str_contains($request->url(), 'list_id'));
    });

    it('creates a campaign aimed at a segment and refuses a past schedule', function () {
        fakeMailchimp(['POST /campaigns' => ['id' => 'cafe123456', 'type' => 'regular', 'status' => 'save', 'settings' => ['title' => 'March', 'subject_line' => 'March news'], 'recipients' => ['list_id' => MAILCHIMP_LIST]]]);

        $output = (new MailchimpCreateCampaignNode)->execute(Run::factory()->create(), mailchimpConfig(['subject' => 'March news', 'from_name' => 'Acme', 'reply_to' => 'hello@acme.com', 'segment_id' => '42', 'template_id' => '7']), []);

        expect($output)->toMatchArray(['id' => 'cafe123456', 'status' => 'save', 'subject' => 'March news'])
            ->and(mailchimpSent('POST /campaigns'))->toBe([
                'type' => 'regular',
                'recipients' => ['list_id' => MAILCHIMP_LIST, 'segment_opts' => ['saved_segment_id' => 42]],
                'settings' => ['subject_line' => 'March news', 'title' => 'March news', 'from_name' => 'Acme', 'reply_to' => 'hello@acme.com', 'template_id' => 7],
            ])
            ->and(fn () => (new MailchimpScheduleCampaignNode)->execute(Run::factory()->create(), ['access_token' => MAILCHIMP_KEY, 'campaign_id' => 'cafe123456', 'send_at' => '2020-01-01 10:00:00'], []))->toThrow(InvalidArgumentException::class, 'in the future');
    });

    it('schedules in UTC and says what is missing before a send', function () {
        fakeMailchimp([
            'POST /campaigns/cafe123456/actions/schedule' => null,
            'GET /campaigns/cafe123456/send-checklist' => ['is_ready' => false, 'items' => [['type' => 'error', 'heading' => 'Subject', 'details' => 'is empty'], ['type' => 'success', 'heading' => 'List', 'details' => 'ok']]],
        ]);
        $run = Run::factory()->create();

        (new MailchimpScheduleCampaignNode)->execute($run, ['access_token' => MAILCHIMP_KEY, 'campaign_id' => 'cafe123456', 'send_at' => now()->addDay()->setTimezone('Asia/Tokyo')->toIso8601String()], []);
        $ready = (new MailchimpCheckSendReadinessNode)->execute($run, ['access_token' => MAILCHIMP_KEY, 'campaign_id' => 'cafe123456'], []);

        expect(mailchimpSent('POST /campaigns/cafe123456/actions/schedule')['schedule_time'])->toEndWith('+00:00')
            ->and($ready)->toBe(['campaign_id' => 'cafe123456', 'ready' => false, 'problems' => ['Subject: is empty']]);
    });

    it('turns Mailchimp\'s refusal into an error that says what it said', function () {
        fakeMailchimp(['PUT /lists/*' => fn () => Http::response(['title' => 'Forgotten Email Not Subscribed', 'status' => 400, 'detail' => 'ada@example.com was permanently deleted from this list and cannot be re-imported.', 'errors' => [['field' => 'email_address', 'message' => 'blocked']]], 400)]);

        expect(fn () => (new MailchimpAddOrUpdateMemberNode)->execute(Run::factory()->create(), mailchimpConfig(['email' => 'ada@example.com']), []))
            ->toThrow(RuntimeException::class, 'Mailchimp error: ada@example.com was permanently deleted from this list and cannot be re-imported. (email_address blocked)');
    });
});

describe('mailchimp dropdowns', function () {
    it('lists audiences, campaigns by title and an audience\'s segments, a page at a time', function () {
        fakeMailchimp([
            'GET /lists' => ['total_items' => 150, 'lists' => [['id' => MAILCHIMP_LIST, 'name' => 'Newsletter', 'stats' => ['member_count' => 42]]]],
            'GET /campaigns' => ['total_items' => 1, 'campaigns' => [['id' => 'cafe123456', 'status' => 'sent', 'settings' => ['title' => 'March', 'subject_line' => 'News']]]],
            'GET /lists/'.MAILCHIMP_LIST.'/segments' => ['total_items' => 1, 'segments' => [['id' => 9, 'name' => 'VIP', 'member_count' => 3]]],
        ]);
        $query = fn (array $config = []): NodeOptionsQuery => new NodeOptionsQuery(app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']), User::factory()->create(), $config, MAILCHIMP_KEY);
        $source = app(MailchimpOptions::class);

        expect($source->load('mailchimp.audiences', $query())->toArray())->toBe(['options' => [['value' => MAILCHIMP_LIST, 'label' => 'Newsletter', 'description' => '42 members']], 'next_cursor' => '100'])
            ->and($source->load('mailchimp.campaigns', $query())->toArray()['options'][0])->toBe(['value' => 'cafe123456', 'label' => 'March', 'description' => 'Sent'])
            ->and($source->load('mailchimp.segments', $query(['list_id' => MAILCHIMP_LIST]))->toArray()['options'])->toBe([['value' => '9', 'label' => 'VIP', 'description' => '3 members']])
            ->and(fn () => $source->load('mailchimp.segments', $query()))->toThrow(App\Exceptions\ConnectorException::class, 'Choose an audience first');
    });
});

/**
 * A published workflow whose first step is a Mailchimp member trigger, with its
 * subscription synced against a faked Mailchimp.
 *
 * @param  array<string, mixed>  $nodeConfig
 * @param  array<string, mixed>  $routes
 */
function mailchimpTrigger(string $nodeType = 'mailchimp_list_new_subscribers', array $nodeConfig = ['list_id' => MAILCHIMP_LIST], array $routes = [], bool $sync = true): Trigger
{
    Queue::fake([FireTriggerEvent::class, PollTrigger::class]);
    fakeMailchimp($routes);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $credential = ConnectorCredential::factory()->forWorkspace($workspace)
        ->forConnector(Connector::query()->firstWhere('key', 'mailchimp') ?? Connector::factory()->create(['key' => 'mailchimp']))
        ->create(['data' => ['api_key' => MAILCHIMP_KEY], 'created_by' => $owner->id]);

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

/**
 * A webhook as Mailchimp posts it: a form to the URL with the watch's token.
 *
 * @param  array<string, mixed>  $fields
 */
function mailchimpDelivery(string $token, array $fields = ['type' => 'subscribe', 'fired_at' => '2026-03-01 10:00:00', 'data' => ['email' => 'ada@example.com', 'list_id' => MAILCHIMP_LIST]])
{
    return test()->call('POST', '/api/hooks/apps/mailchimp?token='.$token, $fields);
}

describe('mailchimp triggers', function () {
    it('reads each kind of change from the audience, newest first, since a look-back', function (string $type, array $expected, string $id) {
        fakeMailchimp(['GET /lists/'.MAILCHIMP_LIST.'/members' => ['members' => [mailchimpMember('ada@example.com', ['status' => 'unsubscribed'])]]]);
        $node = app(NodeRegistry::class)->resolve($type);

        $output = $node->execute(Run::factory()->create(), mailchimpConfig($node->triggerReadConfig(mailchimpConfig())), []);

        expect($node->triggerItems($output, []))->toHaveCount(1)
            ->and($node->triggerItemId($output['members'][0]))->toBe($id);
        Http::assertSent(function (Request $request) use ($expected): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return array_intersect_key($query, $expected) === $expected && isset($query['since_timestamp_opt']) !== isset($query['since_last_changed']);
        });
    })->with([
        'new subscribers' => ['mailchimp_list_new_subscribers', ['status' => 'subscribed', 'sort_field' => 'timestamp_opt', 'sort_dir' => 'DESC'], md5('ada@example.com')],
        'unsubscribes' => ['mailchimp_list_unsubscribes', ['status' => 'unsubscribed', 'sort_field' => 'last_changed'], md5('ada@example.com').':2026-03-01T10:00:00+00:00'],
        'cleaned' => ['mailchimp_list_cleaned_members', ['status' => 'cleaned', 'sort_field' => 'last_changed'], md5('ada@example.com').':2026-03-01T10:00:00+00:00'],
        'updates' => ['mailchimp_list_updated_members', ['sort_field' => 'last_changed'], md5('ada@example.com').':2026-03-01T10:00:00+00:00'],
    ]);

    it('polls new subscribers: a baseline first, then one run for each', function () {
        $trigger = mailchimpTrigger(sync: false);
        $poll = fn (array $emails) => fakeMailchimp(['GET /lists/'.MAILCHIMP_LIST.'/members' => ['members' => array_map(fn (string $email): array => mailchimpMember($email), $emails)]]);

        $poll(['a@x.co']);
        dispatch_sync(new PollTrigger($trigger));
        expect($trigger->events()->where('status', 'queued')->count())->toBe(0);

        $poll(['c@x.co', 'b@x.co', 'a@x.co']);
        dispatch_sync(new PollTrigger($trigger->fresh()));

        expect($trigger->events()->where('status', 'queued')->get()->pluck('payload.email')->sort()->values()->all())->toBe(['b@x.co', 'c@x.co']);
    });

    it('lists sent campaigns for the campaign-sent trigger', function () {
        fakeMailchimp(['GET /campaigns' => ['campaigns' => [['id' => 'cafe123456', 'status' => 'sent', 'send_time' => '2026-03-01T09:00:00+00:00', 'settings' => ['title' => 'March', 'subject_line' => 'News']]]]]);
        $node = app(NodeRegistry::class)->resolve('mailchimp_list_campaigns');

        $output = $node->execute(Run::factory()->create(), $node->triggerReadConfig(['access_token' => MAILCHIMP_KEY]), []);

        expect($output['campaigns'][0])->toMatchArray(['id' => 'cafe123456', 'send_time' => '2026-03-01T09:00:00+00:00'])
            ->and($node->triggerName())->toBe('Mailchimp: Campaign sent');
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'status=sent') && str_contains($request->url(), 'sort_field=send_time'));
    });
});

describe('mailchimp instant delivery', function () {
    it('registers one audience webhook with a token URL, and starts a poll when it arrives', function () {
        $trigger = mailchimpTrigger(routes: ['POST /lists/'.MAILCHIMP_LIST.'/webhooks' => ['id' => 'wh1']]);
        $watch = ProviderWatch::query()->sole();

        expect($watch->resource_key)->toBe('list:'.MAILCHIMP_LIST)
            ->and($watch->external_id)->toBe('wh1')
            ->and($watch->meta['token_hash'])->toBe(hash('sha256', $watch->secret))
            ->and($trigger->subscription->routing_key)->toBe(hash('sha256', $watch->secret))
            ->and($trigger->subscription->status)->toBe(SubscriptionStatus::Active)
            ->and(mailchimpSent('POST /lists/'.MAILCHIMP_LIST.'/webhooks'))->toBe([
                'url' => url('/api/hooks/apps/mailchimp').'?token='.$watch->secret,
                'events' => ['subscribe' => true, 'unsubscribe' => true, 'profile' => true, 'cleaned' => true, 'upemail' => true, 'campaign' => false],
                'sources' => ['user' => true, 'admin' => true, 'api' => true],
            ]);

        mailchimpDelivery((string) $watch->secret)->assertOk();
        mailchimpDelivery((string) $watch->secret)->assertOk();

        Queue::assertPushed(PollTrigger::class, 1);
    });

    it('lets Mailchimp check the URL with an empty request, and refuses a wrong or missing token', function () {
        mailchimpTrigger(routes: ['POST /lists/'.MAILCHIMP_LIST.'/webhooks' => ['id' => 'wh1']]);
        $watch = ProviderWatch::query()->sole();

        test()->get('/api/hooks/apps/mailchimp?token='.$watch->secret)->assertOk();
        test()->post('/api/hooks/apps/mailchimp?token='.$watch->secret)->assertOk();
        mailchimpDelivery('not-the-token')->assertUnauthorized();
        test()->post('/api/hooks/apps/mailchimp', ['type' => 'subscribe'])->assertUnauthorized();

        Queue::assertNotPushed(PollTrigger::class);
    });

    it('is closed while the switch is off', function () {
        mailchimpTrigger(routes: ['POST /lists/'.MAILCHIMP_LIST.'/webhooks' => ['id' => 'wh1']]);
        $watch = ProviderWatch::query()->sole();
        config(['triggers.push.mailchimp.enabled' => false]);

        mailchimpDelivery((string) $watch->secret)->assertNotFound();
    });

    it('keeps polling when the audience is not named, and drops the watch when Mailchimp refuses the URL', function () {
        expect(mailchimpTrigger(nodeConfig: ['list_id' => '{{ trigger.audience }}'])->subscription)->toBeNull();

        $refused = mailchimpTrigger(routes: ['POST /lists/'.MAILCHIMP_LIST.'/webhooks' => fn () => Http::response(['title' => 'Invalid Resource', 'status' => 400, 'detail' => 'This list has reached the limit of 10 webhooks.'], 400)]);

        expect($refused->subscription->status)->toBe(SubscriptionStatus::Failed)
            ->and($refused->subscription->last_error)->toContain('400')
            ->and(ProviderWatch::query()->count())->toBe(0);
    });

    it('makes the webhook again if it was deleted, and removes it with the watch', function () {
        mailchimpTrigger(routes: ['POST /lists/'.MAILCHIMP_LIST.'/webhooks' => ['id' => 'wh1']]);
        $watch = ProviderWatch::query()->sole();
        $source = app(MailchimpSource::class);
        $oldToken = $watch->secret;

        fakeMailchimp(['GET /lists/'.MAILCHIMP_LIST.'/webhooks/wh1' => fn () => Http::response(['title' => 'Resource Not Found', 'status' => 404], 404), 'POST /lists/'.MAILCHIMP_LIST.'/webhooks' => ['id' => 'wh2']]);
        app(WatchManager::class)->renew($source, $watch->fresh());

        expect($watch->fresh()->external_id)->toBe('wh2')->and($watch->fresh()->secret)->not->toBe($oldToken)->and($watch->fresh()->status)->toBe(SubscriptionStatus::Active);

        fakeMailchimp(['DELETE /lists/'.MAILCHIMP_LIST.'/webhooks/wh2' => null]);
        app(WatchManager::class)->remove($source, $watch->fresh());
        Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE');
        expect(ProviderWatch::query()->count())->toBe(0);
    });
});
