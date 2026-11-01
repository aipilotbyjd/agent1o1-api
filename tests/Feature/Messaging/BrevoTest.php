<?php

use App\Jobs\Triggers\FireTriggerEvent;
use App\Jobs\Triggers\PollTrigger;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Runs\Run;
use App\Models\Triggers\Trigger;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Nodes\Integrations\Brevo\AbstractBrevoNode;
use App\Nodes\Integrations\Brevo\BrevoAddContactsToListNode;
use App\Nodes\Integrations\Brevo\BrevoCreateOrUpdateContactNode;
use App\Nodes\Integrations\Brevo\BrevoDeleteContactNode;
use App\Nodes\Integrations\Brevo\BrevoListCampaignsNode;
use App\Nodes\Integrations\Brevo\BrevoListContactsNode;
use App\Nodes\Integrations\Brevo\BrevoSendEmailNode;
use App\Nodes\Integrations\Brevo\BrevoSendSmsNode;
use App\Nodes\Integrations\Brevo\BrevoTrackEventNode;
use App\Nodes\Integrations\Brevo\BrevoUpdateContactNode;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use App\Services\Workflows\NodeOptions\Sources\BrevoOptions;
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
function brevoConfig(array $config = []): array
{
    return ['access_token' => 'brevo_key', ...$config];
}

/**
 * Answer Brevo. A key is "METHOD /path" under /v3 (a trailing * takes any id); the
 * value is the JSON body (null is a 204), a closure taking the request, or a
 * ready response. A route nobody answers is a 404, so a node that calls
 * something unexpected fails the test.
 *
 * @param  array<string, mixed>  $routes
 */
function fakeBrevo(array $routes): void
{
    Http::swap(new Factory);
    Http::fake(['api.brevo.com/*' => function (Request $request) use ($routes) {
        $key = $request->method().' '.preg_replace('~^/v3~', '', (string) parse_url($request->url(), PHP_URL_PATH));
        $route = array_key_exists($key, $routes) ? $key : collect(array_keys($routes))->first(fn (string $pattern): bool => Str::is($pattern, $key));

        if ($route === null) {
            return Http::response(['code' => 'document_not_found', 'message' => "Nothing fakes {$key}"], 404);
        }

        $answer = value($routes[$route], $request);

        return $answer instanceof GuzzleHttp\Promise\PromiseInterface ? $answer : ($answer === null ? Http::response(null, 204) : Http::response($answer));
    }]);
}

/**
 * @return array<string, mixed>
 */
function brevoSent(string $route): array
{
    $found = array_values(array_filter(Http::recorded()->all(), fn (array $pair): bool => $pair[0]->method().' '.preg_replace('~^/v3~', '', (string) parse_url($pair[0]->url(), PHP_URL_PATH)) === $route));

    expect($found)->toHaveCount(1);

    return (array) json_decode($found[0][0]->body(), true);
}

