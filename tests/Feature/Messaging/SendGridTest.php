<?php

use App\Jobs\Triggers\FireTriggerEvent;
use App\Jobs\Triggers\PollTrigger;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Runs\Run;
use App\Models\Triggers\Trigger;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Nodes\Integrations\SendGrid\AbstractSendGridNode;
use App\Nodes\Integrations\SendGrid\SendGridAddGlobalUnsubscribeNode;
use App\Nodes\Integrations\SendGrid\SendGridDeleteContactNode;
use App\Nodes\Integrations\SendGrid\SendGridGetContactNode;
use App\Nodes\Integrations\SendGrid\SendGridGetStatsNode;
use App\Nodes\Integrations\SendGrid\SendGridListBouncesNode;
use App\Nodes\Integrations\SendGrid\SendGridRemoveSuppressionNode;
use App\Nodes\Integrations\SendGrid\SendGridSearchContactsNode;
use App\Nodes\Integrations\SendGrid\SendGridSendEmailNode;
use App\Nodes\Integrations\SendGrid\SendGridUpsertContactNode;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use App\Services\Workflows\NodeOptions\Sources\SendGridOptions;
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
function sendgridConfig(array $config = []): array
{
    return ['access_token' => 'SG.key', ...$config];
}

/**
 * Answer SendGrid. A key is "METHOD /path" under /v3 (a trailing * takes any id);
 * the value is the JSON body (null is a 204), or a closure returning a response.
 * A route nobody answers is a 404, so a node that calls something unexpected
 * fails the test.
 *
 * @param  array<string, mixed>  $routes
 */
function fakeSendGrid(array $routes): void
{
    Http::swap(new Factory);
    Http::fake(['api.sendgrid.com/*' => function (Request $request) use ($routes) {
        $key = $request->method().' '.preg_replace('~^/v3~', '', (string) parse_url($request->url(), PHP_URL_PATH));
        $route = array_key_exists($key, $routes) ? $key : collect(array_keys($routes))->first(fn (string $pattern): bool => Str::is($pattern, $key));

        if ($route === null) {
            return Http::response(['errors' => [['message' => "Nothing fakes {$key}"]]], 404);
        }

        $answer = value($routes[$route], $request);

        return $answer instanceof GuzzleHttp\Promise\PromiseInterface ? $answer : ($answer === null ? Http::response(null, 204) : Http::response($answer));
    }]);
}

/**
 * @return array<string, mixed>
 */
function sendgridSent(string $route): array
{
    $found = array_values(array_filter(Http::recorded()->all(), fn (array $pair): bool => $pair[0]->method().' '.preg_replace('~^/v3~', '', (string) parse_url($pair[0]->url(), PHP_URL_PATH)) === $route));

    expect($found)->toHaveCount(1);

    return (array) json_decode($found[0][0]->body(), true);
}

/**
 * @return array<string, mixed>
 */
function sendgridContact(string $id = 'c1'): array
{
    return ['id' => $id, 'email' => 'ada@example.com', 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'phone_number' => '+14155550123', 'list_ids' => ['l1'], 'custom_fields' => ['e1_T' => 'Acme'], 'created_at' => '2026-03-01T10:00:00Z', 'updated_at' => '2026-03-02T10:00:00Z'];
}

