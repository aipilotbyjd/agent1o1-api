<?php

use App\Contracts\KeepsTriggerState;
use App\Contracts\SupportsTriggerMode;
use App\Enums\Triggers\TriggerEventStatus;
use App\Jobs\Triggers\FireTriggerEvent;
use App\Jobs\Triggers\PollTrigger;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Runs\Run;
use App\Models\Triggers\Trigger;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Nodes\Integrations\GoogleDrive\GoogleDriveListTrashedFilesNode;
use App\Nodes\Integrations\GoogleSheets\GoogleSheetsListDeletedRowsNode;
use App\Nodes\Integrations\GoogleTasks\GoogleTasksListDueSoonTasksNode;
use App\Nodes\Integrations\Notion\NotionListDeletedDatabaseItemsNode;
use App\Services\Triggers\PresenceTracker;
use App\Services\Triggers\TriggerSampler;
use App\Services\Workflows\NodeRegistry;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

const REMOVAL_NOTION_DB = '11111111-2222-4333-8444-555555555555';

/**
 * A published workflow whose first step is a reader node switched on as a
 * trigger, polled by hand (no push).
 *
 * @param  array<string, mixed>  $nodeConfig
 */
function stateReaderTrigger(string $category, string $nodeType, array $nodeConfig): Trigger
{
    Queue::fake([FireTriggerEvent::class]);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $credential = ConnectorCredential::factory()->forWorkspace($workspace)
        ->forConnector(Connector::query()->firstWhere('key', $category) ?? Connector::factory()->create(['key' => $category]))
        ->create(['data' => ['access_token' => 'token'], 'created_by' => $owner->id]);

    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'start', 'type' => $nodeType, 'config' => ['credential_id' => $credential->id, 'activate' => true, ...$nodeConfig]],
            ['key' => 'next', 'type' => 'transform', 'config' => ['mapping' => []]],
        ],
        'edges' => [['from' => 'start', 'to' => 'next']],
    ]);
    $workflow->fresh()->publishVersion(publisher: $owner);

    return Trigger::query()->where('target_id', $workflow->id)->where('trigger_node_key', 'start')->firstOrFail();
}

/**
 * A Sheets answer: a header row, then one row per id.
 *
 * @param  list<string>  $ids
 * @return array<string, mixed>
 */
function removalSheet(array $ids): array
{
    return ['range' => 'Sheet1!A1:B'.(count($ids) + 1), 'values' => [['Order ID', 'Name'], ...array_map(fn (string $id): array => [$id, "Customer {$id}"], $ids)]];
}

/**
 * A Notion database query answer with one page per id.
 *
 * @param  array<string, string>  $titles  id => title
 * @return array<string, mixed>
 */
function removalNotionQuery(array $titles): array
{
    return ['results' => array_map(fn (string $id, string $title): array => [
        'object' => 'page',
        'id' => $id,
        'archived' => false,
        'properties' => ['Name' => ['id' => 'title', 'type' => 'title', 'title' => [['plain_text' => $title]]]],
    ], array_keys($titles), $titles), 'has_more' => false];
}

/**
 * What the due-soon node returns for a task list, at the current (test) time.
 *
 * @param  list<array<string, mixed>>  $tasks
 * @param  array<string, mixed>  $config
 * @return array<string, mixed>
 */
function dueSoonRead(array $tasks, array $config = []): array
{
    Http::fake(['tasks.googleapis.com/*' => Http::response(['items' => $tasks])]);

    return (new GoogleTasksListDueSoonTasksNode)->execute(Run::factory()->create(), ['access_token' => 't', ...$config], []);
}