/**
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function brevoContact(int $id = 1, array $extra = []): array
{
    return ['id' => $id, 'email' => "c{$id}@example.com", 'attributes' => ['FIRSTNAME' => 'Ada', 'LASTNAME' => 'Lovelace', 'SMS' => '+14155550123'], 'listIds' => [2, 3], 'emailBlacklisted' => false, 'smsBlacklisted' => false, 'createdAt' => now()->subMinute()->toIso8601String(), 'modifiedAt' => now()->toIso8601String(), ...$extra];
}

describe('brevo nodes', function () {
    it('registers the nodes under the brevo connector', function () {
        $registry = app(NodeRegistry::class);
        $types = array_filter(array_column($registry->placeableTypes(), 'type'), fn (string $type): bool => str_starts_with($type, 'brevo_'));

        expect(count($types))->toBeGreaterThanOrEqual(20);

        foreach ($types as $type) {
            expect($registry->resolve($type))->toBeInstanceOf(AbstractBrevoNode::class)->and($registry->resolve($type)->category())->toBe('brevo');
        }
    });

    it('adds or updates a contact with the key header, lists and names, then reads them back', function () {
        fakeBrevo(['POST /contacts' => ['id' => 7], 'GET /contacts/ada%40example.com' => brevoContact(7)]);

        $output = (new BrevoCreateOrUpdateContactNode)->execute(Run::factory()->create(), brevoConfig(['email' => ' Ada@Example.com ', 'first_name' => 'Ada', 'phone' => '+14155550123', 'attributes' => '{"COMPANY": "Acme"}', 'list_ids' => [2, '3'], 'email_blocked' => 'no']), []);

        expect($output)->toMatchArray(['id' => 7, 'email' => 'c7@example.com', 'first_name' => 'Ada', 'phone' => '+14155550123', 'list_ids' => [2, 3], 'created' => true])
            ->and(brevoSent('POST /contacts'))->toBe(['email' => 'ada@example.com', 'attributes' => ['COMPANY' => 'Acme', 'FIRSTNAME' => 'Ada', 'SMS' => '+14155550123'], 'listIds' => [2, 3], 'updateEnabled' => true, 'emailBlacklisted' => false]);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('api-key', 'brevo_key'));

        fakeBrevo(['POST /contacts' => null, 'GET /contacts/ada%40example.com' => brevoContact(7)]);
        expect((new BrevoCreateOrUpdateContactNode)->execute(Run::factory()->create(), brevoConfig(['email' => 'ada@example.com']), [])['created'])->toBeFalse();
    });

    it('updates by email or id and refuses an update that changes nothing', function () {
        fakeBrevo(['PUT /contacts/ada%40example.com' => null, 'GET /contacts/ada%40example.com' => brevoContact(7), 'PUT /contacts/7' => null, 'GET /contacts/7' => brevoContact(7)]);
        $node = new BrevoUpdateContactNode;

        $node->execute(Run::factory()->create(), brevoConfig(['contact' => 'ada@example.com', 'add_to_lists' => '4', 'remove_from_lists' => '2, 3', 'last_name' => 'Byron']), []);
        expect(brevoSent('PUT /contacts/ada%40example.com'))->toBe(['attributes' => ['LASTNAME' => 'Byron'], 'listIds' => [4], 'unlinkListIds' => [2, 3]]);

        $node->execute(Run::factory()->create(), brevoConfig(['contact' => '7', 'email_blocked' => 'yes']), []);
        expect(brevoSent('PUT /contacts/7'))->toBe(['emailBlacklisted' => true])
            ->and(fn () => $node->execute(Run::factory()->create(), brevoConfig(['contact' => '7']), []))->toThrow(InvalidArgumentException::class, 'Change at least one thing')
            ->and(fn () => $node->execute(Run::factory()->create(), brevoConfig(['contact' => 'not-an-email', 'last_name' => 'x']), []))->toThrow(InvalidArgumentException::class, 'is not an email address');
    });

    it('lists contacts newest first across pages and cuts them at the creation time asked for', function () {
        fakeBrevo(['GET /contacts' => ['contacts' => [brevoContact(3), brevoContact(2), brevoContact(1, ['createdAt' => '2020-01-01T00:00:00Z'])], 'count' => 3]]);

        $output = (new BrevoListContactsNode)->execute(Run::factory()->create(), brevoConfig(['created_after' => now()->subHour()->toIso8601String(), 'modified_after' => '2026-03-01 00:00:00']), []);

        expect(array_column($output['contacts'], 'id'))->toBe([3, 2]);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'sort=desc') && str_contains(urldecode($request->url()), 'modifiedSince=2026-03-01T00:00:00') && str_contains($request->url(), 'limit=50'));
    });

    it('reads a list\'s contacts from its own path', function () {
        fakeBrevo(['GET /contacts/lists/4/contacts' => ['contacts' => [brevoContact(1)], 'count' => 1]]);

        (new BrevoListContactsNode)->execute(Run::factory()->create(), brevoConfig(['list_id' => 4]), []);

        Http::assertSent(fn (Request $request): bool => str_contains((string) parse_url($request->url(), PHP_URL_PATH), '/contacts/lists/4/contacts'));
    });

    it('adds contacts to a list by email, checking each one', function () {
        fakeBrevo(['POST /contacts/lists/4/contacts/add' => ['contacts' => ['success' => ['a@x.co', 'b@x.co'], 'failure' => []]]]);
        $node = new BrevoAddContactsToListNode;

        $output = $node->execute(Run::factory()->create(), brevoConfig(['list_id' => 4, 'emails' => ['a@x.co', 'B@x.co', 'a@x.co']]), []);

        expect($output)->toBe(['added' => 2, 'failed' => [], 'list_id' => 4])
            ->and(brevoSent('POST /contacts/lists/4/contacts/add'))->toBe(['emails' => ['a@x.co', 'b@x.co']])
            ->and(fn () => $node->execute(Run::factory()->create(), brevoConfig(['list_id' => 4, 'emails' => 'a@x.co, nope']), []))->toThrow(InvalidArgumentException::class, 'is not an email address')
            ->and(fn () => $node->execute(Run::factory()->create(), brevoConfig(['list_id' => 4, 'emails' => '']), []))->toThrow(InvalidArgumentException::class, 'between 1 and 150');
    });

    it('sends a transactional email from a verified sender, a template or a written message', function () {
        fakeBrevo(['POST /smtp/email' => ['messageId' => '<abc@smtp-relay.mailin.fr>']]);
        $node = new BrevoSendEmailNode;
        $run = Run::factory()->create();
        $at = now()->addDay()->startOfSecond();

        $output = $node->execute($run, brevoConfig(['sender_email' => 'hello@acme.com', 'sender_name' => 'Acme', 'to' => 'ada@example.com, bob@example.com', 'subject' => "Hi\r\nBcc: x@y.z", 'html' => '<p>Hello</p>', 'tags' => 'welcome', 'send_at' => $at->toIso8601String(), 'attachments' => ['https://x.test/a.pdf']]), []);

        expect($output)->toMatchArray(['sent' => true, 'message_id' => '<abc@smtp-relay.mailin.fr>', 'to' => ['ada@example.com', 'bob@example.com'], 'scheduled' => true])
            ->and(brevoSent('POST /smtp/email'))->toBe([
                'sender' => ['email' => 'hello@acme.com', 'name' => 'Acme'],
                'to' => [['email' => 'ada@example.com'], ['email' => 'bob@example.com']],
                'subject' => 'Hi Bcc: x@y.z',
                'htmlContent' => '<p>Hello</p>',
                'tags' => ['welcome'],
                'scheduledAt' => $at->utc()->toIso8601String(),
                'attachment' => [['url' => 'https://x.test/a.pdf']],
            ]);

        fakeBrevo(['POST /smtp/email' => ['messageId' => 'm2']]);
        $node->execute($run, brevoConfig(['sender_email' => 'hello@acme.com', 'to' => 'ada@example.com', 'template_id' => 12, 'params' => '{"name": "Ada"}', 'html' => 'ignored']), []);
        expect(brevoSent('POST /smtp/email'))->toBe(['sender' => ['email' => 'hello@acme.com'], 'to' => [['email' => 'ada@example.com']], 'templateId' => 12, 'params' => ['name' => 'Ada']])
            ->and(fn () => $node->execute($run, brevoConfig(['sender_email' => 'hello@acme.com', 'to' => 'ada@example.com', 'subject' => 'x']), []))->toThrow(InvalidArgumentException::class, 'some content or a template')
            ->and(fn () => $node->execute($run, brevoConfig(['sender_email' => 'hello@acme.com', 'to' => '', 'subject' => 'x', 'html' => 'y']), []))->toThrow(InvalidArgumentException::class, 'At least one recipient')
            ->and(fn () => $node->execute($run, brevoConfig(['sender_email' => 'hello@acme.com', 'to' => 'a@b.co', 'subject' => 'x', 'html' => 'y', 'send_at' => '2020-01-01 10:00:00']), []))->toThrow(InvalidArgumentException::class, 'in the future');
    });

    it('sends a text with a checked sender and number', function () {
        fakeBrevo(['POST /transactionalSMS/sms' => ['messageId' => 55, 'usedCredits' => 1, 'remainingCredits' => 99]]);
        $node = new BrevoSendSmsNode;

        $output = $node->execute(Run::factory()->create(), brevoConfig(['sender' => 'Acme', 'recipient' => '+1 (415) 555-0199', 'content' => 'Your code is 1234']), []);

        expect($output)->toMatchArray(['sent' => true, 'message_id' => 55, 'credits_left' => 99, 'recipient' => '14155550199'])
            ->and(brevoSent('POST /transactionalSMS/sms'))->toBe(['sender' => 'Acme', 'recipient' => '14155550199', 'content' => 'Your code is 1234', 'type' => 'transactional'])
            ->and(fn () => $node->execute(Run::factory()->create(), brevoConfig(['sender' => 'A very long sender', 'recipient' => '+14155550199', 'content' => 'x']), []))->toThrow(InvalidArgumentException::class, 'up to 11 letters')
            ->and(fn () => $node->execute(Run::factory()->create(), brevoConfig(['sender' => 'Acme', 'recipient' => '555', 'content' => 'x']), []))->toThrow(InvalidArgumentException::class, 'international phone number');
    });

    it('tracks an event for a contact', function () {
        fakeBrevo(['POST /events' => null]);

        (new BrevoTrackEventNode)->execute(Run::factory()->create(), brevoConfig(['event_name' => 'order_placed', 'email' => 'ada@example.com', 'properties' => '{"total": 49}']), []);

        expect(brevoSent('POST /events'))->toBe(['event_name' => 'order_placed', 'identifiers' => ['email_id' => 'ada@example.com'], 'event_properties' => ['total' => 49]]);
    });

    it('lists sent campaigns with their statistics, and deletes a contact by email path', function () {
        fakeBrevo(['GET /emailCampaigns' => ['campaigns' => [['id' => 5, 'name' => 'March', 'subject' => 'News', 'status' => 'sent', 'sentDate' => '2026-03-01T09:00:00Z', 'sender' => ['email' => 'hello@acme.com'], 'statistics' => ['globalStats' => ['delivered' => 100, 'uniqueViews' => 40, 'uniqueClicks' => 10, 'unsubscriptions' => 1]]]], 'count' => 1], 'DELETE /contacts/ada%40example.com' => null]);

        $output = (new BrevoListCampaignsNode)->execute(Run::factory()->create(), brevoConfig(), []);
        $deleted = (new BrevoDeleteContactNode)->execute(Run::factory()->create(), brevoConfig(['contact' => 'Ada@Example.com']), []);

        expect($output['campaigns'][0])->toMatchArray(['id' => 5, 'status' => 'sent', 'sender' => 'hello@acme.com', 'delivered' => 100, 'opens' => 40, 'clicks' => 10, 'unsubscribed' => 1])
            ->and($deleted['deleted'])->toBeTrue();
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'status=sent') && str_contains($request->url(), 'statistics=globalStats'));
    });

    it('says what Brevo said when it refuses', function () {
        fakeBrevo(['POST /contacts' => fn () => Http::response(['code' => 'invalid_parameter', 'message' => 'Invalid email address'], 400)]);

        expect(fn () => (new BrevoCreateOrUpdateContactNode)->execute(Run::factory()->create(), brevoConfig(['email' => 'a@b.co']), []))->toThrow(RuntimeException::class, 'Brevo error: Invalid email address (invalid_parameter).');
    });
});

describe('brevo dropdowns', function () {
    it('lists contact lists with their size and verified senders by address', function () {
        fakeBrevo([
            'GET /contacts/lists' => ['lists' => [['id' => 4, 'name' => 'Newsletter', 'uniqueSubscribers' => 120]], 'count' => 120],
            'GET /senders' => ['senders' => [['id' => 1, 'name' => 'Acme', 'email' => 'hello@acme.com', 'active' => true], ['id' => 2, 'name' => 'Old', 'email' => 'old@acme.com', 'active' => false]]],
        ]);
        $query = new NodeOptionsQuery(app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']), User::factory()->create(), [], 'brevo_key');
        $source = app(BrevoOptions::class);

        expect($source->load('brevo.lists', $query)->toArray())->toBe(['options' => [['value' => '4', 'label' => 'Newsletter', 'description' => '120 contacts']], 'next_cursor' => '50'])
            ->and($source->load('brevo.senders', $query)->toArray()['options'])->toBe([['value' => 'hello@acme.com', 'label' => 'hello@acme.com', 'description' => 'Acme']]);
    });
});

describe('brevo triggers', function () {
    it('polls new contacts: a baseline first, then one run for each', function () {
        Queue::fake([FireTriggerEvent::class]);
        $owner = User::factory()->create();
        $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
        $workflow = Workflow::factory()->forWorkspace($workspace)->create();
        $credential = ConnectorCredential::factory()->forWorkspace($workspace)
            ->forConnector(Connector::query()->firstWhere('key', 'brevo') ?? Connector::factory()->create(['key' => 'brevo']))
            ->create(['data' => ['api_key' => 'brevo_key'], 'created_by' => $owner->id]);

        $workflow->replaceGraph([
            'nodes' => [
                ['key' => 'start', 'type' => 'brevo_list_contacts', 'config' => ['credential_id' => $credential->id, 'activate' => true]],
                ['key' => 'next', 'type' => 'transform', 'config' => ['mapping' => []]],
            ],
            'edges' => [['from' => 'start', 'to' => 'next']],
        ]);
        $workflow->fresh()->publishVersion(publisher: $owner);

        $trigger = Trigger::query()->where('target_id', $workflow->id)->where('trigger_node_key', 'start')->firstOrFail();
        $poll = fn (array $ids) => fakeBrevo(['GET /contacts' => ['contacts' => array_map(fn (int $id): array => brevoContact($id), $ids), 'count' => count($ids)]]);

        $poll([1]);
        dispatch_sync(new PollTrigger($trigger));
        expect($trigger->events()->where('status', 'queued')->count())->toBe(0);

        $poll([3, 2, 1]);
        dispatch_sync(new PollTrigger($trigger->fresh()));

        expect($trigger->events()->where('status', 'queued')->get()->pluck('payload.id')->sort()->values()->all())->toBe([2, 3]);
    });

    it('makes every edit of a contact its own event, and polls only', function () {
        $node = app(NodeRegistry::class)->resolve('brevo_list_updated_contacts');

        expect($node->triggerItemId(['id' => 1, 'modified_at' => 'a']))->not->toBe($node->triggerItemId(['id' => 1, 'modified_at' => 'b']))
            ->and(app(App\Services\Triggers\Push\PushSources::class)->source('brevo'))->toBeNull();
    });
});
