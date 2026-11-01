<?php

use App\Contracts\SupportsTriggerMode;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Notion\AbstractNotionNode;
use App\Nodes\Integrations\Notion\NotionAppendBlocksNode;
use App\Nodes\Integrations\Notion\NotionArchivePageNode;
use App\Nodes\Integrations\Notion\NotionCreateCommentNode;
use App\Nodes\Integrations\Notion\NotionCreateDatabaseItemNode;
use App\Nodes\Integrations\Notion\NotionCreateDatabaseNode;
use App\Nodes\Integrations\Notion\NotionCreatePageNode;
use App\Nodes\Integrations\Notion\NotionFindDatabaseItemsNode;
use App\Nodes\Integrations\Notion\NotionGetPageNode;
use App\Nodes\Integrations\Notion\NotionListBlockChildrenNode;
use App\Nodes\Integrations\Notion\NotionListNewCommentsNode;
use App\Nodes\Integrations\Notion\NotionListNewDatabaseItemsNode;
use App\Nodes\Integrations\Notion\NotionListNewPagesNode;
use App\Nodes\Integrations\Notion\NotionListNewUsersNode;
use App\Nodes\Integrations\Notion\NotionListUpdatedDatabaseItemsNode;
use App\Nodes\Integrations\Notion\NotionQueryDatabaseNode;
use App\Nodes\Integrations\Notion\NotionSearchNode;
use App\Nodes\Integrations\Notion\NotionUpdateBlockNode;
use App\Nodes\Integrations\Notion\NotionUpdatePageNode;
use App\Services\Workflows\NodeRegistry;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const NOTION_PAGE = '0a1b2c3d-4e5f-4a6b-8c7d-9e0f1a2b3c4d';
const NOTION_DB = '11111111-2222-4333-8444-555555555555';

/**
 * @param  array<string, mixed>  $config
 */
function notion(array $config = []): array
{
    return ['access_token' => 'secret_test', ...$config];
}

/**
 * A page as Notion returns it.
 *
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function notionPage(string $id = NOTION_PAGE, array $extra = []): array
{
    return [
        'object' => 'page',
        'id' => $id,
        'url' => "https://www.notion.so/{$id}",
        'created_time' => '2026-03-01T10:00:00.000Z',
        'last_edited_time' => '2026-03-01T11:00:00.000Z',
        'archived' => false,
        'parent' => ['type' => 'database_id', 'database_id' => NOTION_DB],
        'properties' => [
            'Name' => ['id' => 'title', 'type' => 'title', 'title' => [['plain_text' => 'Ship it']]],
            'Status' => ['id' => 's', 'type' => 'status', 'status' => ['name' => 'Done']],
            'Tags' => ['id' => 't', 'type' => 'multi_select', 'multi_select' => [['name' => 'a'], ['name' => 'b']]],
            'Due' => ['id' => 'd', 'type' => 'date', 'date' => ['start' => '2026-03-05', 'end' => null]],
            'Points' => ['id' => 'p', 'type' => 'number', 'number' => 3],
            'Done' => ['id' => 'c', 'type' => 'checkbox', 'checkbox' => true],
            'Owner' => ['id' => 'o', 'type' => 'people', 'people' => [['id' => 'u1', 'name' => 'Ada']]],
            'Score' => ['id' => 'f', 'type' => 'formula', 'formula' => ['type' => 'number', 'number' => 9]],
        ],
        ...$extra,
    ];
}

/**
 * A database schema as Notion returns it.
 *
 * @return array<string, mixed>
 */
function notionDatabase(): array
{
    return [
        'object' => 'database',
        'id' => NOTION_DB,
        'title' => [['plain_text' => 'Tasks']],
        'properties' => [
            'Name' => ['id' => 'title', 'type' => 'title', 'title' => []],
            'Status' => ['id' => 's', 'type' => 'status', 'status' => ['options' => [['name' => 'Todo'], ['name' => 'Done']]]],
            'Tags' => ['id' => 't', 'type' => 'multi_select', 'multi_select' => ['options' => []]],
            'Points' => ['id' => 'p', 'type' => 'number', 'number' => []],
            'Due' => ['id' => 'd', 'type' => 'date', 'date' => []],
            'Done' => ['id' => 'c', 'type' => 'checkbox', 'checkbox' => []],
            'Link' => ['id' => 'l', 'type' => 'url', 'url' => []],
            'Total' => ['id' => 'x', 'type' => 'formula', 'formula' => []],
        ],
    ];
}

