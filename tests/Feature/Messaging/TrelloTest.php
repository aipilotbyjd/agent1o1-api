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
use App\Nodes\Integrations\Trello\AbstractTrelloNode;
use App\Nodes\Integrations\Trello\TrelloAddCommentNode;
use App\Nodes\Integrations\Trello\TrelloAddLabelNode;
use App\Nodes\Integrations\Trello\TrelloCreateCardNode;
use App\Nodes\Integrations\Trello\TrelloCreateChecklistNode;
use App\Nodes\Integrations\Trello\TrelloCreateLabelNode;
use App\Nodes\Integrations\Trello\TrelloListActivityNode;
use App\Nodes\Integrations\Trello\TrelloListCardsNode;
use App\Nodes\Integrations\Trello\TrelloMoveCardNode;
use App\Nodes\Integrations\Trello\TrelloRemoveLabelNode;
use App\Nodes\Integrations\Trello\TrelloSearchCardsNode;
use App\Nodes\Integrations\Trello\TrelloUpdateCardNode;
use App\Nodes\Integrations\Trello\TrelloUpdateListNode;
use App\Services\Triggers\Push\TrelloSource;
use App\Services\Triggers\Push\WatchManager;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use App\Services\Workflows\NodeOptions\Sources\TrelloOptions;
use App\Services\Workflows\NodeRegistry;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

const TRELLO_BOARD = '5f1c2b3a4d5e6f7a8b9c0d1e';
const TRELLO_LIST = '5f1c2b3a4d5e6f7a8b9c0d2f';
const TRELLO_CARD = '5f1c2b3a4d5e6f7a8b9c0d3a';

/**
 * A run with a connected Trello account in its workspace.
 */
function trelloRun(): Run
{
    $run = Run::factory()->create();

    ConnectorCredential::factory()->create([
        'workspace_id' => $run->workspace_id,
        'connector_id' => (Connector::query()->firstWhere('key', 'trello') ?? Connector::factory()->create(['key' => 'trello']))->id,
        'data' => ['app_key' => 'appkey1', 'api_key' => 'tok1'],
        'is_default' => true,
    ]);

    return $run;
}

/**
 * Answer Trello. A key is "METHOD /path" under /1 (a trailing * takes any id);
 * the value is the JSON body (null is a 200 with nothing), or a closure
 * returning a response. A route nobody answers is a 404, so a node that calls
 * something unexpected fails the test.
 *
 * @param  array<string, mixed>  $routes
 */
function fakeTrello(array $routes): void
{
    Http::swap(new Factory);
    Http::fake(['api.trello.com/*' => function (Request $request) use ($routes) {
        $key = $request->method().' '.preg_replace('~^/1~', '', (string) parse_url($request->url(), PHP_URL_PATH));
        $route = array_key_exists($key, $routes) ? $key : collect(array_keys($routes))->first(fn (string $pattern): bool => Str::is($pattern, $key));

        if ($route === null) {
            return Http::response('The requested resource was not found.', 404);
        }

        $answer = value($routes[$route], $request);

        return $answer instanceof GuzzleHttp\Promise\PromiseInterface ? $answer : Http::response($answer ?? []);
    }]);
}

/**
 * The request made to a route.
 */
function trelloRequest(string $route): Request
{
    $found = array_values(array_filter(Http::recorded()->all(), fn (array $pair): bool => $pair[0]->method().' '.preg_replace('~^/1~', '', (string) parse_url($pair[0]->url(), PHP_URL_PATH)) === $route));

    expect($found)->toHaveCount(1);

    return $found[0][0];
}

/**
 * @return array<string, mixed>
 */
function trelloBody(string $route): array
{
    return (array) json_decode(trelloRequest($route)->body(), true);
}

/**
 * @return array<string, string>
 */
function trelloQuery(string $route): array
{
    parse_str((string) parse_url(trelloRequest($route)->url(), PHP_URL_QUERY), $query);

    return $query;
}