describe('presence tracker', function () {
    it('takes a baseline first and reports nothing', function () {
        $result = PresenceTracker::track(['a' => 'A', 'b' => 'B'], null);

        expect($result['items'])->toBe([])
            ->and($result['state']['present'])->toBe(['a' => 'A', 'b' => 'B']);
    });

    it('reports what went missing, once', function () {
        $first = PresenceTracker::track(['a' => 'Alpha', 'b' => 'Beta'], null, Carbon::parse('2026-03-01 10:00:00'));
        $second = PresenceTracker::track(['a' => 'Alpha'], $first['state'], Carbon::parse('2026-03-01 10:05:00'));
        $third = PresenceTracker::track(['a' => 'Alpha'], $second['state'], Carbon::parse('2026-03-01 10:10:00'));

        expect($second['items'])->toHaveCount(1)
            ->and($second['items'][0])->toMatchArray(['id' => 'b', 'label' => 'Beta'])
            ->and($second['items'][0]['last_seen_at'])->toBe(Carbon::parse('2026-03-01 10:00:00')->toIso8601String())
            ->and($third['items'])->toBe([]);
    });

    it('gives the same removal the same delivery id however often it is repeated', function () {
        $state = PresenceTracker::track(['a' => 'A', 'b' => 'B'], null)['state'];

        $one = PresenceTracker::track(['a' => 'A'], $state)['items'][0];
        $again = PresenceTracker::track(['a' => 'A'], $state)['items'][0];

        expect(PresenceTracker::deliveryId($one))->toBe(PresenceTracker::deliveryId($again));
    });

    it('treats an item that comes back and goes again as a new removal', function () {
        $base = PresenceTracker::track(['a' => 'A'], null, Carbon::parse('2026-03-01 10:00:00'));
        $gone = PresenceTracker::track([], $base['state'], Carbon::parse('2026-03-01 10:05:00'));
        $back = PresenceTracker::track(['a' => 'A'], $gone['state'], Carbon::parse('2026-03-01 10:10:00'));
        $goneAgain = PresenceTracker::track([], $back['state'], Carbon::parse('2026-03-01 10:15:00'));

        expect(PresenceTracker::deliveryId($goneAgain['items'][0]))->not->toBe(PresenceTracker::deliveryId($gone['items'][0]));
    });

    it('hands over a few at a time and keeps the rest for the next poll', function () {
        config(['triggers.poll_max_items' => 2]);
        $state = PresenceTracker::track(['a' => 'A', 'b' => 'B', 'c' => 'C', 'd' => 'D', 'e' => 'E'], null)['state'];

        $first = PresenceTracker::track([], $state);
        $second = PresenceTracker::track([], $first['state']);
        $third = PresenceTracker::track([], $second['state']);

        expect(array_column($first['items'], 'id'))->toBe(['a', 'b'])
            ->and(array_column($second['items'], 'id'))->toBe(['c', 'd'])
            ->and(array_column($third['items'], 'id'))->toBe(['e']);
    });

    it('refuses a list too long to keep track of', function () {
        config(['triggers.removal.max_tracked' => 3]);

        PresenceTracker::track(['a' => 'A', 'b' => 'B', 'c' => 'C', 'd' => 'D'], null);
    })->throws(RuntimeException::class, 'more than 3 items');

    it('shortens a long label', function () {
        $state = PresenceTracker::track(['a' => str_repeat('x', 300)], null)['state'];

        expect(mb_strlen($state['present']['a']))->toBe(100);
    });
});

