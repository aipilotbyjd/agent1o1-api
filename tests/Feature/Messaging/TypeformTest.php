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
use App\Nodes\Integrations\Typeform\AbstractTypeformNode;
use App\Nodes\Integrations\Typeform\TypeformCreateFormNode;
use App\Nodes\Integrations\Typeform\TypeformCreateWebhookNode;
use App\Nodes\Integrations\Typeform\TypeformDeleteResponsesNode;
use App\Nodes\Integrations\Typeform\TypeformGetFormNode;
use App\Nodes\Integrations\Typeform\TypeformGetResponseNode;
use App\Nodes\Integrations\Typeform\TypeformListFormsNode;
use App\Nodes\Integrations\Typeform\TypeformListResponsesNode;
use App\Nodes\Integrations\Typeform\TypeformUpdateFormNode;
use App\Services\Triggers\Push\TypeformSource;
use App\Services\Triggers\Push\WatchManager;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use App\Services\Workflows\NodeOptions\Sources\TypeformOptions;
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
function typeformConfig(array $config = []): array
{
    return ['access_token' => 'tf_test', ...$config];
}

/**
 * Answer Typeform. A key is "METHOD /path"; the value is the JSON body, a closure
 * taking the request, or a ready response. A route nobody answers is a 404, so a
 * node that calls something unexpected fails the test.
 *
 * @param  array<string, mixed>  $routes
 */
function fakeTypeform(array $routes): void
{
    Http::swap(new Factory);
    Http::fake(['*typeform.com/*' => function (Request $request) use ($routes) {
        $key = $request->method().' '.parse_url($request->url(), PHP_URL_PATH);

        // A key may end in * to take any tag or id.
        $route = array_key_exists($key, $routes) ? $key : collect(array_keys($routes))->first(fn (string $pattern): bool => Str::is($pattern, $key));

        if ($route === null) {
            return Http::response(['code' => 'NOT_FOUND', 'description' => "Nothing fakes {$key}"], 404);
        }

        $answer = value($routes[$route], $request);

        return $answer instanceof GuzzleHttp\Promise\PromiseInterface ? $answer : ($answer === null ? Http::response(null, 204) : Http::response($answer));
    }]);
}

/**
 * The JSON body of the one request to a route.
 *
 * @return array<int|string, mixed>
 */
function typeformSent(string $route): array
{
    $found = array_values(array_filter(Http::recorded()->all(), fn (array $pair): bool => $pair[0]->method().' '.parse_url($pair[0]->url(), PHP_URL_PATH) === $route));

    expect($found)->toHaveCount(1);

    return (array) json_decode($found[0][0]->body(), true);
}

/**
 * A form as Typeform returns it: a short text, a choice, a group with a yes/no inside.
 *
 * @return array<string, mixed>
 */
function typeformForm(): array
{
    return [
        'id' => 'AbC123', 'title' => 'Customer survey', 'type' => 'form', 'created_at' => '2026-01-01T00:00:00Z', 'last_updated_at' => '2026-02-01T00:00:00Z',
        'workspace' => ['href' => 'https://api.typeform.com/workspaces/ws1'], 'settings' => ['language' => 'en', 'is_public' => true], '_links' => ['display' => 'https://acme.typeform.com/to/AbC123'],
        'fields' => [
            ['id' => 'f1', 'ref' => 'name', 'type' => 'short_text', 'title' => 'Your *name*?'],
            ['id' => 'f2', 'ref' => 'email', 'type' => 'email', 'title' => 'Email'],
            ['id' => 'f3', 'ref' => 'plan', 'type' => 'multiple_choice', 'title' => 'Plan'],
            ['id' => 'g1', 'ref' => 'group', 'type' => 'group', 'title' => 'More', 'properties' => ['fields' => [['id' => 'f4', 'ref' => 'news', 'type' => 'yes_no', 'title' => 'Newsletter?']]]],
        ],
    ];
}