/**
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function trelloCard(string $id = TRELLO_CARD, array $extra = []): array
{
    return ['id' => $id, 'name' => 'Write the email', 'desc' => 'Draft it', 'shortUrl' => 'https://trello.com/c/abc', 'idList' => TRELLO_LIST, 'idBoard' => TRELLO_BOARD, 'due' => null, 'dueComplete' => false, 'closed' => false, 'labels' => [['name' => 'Urgent', 'color' => 'red']], 'idLabels' => ['l1'], 'idMembers' => ['m1'], 'dateLastActivity' => '2026-03-01T10:00:00.000Z', ...$extra];
}

describe('trello nodes', function () {
    it('registers the nodes under the trello connector', function () {
        $registry = app(NodeRegistry::class);
        $types = array_filter(array_column($registry->placeableTypes(), 'type'), fn (string $type): bool => str_starts_with($type, 'trello_'));

        expect(count($types))->toBeGreaterThanOrEqual(29);

        foreach ($types as $type) {
            expect($registry->resolve($type))->toBeInstanceOf(AbstractTrelloNode::class)->and($registry->resolve($type)->category())->toBe('trello');
        }
    });

    it('creates a card with the key and token in the query and the card in the body', function () {
        fakeTrello(['POST /cards' => trelloCard()]);

        $output = (new TrelloCreateCardNode)->execute(trelloRun(), ['list_id' => TRELLO_LIST, 'name' => ' Write the email ', 'description' => 'Draft it', 'due' => '2026-03-05T09:00:00Z', 'label_ids' => ['abc123', 'def456'], 'member_ids' => 'm1, m2', 'position' => 'top'], []);

        expect($output)->toMatchArray(['id' => TRELLO_CARD, 'name' => 'Write the email', 'url' => 'https://trello.com/c/abc', 'list_id' => TRELLO_LIST, 'labels' => ['Urgent'], 'archived' => false])
            ->and(trelloBody('POST /cards'))->toBe(['idList' => TRELLO_LIST, 'name' => 'Write the email', 'desc' => 'Draft it', 'due' => '2026-03-05T09:00:00.000Z', 'idLabels' => 'abc123,def456', 'idMembers' => 'm1,m2', 'pos' => 'top'])
            ->and(trelloQuery('POST /cards'))->toMatchArray(['key' => 'appkey1', 'token' => 'tok1']);
    });

    it('checks ids and links before calling Trello', function () {
        $node = new TrelloCreateCardNode;
        $run = trelloRun();

        expect(fn () => $node->execute($run, ['list_id' => '../boards', 'name' => 'x'], []))->toThrow(InvalidArgumentException::class, 'not a valid Trello id')
            ->and(fn () => $node->execute($run, ['name' => 'x'], []))->toThrow(InvalidArgumentException::class, 'The list is required')
            ->and(fn () => $node->execute($run, ['list_id' => TRELLO_LIST, 'name' => 'x', 'url_source' => 'javascript:alert(1)'], []))->toThrow(InvalidArgumentException::class, 'must start with http')
            ->and(fn () => $node->execute($run, ['list_id' => TRELLO_LIST, 'name' => 'x', 'label_ids' => ['ok', 'bad/id']], []))->toThrow(InvalidArgumentException::class, 'not a valid Trello id')
            ->and(fn () => $node->execute(Run::factory()->create(), ['list_id' => TRELLO_LIST, 'name' => 'x'], []))->toThrow(RuntimeException::class);
        Http::assertNothingSent();
    });

    it('updates only what is filled in, and moves a card', function () {
        fakeTrello(['PUT /cards/'.TRELLO_CARD => trelloCard(extra: ['closed' => true])]);
        $run = trelloRun();

        $output = (new TrelloUpdateCardNode)->execute($run, ['card_id' => TRELLO_CARD, 'due_complete' => 'done', 'archived' => 'yes'], []);
        expect($output['archived'])->toBeTrue()->and(trelloBody('PUT /cards/'.TRELLO_CARD))->toBe(['dueComplete' => true, 'closed' => true])
            ->and(fn () => (new TrelloUpdateCardNode)->execute($run, ['card_id' => TRELLO_CARD], []))->toThrow(InvalidArgumentException::class, 'Change at least one thing');

        fakeTrello(['PUT /cards/'.TRELLO_CARD => trelloCard()]);
        (new TrelloMoveCardNode)->execute($run, ['card_id' => TRELLO_CARD, 'list_id' => TRELLO_LIST, 'board_id' => TRELLO_BOARD, 'position' => 'bottom'], []);

        expect(trelloBody('PUT /cards/'.TRELLO_CARD))->toBe(['idList' => TRELLO_LIST, 'idBoard' => TRELLO_BOARD, 'pos' => 'bottom']);
    });

    it('lists the cards of a list or board and searches', function () {
        fakeTrello(['GET /lists/'.TRELLO_LIST.'/cards' => [trelloCard(), trelloCard('5f1c2b3a4d5e6f7a8b9c0d3b')], 'GET /search' => ['cards' => [trelloCard()]]]);
        $run = trelloRun();

        $listed = (new TrelloListCardsNode)->execute($run, ['list_id' => TRELLO_LIST, 'limit' => 1], []);
        $found = (new TrelloSearchCardsNode)->execute($run, ['query' => 'launch', 'board_ids' => [TRELLO_BOARD]], []);

        expect($listed['count'])->toBe(1)
            ->and($found['cards'][0]['id'])->toBe(TRELLO_CARD)
            ->and(trelloQuery('GET /search'))->toMatchArray(['query' => 'launch', 'modelTypes' => 'cards', 'idBoards' => TRELLO_BOARD])
            ->and(fn () => (new TrelloListCardsNode)->execute($run, [], []))->toThrow(InvalidArgumentException::class, 'The board or the list is required');
    });

    it('comments, adds and removes labels', function () {
        fakeTrello([
            'POST /cards/'.TRELLO_CARD.'/actions/comments' => ['id' => 'a1', 'date' => '2026-03-01T10:00:00.000Z', 'data' => ['text' => 'Looks good']],
            'POST /cards/'.TRELLO_CARD.'/idLabels' => [], 'DELETE /cards/'.TRELLO_CARD.'/idLabels/abc123' => [],
        ]);
        $run = trelloRun();

        $comment = (new TrelloAddCommentNode)->execute($run, ['card_id' => TRELLO_CARD, 'text' => 'Looks good'], []);
        $added = (new TrelloAddLabelNode)->execute($run, ['card_id' => TRELLO_CARD, 'label_id' => 'abc123'], []);
        $removed = (new TrelloRemoveLabelNode)->execute($run, ['card_id' => TRELLO_CARD, 'label_id' => 'abc123'], []);

        expect($comment)->toBe(['id' => 'a1', 'card_id' => TRELLO_CARD, 'text' => 'Looks good', 'date' => '2026-03-01T10:00:00.000Z'])
            ->and($added['added'])->toBeTrue()->and($removed['removed'])->toBeTrue()
            ->and(trelloBody('POST /cards/'.TRELLO_CARD.'/idLabels'))->toBe(['value' => 'abc123']);
    });

    it('adds a checklist with its first items', function () {
        fakeTrello(['POST /checklists' => ['id' => 'chk1', 'name' => 'Launch'], 'POST /checklists/chk1/checkItems' => ['id' => 'i1']]);

        $output = (new TrelloCreateChecklistNode)->execute(trelloRun(), ['card_id' => TRELLO_CARD, 'name' => 'Launch', 'items' => ['Write', ' ', 'Send']], []);

        expect($output)->toBe(['id' => 'chk1', 'name' => 'Launch', 'card_id' => TRELLO_CARD, 'items_added' => 2]);
        Http::assertSentCount(3);
    });

    it('validates a label colour, and renames or archives a list', function () {
        fakeTrello(['POST /labels' => ['id' => 'l9', 'name' => 'Urgent', 'color' => 'red'], 'PUT /lists/'.TRELLO_LIST => ['id' => TRELLO_LIST, 'name' => 'Done', 'closed' => true]]);
        $run = trelloRun();

        expect((new TrelloCreateLabelNode)->execute($run, ['board_id' => TRELLO_BOARD, 'name' => 'Urgent', 'color' => 'red'], []))->toBe(['id' => 'l9', 'name' => 'Urgent', 'color' => 'red'])
            ->and(fn () => (new TrelloCreateLabelNode)->execute($run, ['board_id' => TRELLO_BOARD, 'name' => 'x', 'color' => 'teal'], []))->toThrow(InvalidArgumentException::class, 'not a Trello label colour')
            ->and((new TrelloUpdateListNode)->execute($run, ['list_id' => TRELLO_LIST, 'name' => 'Done', 'archived' => 'yes'], []))->toBe(['id' => TRELLO_LIST, 'name' => 'Done', 'archived' => true])
            ->and(trelloBody('PUT /lists/'.TRELLO_LIST))->toBe(['name' => 'Done', 'closed' => true]);
    });

    it('reads board activity of one kind, newest first, with the card and list names', function () {
        fakeTrello(['GET /boards/'.TRELLO_BOARD.'/actions' => [
            ['id' => 'a2', 'type' => 'updateCard', 'date' => '2026-03-01T11:00:00.000Z', 'memberCreator' => ['fullName' => 'Ada', 'username' => 'ada'], 'data' => ['card' => ['id' => TRELLO_CARD, 'name' => 'Write the email'], 'listBefore' => ['name' => 'To do'], 'listAfter' => ['name' => 'Doing'], 'board' => ['id' => TRELLO_BOARD]]],
            ['id' => 'a1', 'type' => 'updateCard', 'date' => '2026-03-01T10:00:00.000Z', 'data' => ['card' => ['id' => TRELLO_CARD, 'name' => 'x'], 'list' => ['name' => 'To do']]],
        ]]);
        $node = new TrelloListActivityNode;

        $output = $node->execute(trelloRun(), ['board_id' => TRELLO_BOARD, 'event' => 'card_moved', 'since' => '2026-03-01T00:00:00Z'], []);

        expect($output['activity'][0])->toBe(['id' => 'a2', 'type' => 'updateCard', 'date' => '2026-03-01T11:00:00.000Z', 'member' => 'Ada', 'username' => 'ada', 'card_id' => TRELLO_CARD, 'card_name' => 'Write the email', 'board_id' => TRELLO_BOARD, 'list' => 'Doing', 'list_before' => 'To do', 'text' => null])
            ->and(trelloQuery('GET /boards/'.TRELLO_BOARD.'/actions'))->toMatchArray(['filter' => 'updateCard:idList', 'since' => '2026-03-01T00:00:00.000Z'])
            ->and(fn () => $node->execute(trelloRun(), ['board_id' => TRELLO_BOARD, 'event' => 'bogus'], []))->toThrow(InvalidArgumentException::class, 'not a kind of board activity');
    });

    it('keeps only the archivings when asked for archived cards', function () {
        fakeTrello(['GET /boards/'.TRELLO_BOARD.'/actions' => [
            ['id' => 'a1', 'type' => 'updateCard', 'data' => ['card' => ['id' => TRELLO_CARD, 'name' => 'x', 'closed' => true]]],
            ['id' => 'a2', 'type' => 'updateCard', 'data' => ['card' => ['id' => TRELLO_CARD, 'name' => 'x', 'closed' => false]]],
        ]]);

        $output = (new TrelloListActivityNode)->execute(trelloRun(), ['board_id' => TRELLO_BOARD, 'event' => 'card_archived'], []);

        expect(array_column($output['activity'], 'id'))->toBe(['a1']);
    });

    it('says what Trello said when it refuses', function () {
        fakeTrello(['POST /cards' => fn () => Http::response('invalid token', 401)]);

        expect(fn () => (new TrelloCreateCardNode)->execute(trelloRun(), ['list_id' => TRELLO_LIST, 'name' => 'x'], []))->toThrow(RuntimeException::class, 'Trello error: invalid token.');
    });
});

describe('trello dropdowns', function () {
    it('lists boards, and a board\'s lists and labels, sending the key and token', function () {
        fakeTrello([
            'GET /members/me/boards' => [['id' => TRELLO_BOARD, 'name' => 'Marketing']],
            'GET /boards/'.TRELLO_BOARD.'/lists' => [['id' => TRELLO_LIST, 'name' => 'To do']],
            'GET /boards/'.TRELLO_BOARD.'/labels' => [['id' => 'l1', 'name' => 'Urgent', 'color' => 'red'], ['id' => 'l2', 'name' => '', 'color' => 'green']],
        ]);
        $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
        $query = fn (array $config = []): NodeOptionsQuery => new NodeOptionsQuery($workspace, User::factory()->create(), $config, 'tok1', credentialData: ['app_key' => 'appkey1']);
        $source = app(TrelloOptions::class);

        expect($source->load('trello.boards', $query())->toArray()['options'])->toBe([['value' => TRELLO_BOARD, 'label' => 'Marketing']])
            ->and($source->load('trello.lists', $query(['board_id' => TRELLO_BOARD]))->toArray()['options'])->toBe([['value' => TRELLO_LIST, 'label' => 'To do']])
            ->and($source->load('trello.labels', $query(['board_id' => TRELLO_BOARD]))->toArray()['options'])->toBe([['value' => 'l1', 'label' => 'Urgent', 'description' => 'red'], ['value' => 'l2', 'label' => 'green']])
            ->and($source->load('trello.lists', $query())->toArray()['options'])->toBe([]);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'key=appkey1') && str_contains($request->url(), 'token=tok1'));
    });
});

/**
 * A published workflow whose first step is the board activity reader switched on
 * as a trigger, with its subscription synced against a faked Trello.
 *
 * @param  array<string, mixed>  $nodeConfig
 * @param  array<string, mixed>  $routes
 */
