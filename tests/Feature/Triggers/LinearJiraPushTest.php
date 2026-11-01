<?php

use App\Enums\Triggers\SubscriptionStatus;
use App\Jobs\Triggers\FireTriggerEvent;
use App\Jobs\Triggers\PollTrigger;
use App\Jobs\Triggers\SyncTriggerSubscription;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Triggers\ProviderEvent;
use App\Models\Triggers\ProviderWatch;
use App\Models\Triggers\Trigger;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Services\Triggers\Push\JiraSource;
use App\Services\Triggers\Push\LinearSource;
use App\Services\Triggers\Push\WatchManager;
use App\Services\Workspaces\WorkspaceService;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/**
 * A published workflow whose first step is a Linear or Jira reader node switched
 * on as a trigger, with that provider's push on, and its subscription synced.
 *
 * @param  array<string, mixed>  $nodeConfig
 * @param  array<string, mixed>  $credentialData
 */
function pushTrigger(string $provider, string $nodeType, array $nodeConfig, array $credentialData): Trigger
{
    config(["triggers.push.{$provider}.enabled" => true]);
    Queue::fake([FireTriggerEvent::class, PollTrigger::class]);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $credential = ConnectorCredential::factory()->forWorkspace($workspace)
        ->forConnector(Connector::query()->firstWhere('key', $provider) ?? Connector::factory()->create(['key' => $provider]))
        ->create(['data' => $credentialData, 'created_by' => $owner->id]);

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
 * Answer Linear's GraphQL endpoint by text in the query; the value is `data`,
 * a closure taking the request, or a ready response. Anything else is an
 * `errors` answer, so a node that asks for something unexpected fails the test.
 *
 * @param  array<string, mixed>  $handlers
 */
function fakeLinearPush(array $handlers): void
{
    Http::swap(new Factory);
    Http::fake(['api.linear.app/graphql' => function (Request $request) use ($handlers) {
        foreach ($handlers as $needle => $answer) {
            if (str_contains((string) $request['query'], $needle)) {
                $answer = value($answer, $request);

                return $answer instanceof PromiseInterface ? $answer : Http::response(['data' => $answer]);
            }
        }

        return Http::response(['errors' => [['message' => 'Nothing fakes: '.substr((string) $request['query'], 0, 80)]]]);
    }]);
}

/**
 * @param  array<string, mixed>  $nodeConfig
 */
function linearPushTrigger(string $nodeType = 'linear_list_new_issues', array $nodeConfig = ['team' => 'ENG'], string $scope = 'read,write,admin'): Trigger
{
    fakeLinearPush([
        'teams(filter' => ['teams' => ['nodes' => [['id' => 'team-uuid']]]],
        'webhookCreate(' => ['webhookCreate' => ['success' => true, 'webhook' => ['id' => 'wh-1', 'enabled' => true]]],
    ]);

    return pushTrigger('linear', $nodeType, $nodeConfig, ['access_token' => 'lin_test', 'scope' => $scope]);
}

/**
 * A delivery as Linear sends it, signed with the watch's secret.
 *
 * @param  array<string, mixed>  $overrides
 */
function linearDelivery(ProviderWatch $watch, array $overrides = [], ?string $signWith = null, string $delivery = 'd-1')
{
    $body = json_encode([
        'action' => 'create', 'type' => 'Issue', 'data' => ['id' => 'i1'], 'webhookId' => 'wh-1', 'webhookTimestamp' => now()->getTimestampMs(),
        ...$overrides,
    ]);

    return test()->call('POST', '/api/hooks/apps/linear', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_LINEAR_DELIVERY' => $delivery,
        'HTTP_LINEAR_SIGNATURE' => hash_hmac('sha256', $body, $signWith ?? (string) $watch->secret),
    ], $body);
}