/**
 * The JSON body of the n-th request sent to a URL ending in `$suffix`.
 *
 * @return array<string, mixed>
 */
function notionSent(string $suffix, string $method = 'POST', int $nth = 0): array
{
    $matching = Http::recorded()->filter(fn (array $pair): bool => str_ends_with($pair[0]->url(), $suffix) && $pair[0]->method() === $method)->values();

    return (array) $matching[$nth][0]->data();
}

it('registers every notion node under its own type, in the notion category', function () {
    $registry = app(NodeRegistry::class);
    $types = collect(glob(app_path('Nodes/Integrations/Notion/Notion*Node.php')))
        ->map(fn (string $file): string => 'App\\Nodes\\Integrations\\Notion\\'.basename($file, '.php'));

    expect($types)->toHaveCount(29);

    foreach ($types as $class) {
        $node = new $class;

        expect($registry->has($node->type()))->toBeTrue()
            ->and($registry->resolve($node->type()))->toBeInstanceOf($class)
            ->and($node->category())->toBe('notion')
            ->and($node->configSchema()['properties'])->toHaveKey('credential_id');
    }
});

describe('ids and links', function () {
    it('takes an id with or without dashes, or a notion link', function (string $input) {
        expect(AbstractNotionNode::normalizeId($input))->toBe(NOTION_PAGE);
    })->with([
        'dashed' => NOTION_PAGE,
        'plain' => '0a1b2c3d4e5f4a6b8c7d9e0f1a2b3c4d',
        'link' => 'https://www.notion.so/acme/Ship-it-0a1b2c3d4e5f4a6b8c7d9e0f1a2b3c4d?pvs=4',
        'upper case' => '0A1B2C3D4E5F4A6B8C7D9E0F1A2B3C4D',
    ]);

    it('refuses anything that could point the request elsewhere', function (string $input) {
        AbstractNotionNode::normalizeId($input);
    })->with(['../users', 'abc', '0a1b2c3d4e5f4a6b8c7d9e0f1a2b3c4d/../x', '', 'javascript:0a1b2c3d4e5f4a6b8c7d9e0f1a2b3c4d'])->throws(InvalidArgumentException::class);
});