function trelloTrigger(array $nodeConfig = [], array $routes = [], bool $sync = true): Trigger
{
    Queue::fake([FireTriggerEvent::class, PollTrigger::class]);
    fakeTrello($routes);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $credential = ConnectorCredential::factory()->forWorkspace($workspace)
        ->forConnector(Connector::query()->firstWhere('key', 'trello') ?? Connector::factory()->create(['key' => 'trello']))
        ->create(['data' => ['app_key' => 'appkey1', 'api_key' => 'tok1'], 'created_by' => $owner->id]);

    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'start', 'type' => 'trello_list_activity', 'config' => ['credential_id' => $credential->id, 'activate' => true, 'board_id' => TRELLO_BOARD, ...$nodeConfig]],
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

describe('trello triggers', function () {
    it('polls board activity: a baseline first, then one run for each new action', function () {
        $trigger = trelloTrigger(sync: false);
        $poll = fn (array $ids) => fakeTrello(['GET /boards/'.TRELLO_BOARD.'/actions' => array_map(fn (string $id): array => ['id' => $id, 'type' => 'createCard', 'date' => '2026-03-01T10:00:00.000Z', 'data' => ['card' => ['id' => TRELLO_CARD, 'name' => 'x']]], $ids)]);

        $poll(['a1']);
        dispatch_sync(new PollTrigger($trigger));
        expect($trigger->events()->where('status', 'queued')->count())->toBe(0);

        $poll(['a3', 'a2', 'a1']);
        dispatch_sync(new PollTrigger($trigger->fresh()));

        expect($trigger->events()->where('status', 'queued')->get()->pluck('payload.id')->sort()->values()->all())->toBe(['a2', 'a3']);
    });
});