describe('linear: a webhook wakes the trigger\'s own poll', function () {
    it('registers one webhook for the team and kind of record, signed with its own secret', function (string $nodeType, string $record) {
        $trigger = linearPushTrigger($nodeType);
        $watch = ProviderWatch::query()->sole();

        expect($watch->resource_key)->toBe("eng:{$record}")
            ->and($watch->external_id)->toBe('wh-1')
            ->and($watch->meta)->toMatchArray(['team_id' => 'team-uuid', 'type' => $record])
            ->and($watch->expires_at)->toBeNull()
            ->and($watch->renew_at->isFuture())->toBeTrue()
            ->and($trigger->subscription->routing_key)->toBe('wh-1')
            ->and($trigger->subscription->status)->toBe(SubscriptionStatus::Active);

        Http::assertSent(fn (Request $request): bool => str_contains((string) $request['query'], 'webhookCreate(')
            && $request['variables']['input'] === [
                'url' => url('/api/hooks/apps/linear'), 'resourceTypes' => [$record], 'teamId' => 'team-uuid',
                'secret' => $watch->secret, 'label' => 'Flows', 'enabled' => true,
            ]);
    })->with([
        'new issue' => ['linear_list_new_issues', 'Issue'],
        'issue updated' => ['linear_list_updated_issues', 'Issue'],
        'new comment' => ['linear_list_new_comments', 'Comment'],
    ]);

    it('watches every public team when no team is chosen', function () {
        linearPushTrigger('linear_list_new_comments', []);
        $watch = ProviderWatch::query()->sole();

        expect($watch->resource_key)->toBe('*:Comment');
        Http::assertSent(fn (Request $request): bool => str_contains((string) $request['query'], 'webhookCreate(')
            && ($request['variables']['input']['allPublicTeams'] ?? null) === true
            && ! array_key_exists('teamId', $request['variables']['input']));
    });

    it('pokes the poll when a signed delivery arrives', function () {
        $trigger = linearPushTrigger();
        $watch = ProviderWatch::query()->sole();

        linearDelivery($watch)->assertOk();

        Queue::assertPushed(PollTrigger::class, fn (PollTrigger $job): bool => $job->trigger->is($trigger));
    });

    it('pokes once however often Linear retries the same delivery', function () {
        linearPushTrigger();
        $watch = ProviderWatch::query()->sole();

        linearDelivery($watch, delivery: 'same')->assertOk();
        linearDelivery($watch, delivery: 'same')->assertOk();

        Queue::assertPushed(PollTrigger::class, 1);
    });

    it('refuses a delivery with a wrong signature, a stale timestamp or an unknown webhook', function () {
        linearPushTrigger();
        $watch = ProviderWatch::query()->sole();

        linearDelivery($watch, signWith: 'not-the-secret')->assertUnauthorized();
        linearDelivery($watch, ['webhookTimestamp' => now()->subMinutes(10)->getTimestampMs()])->assertUnauthorized();
        linearDelivery($watch, ['webhookId' => 'someone-else'])->assertUnauthorized();
        test()->postJson('/api/hooks/apps/linear', ['webhookId' => 'wh-1', 'webhookTimestamp' => now()->getTimestampMs()])->assertUnauthorized();

        Queue::assertNotPushed(PollTrigger::class);
    });

    it('is closed while the provider\'s switch is off', function () {
        linearPushTrigger();
        $watch = ProviderWatch::query()->sole();
        config(['triggers.push.linear.enabled' => false]);

        linearDelivery($watch)->assertNotFound();
    });

    it('keeps polling and says why when the account was connected without admin access', function () {
        $trigger = linearPushTrigger(scope: 'read,write');

        expect($trigger->subscription->last_error)->toContain('without permission to register webhooks');
    });

    it('asks for a reconnect when Linear refuses to create the webhook', function () {
        fakeLinearPush([
            'teams(filter' => ['teams' => ['nodes' => [['id' => 'team-uuid']]]],
            'webhookCreate(' => fn () => Http::response(['errors' => [['message' => 'Forbidden', 'extensions' => ['code' => 'FORBIDDEN']]]]),
        ]);

        $trigger = pushTrigger('linear', 'linear_list_new_issues', ['team' => 'ENG'], ['access_token' => 'lin_test', 'scope' => 'read,write,admin']);

        expect($trigger->subscription->status)->toBe(SubscriptionStatus::ReconnectNeeded)
            ->and($trigger->subscription->last_error)->toContain('admin access')
            ->and(ProviderWatch::query()->count())->toBe(0);
    });

    it('switches a webhook Linear turned off back on, and makes it again if it is gone', function () {
        linearPushTrigger();
        $watch = ProviderWatch::query()->sole();
        $source = app(LinearSource::class);

        fakeLinearPush([
            'webhook(id: $id)' => ['webhook' => ['id' => 'wh-1', 'enabled' => false]],
            'webhookUpdate(' => ['webhookUpdate' => ['success' => true]],
        ]);
        app(WatchManager::class)->renew($source, $watch->fresh());

        Http::assertSent(fn (Request $request): bool => str_contains((string) $request['query'], 'webhookUpdate(') && $request['variables'] === ['id' => 'wh-1', 'input' => ['enabled' => true]]);
        expect($watch->fresh()->status)->toBe(SubscriptionStatus::Active);

        fakeLinearPush([
            'webhook(id: $id)' => ['webhook' => null],
            'teams(filter' => ['teams' => ['nodes' => [['id' => 'team-uuid']]]],
            'webhookCreate(' => ['webhookCreate' => ['success' => true, 'webhook' => ['id' => 'wh-2', 'enabled' => true]]],
        ]);
        app(WatchManager::class)->renew($source, $watch->fresh());

        expect($watch->fresh()->external_id)->toBe('wh-2')
            ->and($watch->fresh()->secret)->not->toBe($watch->secret)
            ->and(Trigger::query()->sole()->subscription->routing_key)->toBe('wh-2');
    });

    it('deletes the webhook when the watch is removed', function () {
        linearPushTrigger();
        $watch = ProviderWatch::query()->sole();
        fakeLinearPush(['webhookDelete(' => ['webhookDelete' => ['success' => true]]]);

        app(WatchManager::class)->remove(app(LinearSource::class), $watch);

        Http::assertSent(fn (Request $request): bool => str_contains((string) $request['query'], 'webhookDelete(') && $request['variables'] === ['id' => 'wh-1']);
        expect(ProviderWatch::query()->count())->toBe(0);
    });
});