describe('pages', function () {
    it('gets a page with its properties flattened to plain values', function () {
        Http::fake(['api.notion.com/v1/pages/*' => Http::response(notionPage())]);

        $output = (new NotionGetPageNode)->execute(Run::factory()->create(), notion(['page' => 'https://www.notion.so/Ship-it-0a1b2c3d4e5f4a6b8c7d9e0f1a2b3c4d']), []);

        expect($output['title'])->toBe('Ship it')
            ->and($output['parent_id'])->toBe(NOTION_DB)
            ->and($output['values'])->toBe([
                'Name' => 'Ship it', 'Status' => 'Done', 'Tags' => ['a', 'b'], 'Due' => ['start' => '2026-03-05'],
                'Points' => 3, 'Done' => true, 'Owner' => ['Ada'], 'Score' => 9,
            ])
            ->and($output['properties'])->toHaveKey('Status');

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.notion.com/v1/pages/'.NOTION_PAGE
            && $request->hasHeader('Authorization', 'Bearer secret_test')
            && $request->hasHeader('Notion-Version', '2022-06-28'));
    });

    it('creates a page under a page, with a paragraph for each line, and the rest in later calls', function () {
        Http::fake([
            'api.notion.com/v1/pages' => Http::response(notionPage('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', ['parent' => ['type' => 'page_id', 'page_id' => NOTION_PAGE]])),
            'api.notion.com/v1/blocks/*' => Http::response(['results' => [['id' => 'b1']]]),
        ]);

        $lines = implode("\n", array_map(fn (int $i): string => "Line {$i}", range(1, 130)));
        $output = (new NotionCreatePageNode)->execute(Run::factory()->create(), notion(['parent_page' => NOTION_PAGE, 'title' => 'Notes', 'content' => $lines, 'icon' => '📝']), []);

        $created = notionSent('/v1/pages');

        expect($created['parent'])->toBe(['page_id' => NOTION_PAGE])
            ->and($created['properties']['title']['title'][0]['text']['content'])->toBe('Notes')
            ->and($created['icon'])->toBe(['type' => 'emoji', 'emoji' => '📝'])
            ->and($created['children'])->toHaveCount(100)
            ->and($output['parent_type'])->toBe('page_id');

        $rest = notionSent('/children', 'PATCH');
        expect($rest['children'])->toHaveCount(30)
            ->and($rest['children'][0]['paragraph']['rich_text'][0]['text']['content'])->toBe('Line 101');
    });

    it('splits text longer than notion allows in one piece', function () {
        Http::fake(['api.notion.com/v1/pages' => Http::response(notionPage())]);

        (new NotionCreatePageNode)->execute(Run::factory()->create(), notion(['parent_page' => NOTION_PAGE, 'title' => str_repeat('x', 4500)]), []);

        expect(notionSent('/v1/pages')['properties']['title']['title'])->toHaveCount(3);
    });

    it('updates a database item from plain column values, by each column\'s type', function () {
        Http::fake([
            'api.notion.com/v1/pages/'.NOTION_PAGE => Http::sequence()->push(notionPage())->push(notionPage()),
            'api.notion.com/v1/databases/*' => Http::response(notionDatabase()),
        ]);

        (new NotionUpdatePageNode)->execute(Run::factory()->create(), notion([
            'page' => NOTION_PAGE,
            'title' => 'Renamed',
            'values' => ['Status' => 'Done', 'Tags' => 'x, y', 'Points' => '5', 'Due' => '2026-04-01', 'Done' => 'true', 'Link' => ''],
            'properties' => ['Extra' => ['rich_text' => []]],
        ]), []);

        $patch = notionSent('/v1/pages/'.NOTION_PAGE, 'PATCH')['properties'];

        expect($patch['Name']['title'][0]['text']['content'])->toBe('Renamed')
            ->and($patch['Status'])->toBe(['status' => ['name' => 'Done']])
            ->and($patch['Tags'])->toBe(['multi_select' => [['name' => 'x'], ['name' => 'y']]])
            ->and($patch['Points'])->toBe(['number' => 5])
            ->and($patch['Due'])->toBe(['date' => ['start' => '2026-04-01']])
            ->and($patch['Done'])->toBe(['checkbox' => true])
            ->and($patch['Link'])->toBe(['url' => null])
            ->and($patch['Extra'])->toBe(['rich_text' => []]);
    });

    it('retitles a plain page without needing a database', function () {
        Http::fake(['api.notion.com/v1/pages/*' => Http::sequence()->push(notionPage(extra: ['parent' => ['type' => 'workspace', 'workspace' => true]]))->push(notionPage())]);

        (new NotionUpdatePageNode)->execute(Run::factory()->create(), notion(['page' => NOTION_PAGE, 'title' => 'New title']), []);

        expect(notionSent('/v1/pages/'.NOTION_PAGE, 'PATCH')['properties']['title']['title'][0]['text']['content'])->toBe('New title');
    });

    it('names a column the database does not have, and one plain values cannot set', function (array $values, string $message) {
        Http::fake([
            'api.notion.com/v1/pages/*' => Http::response(notionPage()),
            'api.notion.com/v1/databases/*' => Http::response(notionDatabase()),
        ]);

        (new NotionUpdatePageNode)->execute(Run::factory()->create(), notion(['page' => NOTION_PAGE, 'values' => $values]), []);
    })->with([
        'unknown' => [['Nope' => 'x'], 'no column named "Nope"'],
        'formula' => [['Total' => '4'], "can't be set"],
    ])->throws(InvalidArgumentException::class);

    it('refuses an update that changes nothing', function () {
        (new NotionUpdatePageNode)->execute(Run::factory()->create(), notion(['page' => NOTION_PAGE]), []);
    })->throws(InvalidArgumentException::class, 'give a title');

    it('archives a page', function () {
        Http::fake(['api.notion.com/v1/pages/*' => Http::response(notionPage(extra: ['archived' => true]))]);

        $output = (new NotionArchivePageNode)->execute(Run::factory()->create(), notion(['page' => NOTION_PAGE]), []);

        expect($output['archived'])->toBeTrue()
            ->and(notionSent('/v1/pages/'.NOTION_PAGE, 'PATCH'))->toBe(['archived' => true]);
    });

    it('turns a notion error into a readable failure', function () {
        Http::fake(['api.notion.com/*' => Http::response(['object' => 'error', 'code' => 'object_not_found', 'message' => 'Could not find page with ID'], 404)]);

        (new NotionGetPageNode)->execute(Run::factory()->create(), notion(['page' => NOTION_PAGE]), []);
    })->throws(RuntimeException::class, 'Could not find page');
});