/**
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function typeformResponse(string $token = 'resp1', array $extra = []): array
{
    return [
        'landing_id' => $token, 'token' => $token, 'response_id' => $token, 'landed_at' => '2026-03-01T10:00:00Z', 'submitted_at' => '2026-03-01T10:01:00Z',
        'metadata' => ['browser' => 'default', 'platform' => 'other', 'referer' => 'https://acme.test', 'user_agent' => 'UA', 'network_id' => 'n1'],
        'hidden' => ['utm_source' => 'ads'], 'calculated' => ['score' => 3],
        'answers' => [
            ['field' => ['id' => 'f1', 'type' => 'short_text', 'ref' => 'name'], 'type' => 'text', 'text' => 'Ada'],
            ['field' => ['id' => 'f2', 'type' => 'email', 'ref' => 'email'], 'type' => 'email', 'email' => 'ada@example.com'],
            ['field' => ['id' => 'f3', 'type' => 'multiple_choice', 'ref' => 'plan'], 'type' => 'choice', 'choice' => ['label' => 'Pro']],
            ['field' => ['id' => 'f4', 'type' => 'yes_no', 'ref' => 'news'], 'type' => 'boolean', 'boolean' => true],
        ],
        ...$extra,
    ];
}

describe('typeform nodes', function () {
    it('registers the nodes under the typeform connector', function () {
        $registry = app(NodeRegistry::class);
        $types = array_filter(array_column($registry->placeableTypes(), 'type'), fn (string $type): bool => str_starts_with($type, 'typeform_'));

        expect(count($types))->toBeGreaterThanOrEqual(17);

        foreach ($types as $type) {
            expect($registry->resolve($type))->toBeInstanceOf(AbstractTypeformNode::class)->and($registry->resolve($type)->category())->toBe('typeform');
        }
    });

    it('lists forms page by page', function () {
        $calls = 0;
        fakeTypeform(['GET /forms' => function (Request $request) use (&$calls) {
            $calls++;

            return str_contains($request->url(), 'page=2')
                ? ['total_items' => 3, 'page_count' => 2, 'items' => [['id' => 'f3', 'title' => 'Third']]]
                : ['total_items' => 3, 'page_count' => 2, 'items' => [['id' => 'f1', 'title' => 'First', '_links' => ['display' => 'https://x.typeform.com/to/f1']], ['id' => 'f2', 'title' => 'Second']]];
        }]);

        $output = (new TypeformListFormsNode)->execute(Run::factory()->create(), typeformConfig(['search' => 'survey', 'workspace_id' => 'ws1', 'limit' => 50]), []);

        expect($calls)->toBe(2)
            ->and(array_column($output['forms'], 'id'))->toBe(['f1', 'f2', 'f3'])
            ->and($output['forms'][0]['url'])->toBe('https://x.typeform.com/to/f1');
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer tf_test') && str_contains($request->url(), 'search=survey') && str_contains($request->url(), 'workspace_id=ws1'));
    });

    it('gets a form with its questions, groups included, and accepts a pasted link', function () {
        fakeTypeform(['GET /forms/AbC123' => typeformForm()]);

        $output = (new TypeformGetFormNode)->execute(Run::factory()->create(), typeformConfig(['form_id' => 'https://acme.typeform.com/to/AbC123?typeform-source=x']), []);

        expect($output)->toMatchArray(['id' => 'AbC123', 'title' => 'Customer survey', 'workspace_id' => 'ws1', 'url' => 'https://acme.typeform.com/to/AbC123', 'question_count' => 5])
            ->and(array_column($output['questions'], 'title'))->toBe(['Your name?', 'Email', 'Plan', 'More', 'Newsletter?'])
            ->and(fn () => (new TypeformGetFormNode)->execute(Run::factory()->create(), typeformConfig(['form_id' => 'not a form!']), []))->toThrow(InvalidArgumentException::class, 'not a Typeform form id');
    });

    it('lists responses with each answer next to its question, by title and by ref', function () {
        fakeTypeform(['GET /forms/AbC123/responses' => ['items' => [typeformResponse('resp1')]], 'GET /forms/AbC123' => typeformForm()]);

        $output = (new TypeformListResponsesNode)->execute(Run::factory()->create(), typeformConfig(['form_id' => 'AbC123', 'since' => '2026-03-01 00:00:00', 'query' => 'Ada']), []);
        $response = $output['responses'][0];

        expect($output['count'])->toBe(1)
            ->and($response)->toMatchArray(['token' => 'resp1', 'completed' => true, 'email' => 'ada@example.com', 'score' => 3, 'referer' => 'https://acme.test', 'form_id' => 'AbC123'])
            ->and($response['answers_map'])->toBe(['Your name?' => 'Ada', 'Email' => 'ada@example.com', 'Plan' => 'Pro', 'Newsletter?' => true])
            ->and($response['answers_by_ref'])->toBe(['name' => 'Ada', 'email' => 'ada@example.com', 'plan' => 'Pro', 'news' => true])
            ->and((array) $response['hidden'])->toBe(['utm_source' => 'ads']);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'completed=true') && str_contains(urldecode($request->url()), 'since=2026-03-01T00:00:00') && str_contains($request->url(), 'query=Ada') && str_contains($request->url(), 'page_size=25'));
    });

    it('reads multiple choices, other answers and payments as plain values', function () {
        fakeTypeform(['GET /forms/AbC123/responses' => ['items' => [typeformResponse('r', ['answers' => [
            ['field' => ['id' => 'f3', 'ref' => 'plan'], 'type' => 'choices', 'choices' => ['labels' => ['A', 'B'], 'other' => 'Custom']],
            ['field' => ['id' => 'f1', 'ref' => 'name'], 'type' => 'choice', 'choice' => ['other' => 'Elsewhere']],
            ['field' => ['id' => 'zz', 'ref' => 'pay'], 'type' => 'payment', 'payment' => ['amount' => '10.00', 'success' => true, 'name' => 'Ada']],
            ['field' => ['id' => 'f4', 'ref' => 'news'], 'type' => 'number', 'number' => 42],
        ]])]], 'GET /forms/AbC123' => typeformForm()]);

        $map = (new TypeformListResponsesNode)->execute(Run::factory()->create(), typeformConfig(['form_id' => 'AbC123']), [])['responses'][0]['answers_by_ref'];

        expect($map)->toBe(['plan' => ['A', 'B', 'Custom'], 'name' => 'Elsewhere', 'pay' => ['amount' => '10.00', 'success' => true, 'name' => 'Ada'], 'news' => 42]);
    });

    it('gets one response by its token, and says when there is none', function () {
        fakeTypeform(['GET /forms/AbC123/responses' => fn (Request $request) => str_contains($request->url(), 'included_response_ids=resp1') ? ['items' => [typeformResponse('resp1')]] : ['items' => []], 'GET /forms/AbC123' => typeformForm()]);
        $node = new TypeformGetResponseNode;

        expect($node->execute(Run::factory()->create(), typeformConfig(['form_id' => 'AbC123', 'response_id' => 'resp1']), [])['email'])->toBe('ada@example.com')
            ->and(fn () => $node->execute(Run::factory()->create(), typeformConfig(['form_id' => 'AbC123', 'response_id' => 'nope']), []))->toThrow(RuntimeException::class, 'no response "nope"');
    });

    it('creates a form with questions, hidden fields and a workspace link', function () {
        fakeTypeform(['POST /forms' => typeformForm()]);
        $node = new TypeformCreateFormNode;

        $output = $node->execute(Run::factory()->create(), typeformConfig([
            'title' => 'Survey', 'fields' => '[{"type": "short_text", "title": "Name?"}]', 'workspace_id' => 'ws1', 'hidden' => 'utm_source, utm_medium', 'settings' => ['is_public' => false],
        ]), []);

        expect($output['id'])->toBe('AbC123')
            ->and(typeformSent('POST /forms'))->toBe([
                'title' => 'Survey', 'fields' => [['type' => 'short_text', 'title' => 'Name?']], 'hidden' => ['utm_source', 'utm_medium'], 'settings' => ['is_public' => false],
                'workspace' => ['href' => 'https://api.typeform.com/workspaces/ws1'],
            ])
            ->and(fn () => $node->execute(Run::factory()->create(), typeformConfig(['title' => 'S', 'fields' => [['type' => 'short_text']]]), []))->toThrow(InvalidArgumentException::class, 'type and a title');
    });

    it('updates only what is filled in, as JSON Patch', function () {
        fakeTypeform(['PATCH /forms/AbC123' => null, 'GET /forms/AbC123' => typeformForm()]);
        $node = new TypeformUpdateFormNode;

        $node->execute(Run::factory()->create(), typeformConfig(['form_id' => 'AbC123', 'title' => 'New', 'is_public' => 'no', 'workspace_id' => 'ws2', 'patch' => [['op' => 'replace', 'path' => '/settings/show_progress_bar', 'value' => true]]]), []);

        expect(typeformSent('PATCH /forms/AbC123'))->toBe([
            ['op' => 'replace', 'path' => '/title', 'value' => 'New'],
            ['op' => 'replace', 'path' => '/settings/is_public', 'value' => false],
            ['op' => 'replace', 'path' => '/workspace', 'value' => ['href' => 'https://api.typeform.com/workspaces/ws2']],
            ['op' => 'replace', 'path' => '/settings/show_progress_bar', 'value' => true],
        ])
            ->and(fn () => $node->execute(Run::factory()->create(), typeformConfig(['form_id' => 'AbC123']), []))->toThrow(InvalidArgumentException::class, 'Change at least one thing')
            ->and(fn () => $node->execute(Run::factory()->create(), typeformConfig(['form_id' => 'AbC123', 'patch' => [['op' => 'move', 'path' => '/x']]]), []))->toThrow(InvalidArgumentException::class, 'op (add, remove or replace)');
    });

    it('deletes responses by token and checks webhook details', function () {
        fakeTypeform(['DELETE /forms/AbC123/responses' => null, 'PUT /forms/AbC123/webhooks/crm-sync' => ['tag' => 'crm-sync', 'url' => 'https://x.test/h', 'enabled' => true]]);
        $run = Run::factory()->create();

        $deleted = (new TypeformDeleteResponsesNode)->execute($run, typeformConfig(['form_id' => 'AbC123', 'response_ids' => 'a, b, a']), []);
        expect($deleted['count'])->toBe(2);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE' && str_contains(urldecode($request->url()), 'included_response_ids=a,b'));

        (new TypeformCreateWebhookNode)->execute($run, typeformConfig(['form_id' => 'AbC123', 'tag' => 'crm-sync', 'url' => 'https://x.test/h', 'secret' => 's3']), []);
        expect(typeformSent('PUT /forms/AbC123/webhooks/crm-sync'))->toBe(['url' => 'https://x.test/h', 'enabled' => true, 'secret' => 's3', 'verify_ssl' => true, 'event_types' => ['form_response' => true]])
            ->and(fn () => (new TypeformCreateWebhookNode)->execute($run, typeformConfig(['form_id' => 'AbC123', 'tag' => 'a b', 'url' => 'https://x.test']), []))->toThrow(InvalidArgumentException::class, 'letters, digits')
            ->and(fn () => (new TypeformCreateWebhookNode)->execute($run, typeformConfig(['form_id' => 'AbC123', 'tag' => 'ok', 'url' => 'http://x.test']), []))->toThrow(InvalidArgumentException::class, 'https');
    });

    it('turns Typeform\'s refusal into an error that says what to do', function () {
        fakeTypeform(['GET /forms/AbC123' => fn () => Http::response(['code' => 'AUTHENTICATION_FAILED', 'description' => 'Authentication credentials not valid'], 401)]);

        expect(fn () => (new TypeformGetFormNode)->execute(Run::factory()->create(), typeformConfig(['form_id' => 'AbC123']), []))
            ->toThrow(RuntimeException::class, 'Authentication credentials not valid. Reconnect Typeform');
    });

    it('uses the EU host for an account on the EU data center', function () {
        $run = Run::factory()->create();
        ConnectorCredential::factory()->create([
            'workspace_id' => $run->workspace_id,
            'connector_id' => (Connector::query()->firstWhere('key', 'typeform') ?? Connector::factory()->create(['key' => 'typeform']))->id,
            'data' => ['api_key' => 'tf_eu', 'region' => 'eu'],
            'is_default' => true,
        ]);
        Http::swap(new Factory);
        Http::fake(['api.eu.typeform.com/*' => Http::response(['items' => [], 'page_count' => 1, 'total_items' => 0])]);

        (new TypeformListFormsNode)->execute($run, [], []);

        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://api.eu.typeform.com/forms') && $request->hasHeader('Authorization', 'Bearer tf_eu'));
    });
});

describe('typeform dropdowns', function () {
    it('lists forms and workspaces, a page at a time', function () {
        fakeTypeform([
            'GET /forms' => ['page_count' => 2, 'items' => [['id' => 'AbC123', 'title' => 'Survey']]],
            'GET /workspaces' => ['page_count' => 1, 'items' => [['id' => 'ws1', 'name' => 'Marketing', 'forms' => ['count' => 4]]]],
        ]);
        $query = new NodeOptionsQuery(app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']), User::factory()->create(), [], 'tf_test');
        $source = app(TypeformOptions::class);

        $forms = $source->load('typeform.forms', $query);

        expect($forms->toArray())->toBe(['options' => [['value' => 'AbC123', 'label' => 'Survey', 'description' => 'AbC123']], 'next_cursor' => '2'])
            ->and($source->load('typeform.workspaces', $query)->toArray())->toBe(['options' => [['value' => 'ws1', 'label' => 'Marketing', 'description' => '4 forms']], 'next_cursor' => null]);
    });
});

/**
 * A published workflow whose first step is the response trigger, with its
 * subscription synced against a faked Typeform.
 *
 * @param  array<string, mixed>  $nodeConfig
 * @param  array<string, mixed>  $routes
 */