describe('google sheets: row deleted', function () {
    it('reports a deleted row once, found by its id column', function () {
        $trigger = stateReaderTrigger('google_sheets', 'google_sheets_list_deleted_rows', ['spreadsheet_id' => 'sheet1', 'range' => 'Sheet1', 'id_column' => 'Order ID']);
        Http::fake(['sheets.googleapis.com/*' => Http::sequence()
            ->push(removalSheet(['A1', 'A2', 'A3']))
            ->push(removalSheet(['A1', 'A3']))
            ->push(removalSheet(['A1', 'A3']))]);

        dispatch_sync(new PollTrigger($trigger));
        expect($trigger->events()->count())->toBe(0)
            ->and(array_keys($trigger->fresh()->poll_cursor['state']['present']))->toBe(['A1', 'A2', 'A3']);

        dispatch_sync(new PollTrigger($trigger));
        $event = $trigger->events()->sole();

        expect($event->status)->toBe(TriggerEventStatus::Queued)
            ->and($event->payload)->toMatchArray(['id' => 'A2', 'label' => 'A2', 'id_column' => 'Order ID', 'spreadsheet_id' => 'sheet1']);

        dispatch_sync(new PollTrigger($trigger));
        expect($trigger->events()->count())->toBe(1);
    });

    it('starts a new baseline when the trigger\'s settings change, rather than reporting every old row gone', function () {
        $trigger = stateReaderTrigger('google_sheets', 'google_sheets_list_deleted_rows', ['spreadsheet_id' => 'sheet1', 'range' => 'Sheet1', 'id_column' => 'Order ID']);
        Http::fake(['sheets.googleapis.com/*' => Http::sequence()
            ->push(removalSheet(['A1', 'A2']))
            ->push(['range' => 'Sheet1!A1:B3', 'values' => [['Order ID', 'Email'], ['A1', 'a@x.com'], ['A2', 'b@x.com']]])]);

        dispatch_sync(new PollTrigger($trigger));

        // Someone points the trigger at the Email column instead.
        $config = $trigger->fresh()->config;
        $config['node_config']['id_column'] = 'Email';
        $trigger->forceFill(['config' => $config])->save();

        dispatch_sync(new PollTrigger($trigger));

        expect($trigger->events()->count())->toBe(0)
            ->and(array_keys($trigger->fresh()->poll_cursor['state']['present']))->toBe(['a@x.com', 'b@x.com']);
    });

    it('does not mistake a shifted row for a deleted one', function () {
        $node = new GoogleSheetsListDeletedRowsNode;
        $config = ['access_token' => 't', 'spreadsheet_id' => 's', 'range' => 'Sheet1', 'id_column' => 'Order ID'];
        Http::fake(['sheets.googleapis.com/*' => Http::sequence()->push(removalSheet(['A1', 'A2', 'A3']))->push(removalSheet(['A2', 'A3']))]);

        $baseline = $node->pollSince(Run::factory()->create(), $config, null);
        $after = $node->pollSince(Run::factory()->create(), $config, $baseline['state']);

        expect(array_column($after['items'], 'id'))->toBe(['A1']);
    });

    it('says when the id column is not in the sheet instead of reporting every row deleted', function () {
        $node = new GoogleSheetsListDeletedRowsNode;
        Http::fake(['sheets.googleapis.com/*' => Http::response(removalSheet(['A1']))]);

        $node->pollSince(Run::factory()->create(), ['access_token' => 't', 'spreadsheet_id' => 's', 'range' => 'Sheet1', 'id_column' => 'Missing'], null);
    })->throws(InvalidArgumentException::class, 'no column named "Missing"');

    it('ignores rows with no id', function () {
        $node = new GoogleSheetsListDeletedRowsNode;
        Http::fake(['sheets.googleapis.com/*' => Http::response(['range' => 'Sheet1!A1:B3', 'values' => [['Order ID', 'Name'], ['', 'Blank'], ['A1', 'Ann']]])]);

        $result = $node->pollSince(Run::factory()->create(), ['access_token' => 't', 'spreadsheet_id' => 's', 'range' => 'Sheet1', 'id_column' => 'Order ID'], null);

        expect(array_keys($result['state']['present']))->toBe(['A1']);
    });

    it('shows an example removal as the sample, without moving its state', function () {
        $trigger = stateReaderTrigger('google_sheets', 'google_sheets_list_deleted_rows', ['spreadsheet_id' => 'sheet1', 'range' => 'Sheet1', 'id_column' => 'Order ID']);
        Http::fake();

        $sample = app(TriggerSampler::class)->sample($trigger);

        expect($sample)->toHaveCount(1)
            ->and($sample[0]['payload'])->toHaveKeys(['id', 'label', 'last_seen_at', 'removed_at'])
            ->and($trigger->fresh()->poll_cursor)->toBeNull();
        Http::assertNothingSent();
    });
});