describe('databases', function () {
    it('adds an item with a title, column values and a body', function () {
        Http::fake([
            'api.notion.com/v1/databases/*' => Http::response(notionDatabase()),
            'api.notion.com/v1/pages' => Http::response(notionPage()),
        ]);

        (new NotionCreateDatabaseItemNode)->execute(Run::factory()->create(), notion([
            'database' => NOTION_DB, 'title' => 'Write tests', 'values' => ['Status' => 'Todo'], 'content' => "First\n\nSecond",
        ]), []);

        $body = notionSent('/v1/pages');

        expect($body['parent'])->toBe(['database_id' => NOTION_DB])
            ->and($body['properties']['Name']['title'][0]['text']['content'])->toBe('Write tests')
            ->and($body['properties']['Status'])->toBe(['status' => ['name' => 'Todo']])
            ->and($body['children'])->toHaveCount(2);
    });

    it('adds an item from raw properties without reading the database', function () {
        Http::fake(['api.notion.com/v1/pages' => Http::response(notionPage())]);

        (new NotionCreateDatabaseItemNode)->execute(Run::factory()->create(), notion([
            'database' => NOTION_DB, 'properties' => '{"Name": {"title": [{"text": {"content": "Raw"}}]}}',
        ]), []);

        expect(notionSent('/v1/pages')['properties']['Name']['title'][0]['text']['content'])->toBe('Raw');
        Http::assertSentCount(1);
    });

    it('refuses properties that are not valid json', function () {
        (new NotionCreateDatabaseItemNode)->execute(Run::factory()->create(), notion(['database' => NOTION_DB, 'properties' => '{nope']), []);
    })->throws(InvalidArgumentException::class, 'Properties must be valid JSON');

    it('queries with a filter and sorts, following the cursor up to the limit', function () {
        Http::fake(['api.notion.com/v1/databases/*/query' => Http::sequence()
            ->push(['results' => [notionPage('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa1'), notionPage('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa2')], 'has_more' => true, 'next_cursor' => 'c2'])
            ->push(['results' => [notionPage('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa3'), notionPage('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa4')], 'has_more' => true, 'next_cursor' => 'c3'])]);

        $output = (new NotionQueryDatabaseNode)->execute(Run::factory()->create(), notion([
            'database' => NOTION_DB, 'filter' => '{"property": "Status", "status": {"equals": "Done"}}', 'sorts' => [['property' => 'Due', 'direction' => 'ascending']], 'limit' => 3,
        ]), []);

        expect($output['count'])->toBe(3);

        $first = notionSent('/query');
        $second = notionSent('/query', nth: 1);

        expect($first['filter']['property'])->toBe('Status')
            ->and($first['sorts'][0]['direction'])->toBe('ascending')
            ->and($first['page_size'])->toBe(3)
            ->and($first)->not->toHaveKey('start_cursor')
            ->and($second['start_cursor'])->toBe('c2')
            ->and($second['page_size'])->toBe(1);
    });

    it('finds items by a column, building the filter from the column\'s type', function (string $column, string $value, string $match, array $expected) {
        Http::fake([
            'api.notion.com/v1/databases/*/query' => Http::response(['results' => [notionPage()]]),
            'api.notion.com/v1/databases/*' => Http::response(notionDatabase()),
        ]);

        $output = (new NotionFindDatabaseItemsNode)->execute(Run::factory()->create(), notion(['database' => NOTION_DB, 'column' => $column, 'value' => $value, 'match' => $match]), []);

        expect(notionSent('/query')['filter'])->toBe(['property' => $column, ...$expected])
            ->and($output['first']['title'])->toBe('Ship it');
    })->with([
        'title contains' => ['Name', 'ship', 'contains', ['title' => ['contains' => 'ship']]],
        'status equals' => ['Status', 'Done', 'equals', ['status' => ['equals' => 'Done']]],
        'tag' => ['Tags', 'a', 'equals', ['multi_select' => ['contains' => 'a']]],
        'number' => ['Points', '3', 'equals', ['number' => ['equals' => 3]]],
        'checkbox' => ['Done', 'yes', 'equals', ['checkbox' => ['equals' => true]]],
    ]);

    it('says so when a column cannot be searched', function () {
        Http::fake(['api.notion.com/v1/databases/*' => Http::response(notionDatabase())]);

        (new NotionFindDatabaseItemsNode)->execute(Run::factory()->create(), notion(['database' => NOTION_DB, 'column' => 'Total', 'value' => '1']), []);
    })->throws(InvalidArgumentException::class, "formula column can't be searched");

    it('creates a database with a name column added when none is given', function () {
        Http::fake(['api.notion.com/v1/databases' => Http::response(notionDatabase())]);

        $output = (new NotionCreateDatabaseNode)->execute(Run::factory()->create(), notion([
            'parent_page' => NOTION_PAGE, 'title' => 'Tasks', 'properties' => '{"Status": {"select": {}}}',
        ]), []);

        $body = notionSent('/v1/databases');

        expect(array_keys($body['properties']))->toBe(['Name', 'Status'])
            ->and($body['parent'])->toBe(['type' => 'page_id', 'page_id' => NOTION_PAGE])
            ->and($output['columns']['Status'])->toBe(['id' => 's', 'type' => 'status', 'options' => ['Todo', 'Done']]);
    });

    it('searches pages and databases', function () {
        Http::fake(['api.notion.com/v1/search' => Http::response(['results' => [notionPage(), notionDatabase()]])]);

        $output = (new NotionSearchNode)->execute(Run::factory()->create(), notion(['query' => 'ship', 'object' => 'page', 'order' => 'ascending']), []);

        $body = notionSent('/v1/search');

        expect($body)->toMatchArray(['query' => 'ship', 'filter' => ['property' => 'object', 'value' => 'page'], 'sort' => ['direction' => 'ascending', 'timestamp' => 'last_edited_time']])
            ->and($output['count'])->toBe(2)
            ->and($output['results'][1]['columns'])->toHaveKey('Status');
    });
});