describe('trello instant delivery', function () {
    it('creates a webhook on the board with a token URL, and starts a poll when it arrives', function () {
        $trigger = trelloTrigger(routes: ['POST /webhooks' => ['id' => 'wh1', 'active' => true]]);
        $watch = ProviderWatch::query()->sole();

        expect($watch->resource_key)->toBe(TRELLO_BOARD)
            ->and($watch->external_id)->toBe('wh1')
            ->and($trigger->subscription->status)->toBe(SubscriptionStatus::Active)
            ->and(trelloBody('POST /webhooks'))->toBe(['callbackURL' => url('/api/hooks/apps/trello').'?token='.$watch->secret, 'idModel' => TRELLO_BOARD, 'description' => 'Flows'])
            ->and(trelloQuery('POST /webhooks'))->toMatchArray(['key' => 'appkey1', 'token' => 'tok1']);

        test()->get('/api/hooks/apps/trello?token='.$watch->secret)->assertOk();

        $body = ['action' => ['id' => 'act1', 'type' => 'createCard', 'data' => []], 'model' => ['id' => TRELLO_BOARD]];
        test()->postJson('/api/hooks/apps/trello?token='.$watch->secret, $body)->assertOk();
        test()->postJson('/api/hooks/apps/trello?token='.$watch->secret, $body)->assertOk();

        Queue::assertPushed(PollTrigger::class, 1);
    });

    it('does not register for a board that is mapped from an earlier step', function () {
        $trigger = trelloTrigger(['board_id' => '{{ trigger.board }}'], ['POST /webhooks' => ['id' => 'wh1']]);

        expect($trigger->subscription)->toBeNull()->and(ProviderWatch::query()->count())->toBe(0);
    });

    it('refuses a wrong or missing token and is closed while the switch is off', function () {
        trelloTrigger(routes: ['POST /webhooks' => ['id' => 'wh1']]);
        $watch = ProviderWatch::query()->sole();
        $body = ['action' => ['id' => 'act1', 'type' => 'createCard']];

        test()->postJson('/api/hooks/apps/trello?token=nope', $body)->assertUnauthorized();
        test()->postJson('/api/hooks/apps/trello', $body)->assertUnauthorized();
        config(['triggers.push.trello.enabled' => false]);
        test()->postJson('/api/hooks/apps/trello?token='.$watch->secret, $body)->assertNotFound();

        Queue::assertNotPushed(PollTrigger::class);
    });

    it('says so when Trello refuses the webhook, and drops the watch', function () {
        $trigger = trelloTrigger(routes: ['POST /webhooks' => fn () => Http::response('callbackURL is not reachable', 400)]);

        expect($trigger->subscription->status)->toBe(SubscriptionStatus::Failed)->and($trigger->subscription->last_error)->toContain('400')->and(ProviderWatch::query()->count())->toBe(0);
    });

    it('makes the webhook again when Trello turned it off or lost it, and removes it with the watch', function () {
        trelloTrigger(routes: ['POST /webhooks' => ['id' => 'wh1']]);
        $watch = ProviderWatch::query()->sole();
        $source = app(TrelloSource::class);

        fakeTrello(['GET /webhooks/wh1' => ['id' => 'wh1', 'active' => false], 'POST /webhooks' => ['id' => 'wh2'], 'DELETE /webhooks/wh1' => []]);
        app(WatchManager::class)->renew($source, $watch->fresh());
        expect($watch->fresh()->external_id)->toBe('wh2');
        Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE' && parse_url($request->url(), PHP_URL_PATH) === '/1/webhooks/wh1');

        fakeTrello(['GET /webhooks/wh2' => ['id' => 'wh2', 'active' => true]]);
        app(WatchManager::class)->renew($source, $watch->fresh());
        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');

        fakeTrello(['DELETE /webhooks/wh2' => []]);
        app(WatchManager::class)->remove($source, $watch->fresh());
        expect(ProviderWatch::query()->count())->toBe(0);
    });
});