function typeformTrigger(array $nodeConfig = ['form_id' => 'AbC123'], array $routes = [], bool $sync = true): Trigger
{
    Queue::fake([FireTriggerEvent::class, PollTrigger::class]);
    fakeTypeform($routes);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $credential = ConnectorCredential::factory()->forWorkspace($workspace)
        ->forConnector(Connector::query()->firstWhere('key', 'typeform') ?? Connector::factory()->create(['key' => 'typeform']))
        ->create(['data' => ['api_key' => 'tf_test'], 'created_by' => $owner->id]);

    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'start', 'type' => 'typeform_list_responses', 'config' => ['credential_id' => $credential->id, 'activate' => true, ...$nodeConfig]],
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
 * A delivery as Typeform sends it, signed with the webhook's secret.
 */
function typeformDelivery(string $secret, string $form = 'AbC123', string $eventId = 'evt-1', bool $tamper = false)
{
    $body = json_encode(['event_id' => $eventId, 'event_type' => 'form_response', 'form_response' => ['form_id' => $form, 'token' => 'resp1']]);

    return test()->call('POST', '/api/hooks/apps/typeform', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_TYPEFORM_SIGNATURE' => 'sha256='.base64_encode(hash_hmac('sha256', $body, $secret, true)),
    ], $tamper ? $body.' ' : $body);
}