/**
 * Answer Atlassian: the account's sites, and Jira's webhook calls by method.
 *
 * @param  array<string, mixed>  $overrides  "METHOD path" => response
 */
function fakeJiraPush(array $overrides = []): void
{
    $created = 0;
    Http::swap(new Factory);
    Http::fake(function (Request $request) use ($overrides, &$created) {
        $url = $request->url();
        $path = (string) parse_url($url, PHP_URL_PATH);
        $key = $request->method().' '.preg_replace('~^/ex/jira/[^/]+/rest/api/3~', '', $path);

        if (isset($overrides[$key])) {
            return value($overrides[$key], $request);
        }

        return match (true) {
            str_contains($url, 'accessible-resources') => Http::response([['id' => 'cloud-1', 'url' => 'https://acme.atlassian.net', 'name' => 'acme'], ['id' => 'cloud-2', 'url' => 'https://beta.atlassian.net', 'name' => 'beta']]),
            $key === 'POST /webhook' => Http::response(['webhookRegistrationResult' => [['createdWebhookId' => 1234 + $created++ * 1000]]]),
            $key === 'PUT /webhook/refresh' => Http::response(['expirationDate' => now()->addDays(30)->toIso8601String()]),
            $key === 'DELETE /webhook' => Http::response(null, 202),
            default => Http::response(['errorMessages' => ["Nothing fakes {$key}"]], 404),
        };
    });
}

/**
 * @param  array<string, mixed>  $nodeConfig
 * @param  array<string, mixed>  $overrides
 */
function jiraPushTrigger(string $nodeType = 'jira_list_new_issues', array $nodeConfig = ['project' => 'app'], string $scope = 'read:jira-work manage:jira-webhook', array $overrides = []): Trigger
{
    fakeJiraPush($overrides);

    return pushTrigger('jira', $nodeType, $nodeConfig, ['access_token' => 'atl_test', 'scope' => $scope]);
}

/**
 * A delivery as Jira sends it: unsigned, to the URL that holds the watch's token.
 */
function jiraDelivery(string $token, string $event = 'jira:issue_created', ?string $identifier = 'jira-1')
{
    return test()->call('POST', '/api/hooks/apps/jira?token='.$token, [], [], [], array_filter([
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_ATLASSIAN_WEBHOOK_IDENTIFIER' => $identifier,
    ]), json_encode(['webhookEvent' => $event, 'issue' => ['key' => 'APP-1']]));
}