describe('content and comments', function () {
    it('reads a page\'s content, with nested blocks, as blocks and as text', function () {
        Http::fake([
            'api.notion.com/v1/blocks/'.NOTION_PAGE.'/children*' => Http::response(['results' => [
                ['id' => 'b1', 'type' => 'heading_1', 'has_children' => false, 'heading_1' => ['rich_text' => [['plain_text' => 'Plan']]]],
                ['id' => 'b2', 'type' => 'toggle', 'has_children' => true, 'toggle' => ['rich_text' => [['plain_text' => 'More']]]],
                ['id' => 'b3', 'type' => 'to_do', 'has_children' => false, 'to_do' => ['rich_text' => [['plain_text' => 'Ship']], 'checked' => true]],
            ]]),
            'api.notion.com/v1/blocks/b2/children*' => Http::response(['results' => [
                ['id' => 'b4', 'type' => 'bulleted_list_item', 'has_children' => false, 'bulleted_list_item' => ['rich_text' => [['plain_text' => 'Inside']]]],
            ]]),
        ]);

        $flat = (new NotionListBlockChildrenNode)->execute(Run::factory()->create(), notion(['page' => NOTION_PAGE]), []);
        $deep = (new NotionListBlockChildrenNode)->execute(Run::factory()->create(), notion(['page' => NOTION_PAGE, 'include_nested' => true]), []);

        expect($flat['count'])->toBe(3)
            ->and($deep['count'])->toBe(4)
            ->and($deep['text'])->toBe("# Plan\nMore\n  - Inside\n- [x] Ship");
    });

    it('appends lines as blocks of a type, after a given block', function () {
        Http::fake(['api.notion.com/v1/blocks/*' => Http::response(['results' => [['id' => 'new']]])]);

        $output = (new NotionAppendBlocksNode)->execute(Run::factory()->create(), notion([
            'page' => NOTION_PAGE, 'content' => "One\nTwo", 'block_type' => 'to_do', 'after' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
        ]), []);

        $body = notionSent('/children', 'PATCH');

        expect($output['added'])->toBe(2)
            ->and($body['after'])->toBe('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb')
            ->and($body['children'][1]['to_do'])->toBe(['rich_text' => [['type' => 'text', 'text' => ['content' => 'Two']]], 'checked' => false]);
    });

    it('updates a block\'s text using its own type, and a to-do\'s state', function () {
        Http::fake(['api.notion.com/v1/blocks/*' => Http::response(['id' => 'b3', 'type' => 'to_do', 'to_do' => ['rich_text' => [['plain_text' => 'Done now']], 'checked' => true]])]);

        $output = (new NotionUpdateBlockNode)->execute(Run::factory()->create(), notion(['block' => NOTION_PAGE, 'text' => 'Done now', 'checked' => 'checked']), []);

        expect($output['checked'])->toBeTrue()
            ->and(notionSent('/v1/blocks/'.NOTION_PAGE, 'PATCH'))->toBe(['to_do' => ['rich_text' => [['type' => 'text', 'text' => ['content' => 'Done now']]], 'checked' => true]]);
    });

    it('comments on a page and replies in a discussion', function () {
        Http::fake(['api.notion.com/v1/comments' => Http::response(['id' => 'c1', 'discussion_id' => 'd1', 'rich_text' => [['plain_text' => 'Hi']], 'parent' => ['type' => 'page_id', 'page_id' => NOTION_PAGE]])]);

        $output = (new NotionCreateCommentNode)->execute(Run::factory()->create(), notion(['page' => NOTION_PAGE, 'text' => 'Hi']), []);
        (new NotionCreateCommentNode)->execute(Run::factory()->create(), notion(['discussion_id' => '22222222-2222-4222-8222-222222222222', 'text' => 'Reply']), []);

        expect($output['text'])->toBe('Hi')
            ->and(notionSent('/v1/comments')['parent'])->toBe(['page_id' => NOTION_PAGE])
            ->and(notionSent('/v1/comments', nth: 1))->toHaveKey('discussion_id')->not->toHaveKey('parent');
    });
});