describe('sendgrid nodes', function () {
    it('registers the nodes under the sendgrid connector', function () {
        $registry = app(NodeRegistry::class);
        $types = array_filter(array_column($registry->placeableTypes(), 'type'), fn (string $type): bool => str_starts_with($type, 'sendgrid_'));

        expect(count($types))->toBeGreaterThanOrEqual(20);

        foreach ($types as $type) {
            expect($registry->resolve($type))->toBeInstanceOf(AbstractSendGridNode::class)->and($registry->resolve($type)->category())->toBe('sendgrid');
        }
    });

    it('sends a written email with a bearer key and returns SendGrid\'s message id', function () {
        fakeSendGrid(['POST /mail/send' => fn () => Http::response('', 202, ['X-Message-Id' => 'msg-1'])]);

        $output = (new SendGridSendEmailNode)->execute(Run::factory()->create(), sendgridConfig(['sender_email' => 'hello@acme.com', 'sender_name' => 'Acme', 'to' => 'ada@example.com, bob@example.com', 'cc' => 'carol@example.com', 'subject' => "Hi\r\nBcc: x@y.z", 'html' => '<p>Hello</p>', 'text' => 'Hello', 'categories' => 'welcome, news']), []);

        expect($output)->toBe(['sent' => true, 'message_id' => 'msg-1', 'to' => ['ada@example.com', 'bob@example.com'], 'scheduled' => false])
            ->and(sendgridSent('POST /mail/send'))->toEqual([
                'personalizations' => [['to' => [['email' => 'ada@example.com'], ['email' => 'bob@example.com']], 'cc' => [['email' => 'carol@example.com']]]],
                'from' => ['email' => 'hello@acme.com', 'name' => 'Acme'],
                'subject' => 'Hi Bcc: x@y.z',
                'content' => [['type' => 'text/plain', 'value' => 'Hello'], ['type' => 'text/html', 'value' => '<p>Hello</p>']],
                'categories' => ['welcome', 'news'],
            ]);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer SG.key'));
    });

    it('sends a dynamic template, one copy each, and checks what it is asked to send', function () {
        fakeSendGrid(['POST /mail/send' => fn () => Http::response('', 202)]);
        $node = new SendGridSendEmailNode;
        $run = Run::factory()->create();

        $output = $node->execute($run, sendgridConfig(['sender_email' => 'hello@acme.com', 'to' => 'ada@example.com, bob@example.com', 'template_id' => 'd-abc123', 'template_data' => '{"name": "Ada"}', 'one_each' => true]), []);

        expect($output['message_id'])->toBeNull()
            ->and(sendgridSent('POST /mail/send'))->toEqual([
                'personalizations' => [
                    ['to' => [['email' => 'ada@example.com']], 'dynamic_template_data' => ['name' => 'Ada']],
                    ['to' => [['email' => 'bob@example.com']], 'dynamic_template_data' => ['name' => 'Ada']],
                ],
                'from' => ['email' => 'hello@acme.com'],
                'template_id' => 'd-abc123',
            ])
            ->and(fn () => $node->execute($run, sendgridConfig(['sender_email' => 'hello@acme.com', 'to' => 'ada@example.com', 'subject' => 'x']), []))->toThrow(InvalidArgumentException::class, 'a subject and some content or a template')
            ->and(fn () => $node->execute($run, sendgridConfig(['sender_email' => 'hello@acme.com', 'to' => '', 'subject' => 'x', 'html' => 'y']), []))->toThrow(InvalidArgumentException::class, 'At least one recipient')
            ->and(fn () => $node->execute($run, sendgridConfig(['sender_email' => 'hello@acme.com', 'to' => 'nope', 'subject' => 'x', 'html' => 'y']), []))->toThrow(InvalidArgumentException::class, 'is not an email address')
            ->and(fn () => $node->execute($run, sendgridConfig(['sender_email' => 'hello@acme.com', 'to' => 'a@b.co', 'subject' => 'x', 'html' => 'y', 'send_at' => now()->addDays(5)->toIso8601String()]), []))->toThrow(InvalidArgumentException::class, 'within 72 hours');
    });

    it('schedules an email as a unix time', function () {
        fakeSendGrid(['POST /mail/send' => fn () => Http::response('', 202)]);
        $at = now()->addHours(2)->startOfSecond();

        $output = (new SendGridSendEmailNode)->execute(Run::factory()->create(), sendgridConfig(['sender_email' => 'hello@acme.com', 'to' => 'ada@example.com', 'subject' => 'Later', 'text' => 'Hi', 'send_at' => $at->toIso8601String()]), []);

        expect($output['scheduled'])->toBeTrue()->and(sendgridSent('POST /mail/send')['send_at'])->toBe($at->timestamp);
    });

    it('says what SendGrid said when it refuses', function () {
        fakeSendGrid(['POST /mail/send' => fn () => Http::response(['errors' => [['message' => 'The from address does not match a verified Sender Identity.', 'field' => 'from']]], 403)]);

        expect(fn () => (new SendGridSendEmailNode)->execute(Run::factory()->create(), sendgridConfig(['sender_email' => 'hello@acme.com', 'to' => 'a@b.co', 'subject' => 'x', 'text' => 'y']), []))
            ->toThrow(RuntimeException::class, 'SendGrid error: The from address does not match a verified Sender Identity. (from)');
    });

    it('queues a contact add with lists and custom fields', function () {
        fakeSendGrid(['PUT /marketing/contacts' => ['job_id' => 'job-1']]);

        $output = (new SendGridUpsertContactNode)->execute(Run::factory()->create(), sendgridConfig(['email' => ' Ada@Example.com ', 'first_name' => 'Ada', 'custom_fields' => '{"e1_T": "Acme"}', 'list_ids' => ['l1', 'l2']]), []);

        expect($output)->toBe(['queued' => true, 'job_id' => 'job-1', 'email' => 'ada@example.com'])
            ->and(sendgridSent('PUT /marketing/contacts'))->toEqual(['list_ids' => ['l1', 'l2'], 'contacts' => [['email' => 'ada@example.com', 'first_name' => 'Ada', 'custom_fields' => ['e1_T' => 'Acme']]]]);
    });

    it('finds a contact by email, and says so when there is none', function () {
        fakeSendGrid(['POST /marketing/contacts/search/emails' => ['result' => ['ada@example.com' => ['contact' => sendgridContact()]]]]);
        $node = new SendGridGetContactNode;

        expect($node->execute(Run::factory()->create(), sendgridConfig(['email' => 'Ada@example.com']), []))->toMatchArray(['found' => true, 'id' => 'c1', 'first_name' => 'Ada', 'phone' => '+14155550123', 'list_ids' => ['l1']]);

        fakeSendGrid(['POST /marketing/contacts/search/emails' => fn () => Http::response(['errors' => [['message' => 'no contacts found']]], 404)]);

        expect($node->execute(Run::factory()->create(), sendgridConfig(['email' => 'ada@example.com']), []))->toBe(['found' => false, 'email' => 'ada@example.com']);
    });

    it('deletes a contact through the id it finds, and does nothing for a stranger', function () {
        fakeSendGrid(['POST /marketing/contacts/search/emails' => ['result' => ['ada@example.com' => ['contact' => sendgridContact('c9')]]], 'DELETE /marketing/contacts' => ['job_id' => 'job-2']]);

        $output = (new SendGridDeleteContactNode)->execute(Run::factory()->create(), sendgridConfig(['email' => 'ada@example.com']), []);

        expect($output)->toBe(['deleted' => true, 'found' => true, 'email' => 'ada@example.com', 'job_id' => 'job-2']);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE' && str_contains($request->url(), 'ids=c9'));

        fakeSendGrid(['POST /marketing/contacts/search/emails' => fn () => Http::response(['errors' => [['message' => 'none']]], 404)]);

        expect((new SendGridDeleteContactNode)->execute(Run::factory()->create(), sendgridConfig(['email' => 'ada@example.com']), []))->toBe(['deleted' => false, 'found' => false, 'email' => 'ada@example.com']);
    });

    it('searches contacts by list and creation time, and needs something to search by', function () {
        fakeSendGrid(['POST /marketing/contacts/search' => ['result' => [sendgridContact('c1'), sendgridContact('c2')], 'contact_count' => 2]]);
        $node = new SendGridSearchContactsNode;

        $output = $node->execute(Run::factory()->create(), sendgridConfig(['query' => "email LIKE 'ada%'", 'list_id' => 'abc-123', 'created_after' => '2026-03-01T10:00:00Z']), []);

        expect(array_column($output['contacts'], 'id'))->toBe(['c1', 'c2'])
            ->and($output['total'])->toBe(2)
            ->and(sendgridSent('POST /marketing/contacts/search')['query'])->toBe("(email LIKE 'ada%') AND CONTAINS(list_ids, 'abc-123') AND created_at > TIMESTAMP '2026-03-01T10:00:00Z'")
            ->and(fn () => $node->execute(Run::factory()->create(), sendgridConfig(), []))->toThrow(InvalidArgumentException::class, 'Give a query')
            ->and(fn () => $node->execute(Run::factory()->create(), sendgridConfig(['list_id' => "x') OR (1=1"]), []))->toThrow(InvalidArgumentException::class, 'not a valid id');
    });

    it('adds up the stats for the days asked for', function () {
        fakeSendGrid(['GET /stats' => [
            ['date' => '2026-03-01', 'stats' => [['metrics' => ['requests' => 10, 'delivered' => 9, 'opens' => 5]]]],
            ['date' => '2026-03-02', 'stats' => [['metrics' => ['requests' => 20, 'delivered' => 18, 'opens' => 7]]]],
        ]]);

        $output = (new SendGridGetStatsNode)->execute(Run::factory()->create(), sendgridConfig(['start_date' => '2026-03-01', 'end_date' => '2026-03-02']), []);

        expect((array) $output['totals'])->toBe(['requests' => 30, 'delivered' => 27, 'opens' => 12])
            ->and($output['count'])->toBe(2)
            ->and(fn () => (new SendGridGetStatsNode)->execute(Run::factory()->create(), sendgridConfig(['start_date' => '2026-03-05', 'end_date' => '2026-03-01']), []))->toThrow(InvalidArgumentException::class, 'before');
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'start_date=2026-03-01') && str_contains($request->url(), 'aggregated_by=day'));
    });

    it('manages suppressions: lifts one by list, and adds global unsubscribes', function () {
        fakeSendGrid(['DELETE /suppression/bounces/ada%40example.com' => null, 'DELETE /asm/suppressions/global/ada%40example.com' => null, 'POST /asm/suppressions/global' => ['recipient_emails' => ['a@x.co']]]);
        $remove = new SendGridRemoveSuppressionNode;

        expect($remove->execute(Run::factory()->create(), sendgridConfig(['email' => 'Ada@Example.com', 'group' => 'bounces']), []))->toBe(['removed' => true, 'email' => 'ada@example.com', 'group' => 'bounces'])
            ->and($remove->execute(Run::factory()->create(), sendgridConfig(['email' => 'ada@example.com', 'group' => 'unsubscribes']), [])['removed'])->toBeTrue()
            ->and(fn () => $remove->execute(Run::factory()->create(), sendgridConfig(['email' => 'ada@example.com', 'group' => '../x']), []))->toThrow(InvalidArgumentException::class, 'not a suppression list')
            ->and((new SendGridAddGlobalUnsubscribeNode)->execute(Run::factory()->create(), sendgridConfig(['emails' => 'a@x.co, A@x.co']), []))->toBe(['unsubscribed' => 1, 'emails' => ['a@x.co']])
            ->and(sendgridSent('POST /asm/suppressions/global'))->toBe(['recipient_emails' => ['a@x.co']]);
    });

    it('lists bounces newest first, from a time, with the time as an ISO date', function () {
        fakeSendGrid(['GET /suppression/bounces' => [
            ['created' => 1772000000, 'email' => 'old@x.co', 'reason' => '550 no such user', 'status' => '5.1.1'],
            ['created' => 1772100000, 'email' => 'new@x.co', 'reason' => '550 mailbox full', 'status' => '5.2.2'],
        ]]);

        $output = (new SendGridListBouncesNode)->execute(Run::factory()->create(), sendgridConfig(['since' => '2026-02-01T00:00:00Z']), []);

        expect(array_column($output['bounces'], 'email'))->toBe(['new@x.co', 'old@x.co'])
            ->and($output['bounces'][0]['created_at'])->toBe('2026-02-26T10:00:00+00:00');
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'start_time='.Illuminate\Support\Carbon::parse('2026-02-01T00:00:00Z')->timestamp) && str_contains($request->url(), 'limit=50'));
    });
});