describe('typeform triggers', function () {
    it('polls responses: a baseline first, then one run for each new submission', function () {
        $trigger = typeformTrigger(sync: false);
        $poll = fn (array $responses) => fakeTypeform(['GET /forms/AbC123/responses' => ['items' => $responses], 'GET /forms/AbC123' => typeformForm()]);

        $poll([typeformResponse('r1')]);
        dispatch_sync(new PollTrigger($trigger));
        expect($trigger->events()->where('status', 'queued')->count())->toBe(0);

        $poll([typeformResponse('r3'), typeformResponse('r2'), typeformResponse('r1')]);
        dispatch_sync(new PollTrigger($trigger->fresh()));

        $events = $trigger->events()->where('status', 'queued')->get();

        expect($events->pluck('payload.token')->sort()->values()->all())->toBe(['r2', 'r3'])
            ->and($events->first()->payload['answers_map'])->toMatchArray(['Plan' => 'Pro']);
    });

    it('sets one webhook on the form, signed with its own secret, and starts a poll when a signed response arrives', function () {
        $trigger = typeformTrigger(routes: ['PUT /forms/AbC123/webhooks/*' => ['enabled' => true]]);
        $watch = ProviderWatch::query()->sole();
        $tag = $watch->meta['tag'];

        expect($watch->resource_key)->toBe('form:AbC123')
            ->and($watch->external_id)->toBe('AbC123')
            ->and($tag)->toStartWith('flows-')
            ->and($trigger->subscription->routing_key)->toBe('AbC123')
            ->and($trigger->subscription->status)->toBe(SubscriptionStatus::Active)
            ->and(typeformSent("PUT /forms/AbC123/webhooks/{$tag}"))->toBe(['url' => url('/api/hooks/apps/typeform'), 'enabled' => true, 'secret' => $watch->secret, 'verify_ssl' => true, 'event_types' => ['form_response' => true]]);

        typeformDelivery((string) $watch->secret)->assertOk();
        typeformDelivery((string) $watch->secret)->assertOk();

        Queue::assertPushed(PollTrigger::class, 1);
    });

    it('refuses a delivery with a wrong secret, an altered body or an unwatched form', function () {
        typeformTrigger(routes: ['PUT /forms/AbC123/webhooks/*' => ['enabled' => true]]);
        $watch = ProviderWatch::query()->sole();

        typeformDelivery('not-the-secret')->assertUnauthorized();
        typeformDelivery((string) $watch->secret, tamper: true)->assertUnauthorized();
        typeformDelivery((string) $watch->secret, form: 'Other1')->assertUnauthorized();
        test()->postJson('/api/hooks/apps/typeform', ['form_response' => ['form_id' => 'AbC123']])->assertUnauthorized();

        Queue::assertNotPushed(PollTrigger::class);
    });

    it('is closed while the switch is off', function () {
        typeformTrigger(routes: ['PUT /forms/AbC123/webhooks/*' => ['enabled' => true]]);
        $watch = ProviderWatch::query()->sole();
        config(['triggers.push.typeform.enabled' => false]);

        typeformDelivery((string) $watch->secret)->assertNotFound();
    });

    it('keeps polling when the form is not named, and says so when Typeform refuses the webhook', function () {
        $unnamed = typeformTrigger(['form_id' => '{{ trigger.form }}']);
        expect($unnamed->subscription)->toBeNull();

        $forbidden = typeformTrigger(routes: ['PUT /forms/AbC123/webhooks/*' => fn () => Http::response(['code' => 'FORBIDDEN', 'description' => 'Missing scope'], 403)]);
        expect($forbidden->subscription->status)->toBe(SubscriptionStatus::Failed)->and($forbidden->subscription->last_error)->toContain('403');
    });

    it('turns a webhook Typeform disabled back on, makes it again if it is gone, and removes it', function () {
        typeformTrigger(routes: ['PUT /forms/AbC123/webhooks/*' => ['enabled' => true]]);
        $watch = ProviderWatch::query()->sole();
        $tag = $watch->meta['tag'];
        $source = app(TypeformSource::class);

        fakeTypeform(["GET /forms/AbC123/webhooks/{$tag}" => ['enabled' => false], "PUT /forms/AbC123/webhooks/{$tag}" => ['enabled' => true]]);
        app(WatchManager::class)->renew($source, $watch->fresh());
        expect(typeformSent("PUT /forms/AbC123/webhooks/{$tag}")['secret'])->toBe($watch->secret);

        fakeTypeform(["GET /forms/AbC123/webhooks/{$tag}" => fn () => Http::response(['code' => 'NOT_FOUND'], 404), "PUT /forms/AbC123/webhooks/{$tag}" => ['enabled' => true]]);
        app(WatchManager::class)->renew($source, $watch->fresh());
        expect($watch->fresh()->status)->toBe(SubscriptionStatus::Active)->and(typeformSent("PUT /forms/AbC123/webhooks/{$tag}")['url'])->toBe(url('/api/hooks/apps/typeform'));

        fakeTypeform(["DELETE /forms/AbC123/webhooks/{$tag}" => null]);
        app(WatchManager::class)->remove($source, $watch->fresh());
        Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE');
        expect(ProviderWatch::query()->count())->toBe(0);
    });
});