describe('jira: a webhook wakes the trigger\'s own poll', function () {
    it('registers a dynamic webhook for the event, limited to the project when fields choose it', function (string $nodeType, array $nodeConfig, string $key, ?string $jql) {
        $trigger = jiraPushTrigger($nodeType, $nodeConfig);
        $watch = ProviderWatch::query()->sole();

        expect($watch->resource_key)->toBe($key)
            ->and($watch->external_id)->toBe('1234')
            ->and($watch->meta)->toMatchArray(['cloud_id' => 'cloud-1', 'token_hash' => hash('sha256', $watch->secret)])
            ->and($watch->expires_at->between(now()->addDays(29), now()->addDays(31)))->toBeTrue()
            ->and($watch->renew_at->between(now()->addDays(19), now()->addDays(21)))->toBeTrue()
            ->and($trigger->subscription->routing_key)->toBe(hash('sha256', $watch->secret));

        Http::assertSent(function (Request $request) use ($watch, $jql, $key): bool {
            if ($request->method() !== 'POST' || ! str_ends_with($request->url(), '/ex/jira/cloud-1/rest/api/3/webhook')) {
                return false;
            }

            return $request['url'] === url('/api/hooks/apps/jira').'?token='.$watch->secret
                && $request['webhooks'] === [array_filter(['events' => [explode('|', $key)[1]], 'jqlFilter' => $jql])];
        });
    })->with([
        'new issues in a project' => ['jira_list_new_issues', ['project' => 'app'], '-|jira:issue_created|APP', 'project = "APP"'],
        'updated issues in a project' => ['jira_list_updated_issues', ['project' => 'OPS', 'filter_mode' => 'basic'], '-|jira:issue_updated|OPS', 'project = "OPS"'],
        'new issues found by JQL' => ['jira_list_new_issues', ['filter_mode' => 'jql', 'jql' => 'project = APP OR project = OPS'], '-|jira:issue_created|*', null],
        'new issues of no project' => ['jira_list_new_issues', [], '-|jira:issue_created|*', null],
    ]);

    it('registers on the site the node names', function () {
        jiraPushTrigger('jira_list_new_issues', ['project' => 'APP', 'site' => 'Beta.atlassian.net']);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && str_contains($request->url(), '/ex/jira/cloud-2/rest/api/3/webhook'));
        expect(ProviderWatch::query()->sole()->resource_key)->toBe('beta.atlassian.net|jira:issue_created|APP');
    });

    it('pokes the poll when a delivery arrives with the watch\'s token', function () {
        $trigger = jiraPushTrigger();
        $watch = ProviderWatch::query()->sole();

        jiraDelivery((string) $watch->secret)->assertOk();

        Queue::assertPushed(PollTrigger::class, fn (PollTrigger $job): bool => $job->trigger->is($trigger));
    });

    it('records a delivery Jira repeats once, tells deliveries apart by their identifier, and shares one queued poll', function () {
        jiraPushTrigger();
        $token = (string) ProviderWatch::query()->sole()->secret;

        jiraDelivery($token, identifier: 'one')->assertOk();
        jiraDelivery($token, identifier: 'one')->assertOk();
        jiraDelivery($token, identifier: 'two')->assertOk();

        // Jira repeating "one" is recorded once; "two" is a second delivery.
        expect(ProviderEvent::query()->where('provider', 'jira')->pluck('event_id')->sort()->values()->all())->toBe(['one', 'two']);

        // A poll is unique per trigger, so deliveries that arrive while one is
        // already queued share it instead of queuing another.
        Queue::assertPushed(PollTrigger::class, 1);
    });

    it('refuses a delivery without the token, or with another one', function () {
        jiraPushTrigger();

        test()->postJson('/api/hooks/apps/jira', ['webhookEvent' => 'jira:issue_created'])->assertUnauthorized();
        jiraDelivery('guess')->assertUnauthorized();

        Queue::assertNotPushed(PollTrigger::class);
    });

    it('is closed while the provider\'s switch is off', function () {
        jiraPushTrigger();
        $token = (string) ProviderWatch::query()->sole()->secret;
        config(['triggers.push.jira.enabled' => false]);

        jiraDelivery($token)->assertNotFound();
    });

    it('keeps polling and says why when the account was connected without the webhook scope', function () {
        $trigger = jiraPushTrigger(scope: 'read:jira-work');

        expect($trigger->subscription->last_error)->toContain('without permission to register webhooks');
    });

    it('keeps polling when Atlassian refuses the registration', function () {
        $trigger = jiraPushTrigger(overrides: ['POST /webhook' => fn () => Http::response(['message' => 'Forbidden'], 403)]);

        expect($trigger->subscription->status)->toBe(SubscriptionStatus::Failed)
            ->and($trigger->subscription->last_error)->toContain('403')
            ->and(ProviderWatch::query()->count())->toBe(0);
    });

    it('says what Jira rejected when it answers 200 with an error for the webhook', function () {
        $trigger = jiraPushTrigger(overrides: ['POST /webhook' => fn () => Http::response(['webhookRegistrationResult' => [['errors' => ['The JQL is not supported']]]])]);

        expect($trigger->subscription->status)->toBe(SubscriptionStatus::Failed)
            ->and($trigger->subscription->last_error)->toContain('The JQL is not supported')
            ->and(ProviderWatch::query()->count())->toBe(0);
    });

    it('extends the webhook before it expires', function () {
        jiraPushTrigger();
        $watch = ProviderWatch::query()->sole();
        $expires = now()->addDays(30)->startOfSecond();

        fakeJiraPush(['PUT /webhook/refresh' => fn () => Http::response(['expirationDate' => $expires->toIso8601String()])]);
        app(WatchManager::class)->renew(app(JiraSource::class), $watch->fresh());

        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT' && str_ends_with($request->url(), '/webhook/refresh') && $request['webhookIds'] === [1234]);
        expect($watch->fresh()->expires_at->equalTo($expires))->toBeTrue()
            ->and($watch->fresh()->renew_at->between(now()->addDays(19), now()->addDays(21)))->toBeTrue()
            ->and($watch->fresh()->secret)->toBe($watch->secret);
    });

    it('makes the webhook again, with a new token, when Jira has dropped it', function () {
        $trigger = jiraPushTrigger();
        $watch = ProviderWatch::query()->sole();

        fakeJiraPush(['PUT /webhook/refresh' => fn () => Http::response(['errorMessages' => ['Webhook not found']], 404)]);
        app(WatchManager::class)->renew(app(JiraSource::class), $watch->fresh());

        $fresh = $watch->fresh();

        expect($fresh->external_id)->toBe('1234')
            ->and($fresh->secret)->not->toBe($watch->secret)
            ->and($trigger->subscription->fresh()->routing_key)->toBe(hash('sha256', (string) $fresh->secret))
            ->and($fresh->status)->toBe(SubscriptionStatus::Active);

        // The old URL no longer reaches anything; the new one does.
        jiraDelivery((string) $watch->secret)->assertUnauthorized();
        jiraDelivery((string) $fresh->secret)->assertOk();
    });

    it('deletes the webhook when the watch is removed', function () {
        jiraPushTrigger();
        $watch = ProviderWatch::query()->sole();
        fakeJiraPush();

        app(WatchManager::class)->remove(app(JiraSource::class), $watch);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE' && str_ends_with($request->url(), '/ex/jira/cloud-1/rest/api/3/webhook') && $request['webhookIds'] === [1234]);
        expect(ProviderWatch::query()->count())->toBe(0);
    });
});

it('keeps the push switch for linear and jira off by default, and asks for the extra scope only when on', function () {
    expect(config('triggers.push.linear.enabled'))->toBeFalse()
        ->and(config('triggers.push.jira.enabled'))->toBeFalse();

    $scopes = function (string $key): array {
        test()->syncSetup('connectors');

        return Connector::query()->where('key', $key)->firstOrFail()->oauth['scopes'];
    };

    expect($scopes('linear'))->not->toContain('admin')
        ->and($scopes('jira'))->not->toContain('manage:jira-webhook');

    config(['triggers.push.linear.enabled' => true, 'triggers.push.jira.enabled' => true]);

    expect($scopes('linear'))->toContain('admin')
        ->and($scopes('jira'))->toContain('manage:jira-webhook');
});