describe('sendgrid dropdowns', function () {
    it('lists marketing lists with their size, and verified senders by address', function () {
        fakeSendGrid([
            'GET /marketing/lists' => ['result' => [['id' => 'l1', 'name' => 'Newsletter', 'contact_count' => 120]]],
            'GET /verified_senders' => ['results' => [['id' => 1, 'from_name' => 'Acme', 'from_email' => 'hello@acme.com', 'verified' => true], ['id' => 2, 'from_name' => 'Old', 'from_email' => 'old@acme.com', 'verified' => false]]],
        ]);
        $query = new NodeOptionsQuery(app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']), User::factory()->create(), [], 'SG.key');
        $source = app(SendGridOptions::class);

        expect($source->load('sendgrid.lists', $query)->toArray()['options'])->toBe([['value' => 'l1', 'label' => 'Newsletter', 'description' => '120 contacts']])
            ->and($source->load('sendgrid.senders', $query)->toArray()['options'])->toBe([['value' => 'hello@acme.com', 'label' => 'hello@acme.com', 'description' => 'Acme']]);
    });
});

describe('sendgrid triggers', function () {
    it('polls bounces: a baseline first, then one run for each new address, and polls only', function () {
        Queue::fake([FireTriggerEvent::class]);
        $owner = User::factory()->create();
        $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
        $workflow = Workflow::factory()->forWorkspace($workspace)->create();
        $credential = ConnectorCredential::factory()->forWorkspace($workspace)
            ->forConnector(Connector::query()->firstWhere('key', 'sendgrid') ?? Connector::factory()->create(['key' => 'sendgrid']))
            ->create(['data' => ['api_key' => 'SG.key'], 'created_by' => $owner->id]);

        $workflow->replaceGraph([
            'nodes' => [
                ['key' => 'start', 'type' => 'sendgrid_list_bounces', 'config' => ['credential_id' => $credential->id, 'activate' => true]],
                ['key' => 'next', 'type' => 'transform', 'config' => ['mapping' => []]],
            ],
            'edges' => [['from' => 'start', 'to' => 'next']],
        ]);
        $workflow->fresh()->publishVersion(publisher: $owner);

        $trigger = Trigger::query()->where('target_id', $workflow->id)->where('trigger_node_key', 'start')->firstOrFail();
        $bounce = fn (string $email, int $at): array => ['created' => $at, 'email' => $email, 'reason' => '550', 'status' => '5.1.1'];
        $poll = fn (array $rows) => fakeSendGrid(['GET /suppression/bounces' => $rows]);
        $now = now()->timestamp;

        $poll([$bounce('a@x.co', $now - 100)]);
        dispatch_sync(new PollTrigger($trigger));
        expect($trigger->events()->where('status', 'queued')->count())->toBe(0);

        $poll([$bounce('c@x.co', $now - 10), $bounce('b@x.co', $now - 50), $bounce('a@x.co', $now - 100)]);
        dispatch_sync(new PollTrigger($trigger->fresh()));

        expect($trigger->events()->where('status', 'queued')->get()->pluck('payload.email')->sort()->values()->all())->toBe(['b@x.co', 'c@x.co'])
            ->and(app(App\Services\Triggers\Push\PushSources::class)->source('sendgrid'))->toBeNull();
    });
});