describe('notion: database item deleted', function () {
    it('reports an item that is no longer in the database', function () {
        $node = new NotionListDeletedDatabaseItemsNode;
        $config = ['access_token' => 'secret', 'database' => REMOVAL_NOTION_DB];
        Http::fake(['api.notion.com/v1/databases/*' => Http::sequence()
            ->push(removalNotionQuery(['p1' => 'Ship it', 'p2' => 'Write docs']))
            ->push(removalNotionQuery(['p1' => 'Ship it']))]);

        $baseline = $node->pollSince(Run::factory()->create(), $config, null);
        $after = $node->pollSince(Run::factory()->create(), $config, $baseline['state']);

        expect($baseline['items'])->toBe([])
            ->and($after['items'])->toHaveCount(1)
            ->and($after['items'][0])->toMatchArray(['id' => 'p2', 'label' => 'Write docs', 'database_id' => REMOVAL_NOTION_DB]);
    });

    it('counts an archived item as gone', function () {
        $node = new NotionListDeletedDatabaseItemsNode;
        $config = ['access_token' => 'secret', 'database' => REMOVAL_NOTION_DB];
        $archived = removalNotionQuery(['p1' => 'Ship it']);
        $archived['results'][0]['archived'] = true;
        Http::fake(['api.notion.com/v1/databases/*' => Http::sequence()->push(removalNotionQuery(['p1' => 'Ship it']))->push($archived)]);

        $baseline = $node->pollSince(Run::factory()->create(), $config, null);
        $after = $node->pollSince(Run::factory()->create(), $config, $baseline['state']);

        expect(array_column($after['items'], 'id'))->toBe(['p1']);
    });
});

describe('google drive: file trashed', function () {
    it('reads the change feed from where it left off and reports trashed and deleted files', function () {
        $trigger = stateReaderTrigger('google_drive', 'google_drive_list_trashed_files', []);
        Http::fake([
            'www.googleapis.com/drive/v3/changes/startPageToken*' => Http::response(['startPageToken' => 'tok1']),
            'www.googleapis.com/drive/v3/changes*' => Http::response([
                'newStartPageToken' => 'tok2',
                'changes' => [
                    ['fileId' => 'f1', 'removed' => false, 'time' => '2026-03-01T10:00:00.000Z', 'file' => ['id' => 'f1', 'name' => 'a.pdf', 'mimeType' => 'application/pdf', 'trashed' => true, 'parents' => ['p1']]],
                    ['fileId' => 'f2', 'removed' => false, 'time' => '2026-03-01T10:01:00.000Z', 'file' => ['id' => 'f2', 'name' => 'b.pdf', 'trashed' => false]],
                    ['fileId' => 'f3', 'removed' => true, 'time' => '2026-03-01T10:02:00.000Z'],
                ],
            ]),
        ]);

        dispatch_sync(new PollTrigger($trigger));
        expect($trigger->events()->count())->toBe(0)
            ->and($trigger->fresh()->poll_cursor['state']['page_token'])->toBe('tok1');

        dispatch_sync(new PollTrigger($trigger));

        $payloads = $trigger->events()->get()->keyBy(fn ($event): string => $event->payload['id'])->map->payload;

        expect($payloads->keys()->sort()->values()->all())->toBe(['f1', 'f3'])
            ->and($payloads['f1'])->toMatchArray(['name' => 'a.pdf', 'permanently_deleted' => false, 'trashed_at' => '2026-03-01T10:00:00.000Z'])
            ->and($payloads['f3'])->toMatchArray(['permanently_deleted' => true])
            ->and($trigger->fresh()->poll_cursor['state']['page_token'])->toBe('tok2');
        Http::assertSent(fn ($request) => str_contains($request->url(), '/changes?') && $request['pageToken'] === 'tok1' && $request['includeRemoved'] === 'true');
    });

    it('reports a file once per change, so trash, restore, trash again is two events', function () {
        $node = new GoogleDriveListTrashedFilesNode;

        $first = $node->triggerItemId(['id' => 'f1', 'trashed_at' => '2026-03-01T10:00:00.000Z']);
        $second = $node->triggerItemId(['id' => 'f1', 'trashed_at' => '2026-03-02T10:00:00.000Z']);

        expect($first)->not->toBe($second)->and($first)->toStartWith('f1:');
    });

    it('keeps to a folder and a type, and skips deleted files it cannot check', function () {
        $node = new GoogleDriveListTrashedFilesNode;
        Http::fake(['www.googleapis.com/drive/v3/changes*' => Http::response([
            'newStartPageToken' => 'tok2',
            'changes' => [
                ['fileId' => 'f1', 'time' => 't', 'file' => ['id' => 'f1', 'mimeType' => 'application/pdf', 'trashed' => true, 'parents' => ['wanted']]],
                ['fileId' => 'f2', 'time' => 't', 'file' => ['id' => 'f2', 'mimeType' => 'application/pdf', 'trashed' => true, 'parents' => ['other']]],
                ['fileId' => 'f3', 'time' => 't', 'file' => ['id' => 'f3', 'mimeType' => 'image/png', 'trashed' => true, 'parents' => ['wanted']]],
                ['fileId' => 'f4', 'time' => 't', 'removed' => true],
            ],
        ])]);

        $result = $node->pollSince(Run::factory()->create(), ['access_token' => 't', 'folder_id' => 'wanted', 'file_type' => 'application/pdf'], ['page_token' => 'tok1']);

        expect(array_column($result['items'], 'id'))->toBe(['f1']);
    });

    it('carries on from the next page when a busy drive has more', function () {
        config(['triggers.poll_max_items' => 1]);
        $node = new GoogleDriveListTrashedFilesNode;
        Http::fake(['www.googleapis.com/drive/v3/changes*' => Http::response([
            'nextPageToken' => 'tok-next',
            'changes' => [['fileId' => 'f1', 'time' => 't', 'file' => ['id' => 'f1', 'trashed' => true]]],
        ])]);

        $result = $node->pollSince(Run::factory()->create(), ['access_token' => 't'], ['page_token' => 'tok1']);

        expect($result['items'])->toHaveCount(1)
            ->and($result['state'])->toBe(['page_token' => 'tok-next']);
    });

    it('starts again from now when Drive no longer knows the token', function () {
        $node = new GoogleDriveListTrashedFilesNode;
        Http::fake([
            'www.googleapis.com/drive/v3/changes/startPageToken*' => Http::response(['startPageToken' => 'fresh']),
            'www.googleapis.com/drive/v3/changes*' => Http::response(['error' => ['message' => 'Invalid page token.']], 400),
        ]);

        $result = $node->pollSince(Run::factory()->create(), ['access_token' => 't'], ['page_token' => 'expired']);

        expect($result)->toBe(['items' => [], 'state' => ['page_token' => 'fresh']]);
    });

    it('lists what is in the trash when read as a plain node', function () {
        Http::fake(['www.googleapis.com/drive/v3/files*' => Http::response(['files' => [['id' => 'f1', 'trashed' => true]]])]);

        $output = (new GoogleDriveListTrashedFilesNode)->execute(Run::factory()->create(), ['access_token' => 't', 'folder_id' => 'p1'], []);

        expect($output['files'])->toHaveCount(1);
        Http::assertSent(fn ($request) => str_contains($request['q'], 'trashed = true') && str_contains($request['q'], "'p1' in parents") && ! str_contains($request['q'], 'trashed = false'));
    });
});