describe('as triggers', function () {
    it('lists new database items newest first, by creation time', function () {
        Http::fake(['api.notion.com/v1/databases/*/query' => Http::response(['results' => [notionPage()]])]);
        $node = new NotionListNewDatabaseItemsNode;

        $output = $node->execute(Run::factory()->create(), notion(['database' => NOTION_DB]), []);

        expect($node)->toBeInstanceOf(SupportsTriggerMode::class)
            ->and($node->triggerListsNewestFirst())->toBeTrue()
            ->and($node->triggerItems($output, []))->toHaveCount(1)
            ->and($node->triggerItemId($output['items'][0]))->toBe(NOTION_PAGE)
            ->and(notionSent('/query')['sorts'])->toBe([['timestamp' => 'created_time', 'direction' => 'descending']]);
    });

    it('treats each edit of an item as a new event', function () {
        $node = new NotionListUpdatedDatabaseItemsNode;
        $item = ['id' => NOTION_PAGE, 'last_edited_time' => '2026-03-01T11:00:00.000Z'];

        expect($node->triggerItemId($item))->toBe(NOTION_PAGE.':2026-03-01T11:00:00.000Z')
            ->and($node->triggerItemId([...$item, 'last_edited_time' => '2026-03-01T12:00:00.000Z']))->not->toBe($node->triggerItemId($item));
    });

    it('only calls a page new when it was made lately, and leaves out database items unless asked', function () {
        $this->travelTo('2026-03-10 12:00:00');
        $node = new NotionListNewPagesNode;
        $output = ['pages' => [
            ['id' => 'fresh', 'created_time' => '2026-03-10T11:00:00.000Z', 'parent_type' => 'page_id'],
            ['id' => 'old', 'created_time' => '2025-01-01T00:00:00.000Z', 'parent_type' => 'page_id'],
            ['id' => 'row', 'created_time' => '2026-03-10T11:30:00.000Z', 'parent_type' => 'database_id'],
        ]];

        expect(array_column($node->triggerItems($output, []), 'id'))->toBe(['fresh'])
            ->and(array_column($node->triggerItems($output, ['include_database_items' => true]), 'id'))->toBe(['fresh', 'row']);
    });

    it('reads the newest comments and members from the end of their lists', function () {
        config(['triggers.poll_max_items' => 2]);
        $comments = ['comments' => [['id' => '1'], ['id' => '2'], ['id' => '3']]];
        $users = ['users' => [['id' => 'a'], ['id' => 'b'], ['id' => 'c']]];

        expect(array_column((new NotionListNewCommentsNode)->triggerItems($comments, []), 'id'))->toBe(['2', '3'])
            ->and(array_column((new NotionListNewUsersNode)->triggerItems($users, []), 'id'))->toBe(['b', 'c']);
    });
});