describe('google tasks: task due soon', function () {
    afterEach(fn () => Carbon::setTestNow());

    it('lists open tasks from the lead time before their due day until that day ends', function () {
        Carbon::setTestNow('2026-03-04 12:00:00');

        $output = dueSoonRead([
            ['id' => 'today', 'status' => 'needsAction', 'due' => '2026-03-04T00:00:00.000Z'],
            ['id' => 'tomorrow', 'status' => 'needsAction', 'due' => '2026-03-05T00:00:00.000Z'],
            ['id' => 'next-week', 'status' => 'needsAction', 'due' => '2026-03-11T00:00:00.000Z'],
            ['id' => 'overdue', 'status' => 'needsAction', 'due' => '2026-03-02T00:00:00.000Z'],
            ['id' => 'done', 'status' => 'completed', 'due' => '2026-03-04T00:00:00.000Z'],
            ['id' => 'no-date', 'status' => 'needsAction'],
        ], ['hours_before' => 24]);

        expect(array_column($output['items'], 'id'))->toBe(['today', 'tomorrow'])
            ->and(array_column($output['later'], 'id'))->toBe(['next-week']);
    });

    it('counts the lead from the start of the due day', function () {
        Carbon::setTestNow('2026-03-04 12:00:00');

        // 6 hours before: nothing is within 6 hours of the start of the 5th yet.
        expect(dueSoonRead([['id' => 't', 'status' => 'needsAction', 'due' => '2026-03-05T00:00:00.000Z']], ['hours_before' => 6])['items'])->toBe([]);

        Carbon::setTestNow('2026-03-04 18:00:00');

        expect(array_column(dueSoonRead([['id' => 't', 'status' => 'needsAction', 'due' => '2026-03-05T00:00:00.000Z']], ['hours_before' => 6])['items'], 'id'))->toBe(['t']);
    });

    it('wakes the poll when the next task comes within its lead time', function () {
        Carbon::setTestNow('2026-03-04 12:00:00');
        $node = new GoogleTasksListDueSoonTasksNode;

        $output = dueSoonRead([
            ['id' => 'far', 'status' => 'needsAction', 'due' => '2026-03-11T00:00:00.000Z'],
            ['id' => 'sooner', 'status' => 'needsAction', 'due' => '2026-03-08T00:00:00.000Z'],
        ], ['hours_before' => 24]);

        expect($node->nextTriggerPollAt($output, ['hours_before' => 24])->toIso8601String())->toBe('2026-03-07T00:00:00+00:00')
            ->and($node->nextTriggerPollAt(['later' => []], []))->toBeNull();
    });

    it('reports a task once per due date, and again if the date moves', function () {
        $node = new GoogleTasksListDueSoonTasksNode;

        expect($node->triggerItemId(['id' => 't1', 'due' => '2026-03-05T00:00:00.000Z']))->toBe('t1:due:2026-03-05')
            ->and($node->triggerItemId(['id' => 't1', 'due' => '2026-03-09T00:00:00.000Z']))->not->toBe('t1:due:2026-03-05');
    });

    it('tells the flow how long until the due day starts', function () {
        Carbon::setTestNow('2026-03-04 18:00:00');

        $payload = (new GoogleTasksListDueSoonTasksNode)->expandTriggerItem(Run::factory()->create(), [], ['id' => 't', 'due' => '2026-03-05T00:00:00.000Z']);

        expect($payload['hours_until_due'])->toBe(6);
    });

    it('fires for a task that comes due after the trigger was switched on', function () {
        Carbon::setTestNow('2026-03-01 09:00:00');
        $trigger = stateReaderTrigger('google_tasks', 'google_tasks_list_due_soon_tasks', ['hours_before' => 24]);
        Http::fake(['tasks.googleapis.com/*' => Http::sequence()
            ->push(['items' => [['id' => 'old', 'status' => 'needsAction', 'due' => '2026-03-02T00:00:00.000Z']]])
            ->push(['items' => [['id' => 'old', 'status' => 'needsAction', 'due' => '2026-03-02T00:00:00.000Z'], ['id' => 'new', 'status' => 'needsAction', 'due' => '2026-03-02T00:00:00.000Z']]])]);

        // Already due soon when switched on: the baseline, not a run.
        dispatch_sync(new PollTrigger($trigger));
        expect($trigger->events()->where('status', TriggerEventStatus::Queued)->count())->toBe(0);

        dispatch_sync(new PollTrigger($trigger));

        expect($trigger->events()->where('status', TriggerEventStatus::Queued)->sole()->payload['id'])->toBe('new');
    });
});

it('registers the new triggers as app triggers with their own names', function () {
    $registry = app(NodeRegistry::class);

    $expected = [
        'google_sheets_list_deleted_rows' => ['Google Sheets: Row deleted', true],
        'notion_list_deleted_database_items' => ['Notion: Database item deleted', true],
        'google_drive_list_trashed_files' => ['Google Drive: File trashed', true],
        'google_tasks_list_due_soon_tasks' => ['Google Tasks: Task due soon', false],
    ];

    foreach ($expected as $type => [$name, $keepsState]) {
        $node = $registry->resolve($type);

        expect($node)->toBeInstanceOf(SupportsTriggerMode::class)
            ->and($node->triggerName())->toBe($name)
            ->and($node instanceof KeepsTriggerState)->toBe($keepsState);
    }

    $names = collect($registry->triggerCatalog())->pluck('name');

    expect($names)->toContain('Google Sheets: Row deleted', 'Notion: Database item deleted', 'Google Drive: File trashed', 'Google Tasks: Task due soon');
});
