<?php

use App\Enums\NodeRunStatus;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceDataEntry;
use App\Nodes\DataTransform\DataStoreNode;
use App\Services\Web\HtmlDocument;
use App\Services\Web\SelectorToXPath;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

beforeEach(function () {
    // Nothing here may reach an outside service for real: an unfaked call fails the test.
    Http::preventStrayRequests();
});

function dataWorkspace(): Workspace
{
    return app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
}

/* ---------------------------------------------------------------------- csv */

it('parses CSV text into rows keyed by the headings', function () {
    $nodeRun = runNodeStep('csv_parse', ['text' => "name,email\nAda,ada@x.com\n\"Lovelace, A\",a@x.com\n,\n"]);

    expect($nodeRun->output)->toMatchArray([
        'headers' => ['name', 'email'],
        'row_count' => 2,
        'rows' => [['name' => 'Ada', 'email' => 'ada@x.com'], ['name' => 'Lovelace, A', 'email' => 'a@x.com']],
    ]);
});

it('detects a semicolon or tab separator, copes with a byte order mark and names blank and repeated headings', function () {
    $semicolon = runNodeStep('csv_parse', ['text' => "\xEF\xBB\xBFa;b;;a\n1;2;3;4"]);
    $tab = runNodeStep('csv_parse', ['text' => "x\ty\n1\t2"]);

    expect($semicolon->output['headers'])->toBe(['a', 'b', 'column_3', 'a_2'])
        ->and($semicolon->output['rows'][0])->toBe(['a' => '1', 'b' => '2', 'column_3' => '3', 'a_2' => '4'])
        ->and($tab->output['rows'])->toBe([['x' => '1', 'y' => '2']]);
});

it('numbers the columns when there is no heading row and pads short rows', function () {
    $nodeRun = runNodeStep('csv_parse', ['text' => "1,2,3\n4,5", 'has_header' => false]);

    expect($nodeRun->output['rows'])->toBe([['column_1' => '1', 'column_2' => '2', 'column_3' => '3'], ['column_1' => '4', 'column_2' => '5', 'column_3' => '']]);
});

it('reads an Excel file, the named sheet and the first by default', function () {
    $book = new Spreadsheet;
    $book->getActiveSheet()->setTitle('People')->fromArray([['name', 'age'], ['Ada', 36]]);
    $book->createSheet()->setTitle('Other')->fromArray([['x'], ['1']]);
    $path = tempnam(sys_get_temp_dir(), 'x');
    (new Xlsx($book))->save($path);
    $base64 = base64_encode(file_get_contents($path));
    unlink($path);
    $mime = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    $first = runNodeStep('csv_parse', ['file_base64' => $base64, 'mime_type' => $mime]);
    $named = runNodeStep('csv_parse', ['file_base64' => $base64, 'mime_type' => $mime, 'sheet' => 'Other']);
    $missing = runNodeStep('csv_parse', ['file_base64' => $base64, 'mime_type' => $mime, 'sheet' => 'Nope']);

    expect($first->output['rows'])->toBe([['name' => 'Ada', 'age' => 36]])
        ->and($named->output['rows'])->toBe([['x' => 1]])
        ->and($missing->status)->toBe(NodeRunStatus::Failed);
});

it('needs a source for the CSV', function () {
    expect(fn () => executeNode('csv_parse', []))->toThrow(InvalidArgumentException::class, 'Give the file');
});

/* -------------------------------------------------------------- table_format */

it('formats rows as a CSV file with quoting, and protects against spreadsheet formulas', function () {
    $nodeRun = runNodeStep('table_format', [
        'rows' => [['name' => 'Ada, L', 'note' => '=HYPERLINK("x")', 'n' => -5], ['name' => 'Bob', 'note' => 'ok', 'n' => 3]],
        'format' => 'csv',
        'filename' => 'people.csv',
    ]);

    expect($nodeRun->output['text'])->toBe("name,note,n\n\"Ada, L\",\"'=HYPERLINK(\"\"x\"\")\",-5\nBob,ok,3\n")
        ->and($nodeRun->output['file'])->toMatchArray(['filename' => 'people.csv', 'mime_type' => 'text/csv'])
        ->and(base64_decode($nodeRun->output['file']['content_base64']))->toBe($nodeRun->output['text'])
        ->and($nodeRun->output['row_count'])->toBe(2);
});

it('can leave formulas alone, choose columns and write a Markdown table', function () {
    $markdown = runNodeStep('table_format', ['rows' => [['a' => 'x|y', 'b' => 2, 'c' => 'z']], 'format' => 'markdown', 'columns' => ['b', 'a']]);
    $raw = runNodeStep('table_format', ['rows' => [['v' => '=1+1']], 'format' => 'csv', 'protect_formulas' => false]);

    expect($markdown->output['text'])->toBe("| b | a |\n| --- | --- |\n| 2 | x\\|y |")
        ->and($raw->output['text'])->toBe("v\n=1+1\n");
});

it('escapes HTML in an HTML table', function () {
    $nodeRun = runNodeStep('table_format', ['rows' => [['name' => '<script>alert(1)</script>']], 'format' => 'html']);

    expect($nodeRun->output['text'])->toBe('<table><thead><tr><th>name</th></tr></thead><tbody><tr><td>&lt;script&gt;alert(1)&lt;/script&gt;</td></tr></tbody></table>');
});

it('refuses rows that are not a list', function () {
    expect(fn () => executeNode('table_format', ['rows' => ['a' => 1], 'format' => 'csv']))->toThrow(InvalidArgumentException::class, 'list of objects')
        ->and(fn () => executeNode('table_format', ['rows' => 'nope', 'format' => 'csv']))->toThrow(InvalidArgumentException::class);
});

/* -------------------------------------------------------------------- table */

$people = [
    ['name' => 'Ada', 'team' => 'eng', 'pay' => 10],
    ['name' => 'Bob', 'team' => 'eng', 'pay' => 20],
    ['name' => 'Cy', 'team' => 'ops', 'pay' => 30],
    ['name' => 'Ada', 'team' => 'eng', 'pay' => 10],
];

it('removes duplicate rows, by whole row or by chosen columns', function () use ($people) {
    $whole = runNodeStep('table', ['operation' => 'dedupe', 'rows' => $people]);
    $byTeam = runNodeStep('table', ['operation' => 'dedupe', 'rows' => $people, 'columns' => ['team']]);

    expect($whole->output['row_count'])->toBe(3)->and($whole->output['removed'])->toBe(1)
        ->and(array_column($byTeam->output['rows'], 'name'))->toBe(['Ada', 'Cy']);
});

it('groups rows and totals them', function () use ($people) {
    $nodeRun = runNodeStep('table', ['operation' => 'group', 'rows' => $people, 'columns' => ['team'], 'aggregates' => ['people' => 'count', 'total' => 'sum:pay', 'average' => 'avg:pay', 'top' => 'max:pay', 'low' => 'min:pay']]);

    expect($nodeRun->output['rows'])->toBe([
        ['team' => 'eng', 'people' => 3, 'total' => 40, 'average' => 13.3333, 'top' => 20, 'low' => 10],
        ['team' => 'ops', 'people' => 1, 'total' => 30, 'average' => 30, 'top' => 30, 'low' => 30],
    ]);
});

it('rejects a malformed total and a group with no column', function () use ($people) {
    expect(runNodeStep('table', ['operation' => 'group', 'rows' => $people, 'columns' => ['team'], 'aggregates' => ['x' => 'sum']])->status)->toBe(NodeRunStatus::Failed)
        ->and(runNodeStep('table', ['operation' => 'group', 'rows' => $people, 'columns' => []])->status)->toBe(NodeRunStatus::Failed);
});

it('sorts by several columns, numerically where it can', function () {
    $nodeRun = runNodeStep('table', ['operation' => 'sort', 'rows' => [['n' => 10, 'x' => 'b'], ['n' => 9, 'x' => 'a'], ['n' => 10, 'x' => 'a']], 'columns' => ['n', 'x'], 'direction' => 'desc']);

    expect(array_map(fn (array $row): string => $row['n'].$row['x'], $nodeRun->output['rows']))->toBe(['10b', '10a', '9a']);
});

it('keeps rows that match a condition, keeps or renames columns and limits the count', function () use ($people) {
    $filtered = runNodeStep('table', ['operation' => 'filter', 'rows' => $people, 'column' => 'pay', 'condition' => 'greater_than', 'value' => '15']);
    $selected = runNodeStep('table', ['operation' => 'select', 'rows' => $people, 'columns' => ['name']]);
    $renamed = runNodeStep('table', ['operation' => 'rename', 'rows' => [['name' => 'Ada']], 'mapping' => ['name' => 'full_name']]);
    $limited = runNodeStep('table', ['operation' => 'limit', 'rows' => $people, 'count' => 2]);

    expect(array_column($filtered->output['rows'], 'name'))->toBe(['Bob', 'Cy'])
        ->and($selected->output['rows'][0])->toBe(['name' => 'Ada'])
        ->and($renamed->output['rows'])->toBe([['full_name' => 'Ada']])
        ->and($limited->output['removed'])->toBe(2);
});

it('refuses rows that are not a list, an unknown condition and a missing column', function () use ($people) {
    expect(fn () => executeNode('table', ['operation' => 'dedupe', 'rows' => 'nope']))->toThrow(InvalidArgumentException::class, 'list of objects')
        ->and(fn () => executeNode('table', ['operation' => 'filter', 'rows' => $people, 'column' => 'pay', 'condition' => 'sort_of']))->toThrow(InvalidArgumentException::class, 'Unsupported condition')
        ->and(fn () => executeNode('table', ['operation' => 'filter', 'rows' => $people, 'column' => '']))->toThrow(InvalidArgumentException::class, 'which column')
        ->and(fn () => executeNode('table', ['operation' => 'explode', 'rows' => $people]))->toThrow(InvalidArgumentException::class, 'Unsupported');
});

/* ------------------------------------------------------------------- lookup */

it('maps a value through a table, ignoring case, with a fallback', function () {
    $table = ['US' => 'North America', 'DE' => 'Europe'];

    $hit = runNodeStep('lookup', ['value' => ' us ', 'table' => $table]);
    $miss = runNodeStep('lookup', ['value' => 'JP', 'table' => $table, 'fallback' => 'Rest of world']);
    $exact = runNodeStep('lookup', ['value' => 'us', 'table' => $table, 'ignore_case' => false]);

    expect($hit->output)->toBe(['result' => 'North America', 'matched' => true])
        ->and($miss->output)->toBe(['result' => 'Rest of world', 'matched' => false])
        ->and($exact->output)->toBe(['result' => null, 'matched' => false]);
});

/* ---------------------------------------------------------------- pii_redact */

it('masks the personal data it recognises and counts it', function () {
    $nodeRun = runNodeStep('pii_redact', ['text' => 'Mail ada@example.com or call +1 415 555 0132. Card 4242 4242 4242 4242, SSN 123-45-6789, from 203.0.113.9, key sk_live_abcdefghij1234567890.']);

    expect($nodeRun->output['text'])->toBe('Mail [EMAIL] or call [PHONE]. Card [CREDIT_CARD], SSN [SSN], from [IP], key [API_KEY].')
        ->and($nodeRun->output['counts'])->toBe(['api_key' => 1, 'email' => 1, 'credit_card' => 1, 'ssn' => 1, 'ip_address' => 1, 'phone' => 1])
        ->and($nodeRun->output)->toMatchArray(['found' => true, 'result' => 'redacted']);
});

it('leaves ordinary numbers alone', function () {
    $nodeRun = runNodeStep('pii_redact', ['text' => 'Order 1234567890123 was 4242 4242 4242 4241 units, version 1.2.3, on 2026-11-01.']);

    expect($nodeRun->output)->toMatchArray(['text' => 'Order 1234567890123 was 4242 4242 4242 4241 units, version 1.2.3, on 2026-11-01.', 'found' => false, 'result' => 'clean']);
});

it('masks only the kinds asked for and can use asterisks', function () {
    $nodeRun = runNodeStep('pii_redact', ['text' => 'ada@example.com 123-45-6789', 'types' => ['email'], 'style' => 'mask']);

    expect($nodeRun->output['text'])->toBe(str_repeat('*', 15).' 123-45-6789');
});

it('refuses a kind it cannot mask', function () {
    expect(fn () => executeNode('pii_redact', ['text' => 'x', 'types' => ['passport']]))->toThrow(InvalidArgumentException::class, 'passport');
});

/* -------------------------------------------------------------- html_to_text */

$page = <<<'HTML'
<!doctype html><html><head><title>Pricing — Acme</title><script>var secret = 1;</script><style>p{color:red}</style></head>
<body><nav><a href="/home">Home</a></nav>
<main><h1>Pricing</h1><p>Plans for <strong>every</strong> team, from <em>solo</em> to enterprise. Read <a href="/docs/plans">the docs</a> or <a href="javascript:alert(1)">this</a>.</p>
<ul><li>Free</li><li>Pro<ul><li>Teams</li></ul></li></ul>
<table><tr><th>Plan</th><th>Price</th></tr><tr><td>Free</td><td>$0</td></tr></table>
<p>This paragraph is here so the main area is long enough to be treated as the real content of the page by the reader: it carries more than two hundred characters of ordinary text, which is the threshold used.</p>
</main><footer>Footer text</footer></body></html>
HTML;

it('turns HTML into readable text and Markdown without scripts, menus or unsafe links', function () use ($page) {
    $nodeRun = runNodeStep('html_to_text', ['html' => $page, 'base_url' => 'https://example.com/pricing/']);

    expect($nodeRun->output['title'])->toBe('Pricing — Acme')
        ->and($nodeRun->output['markdown'])->toContain('# Pricing')
        ->and($nodeRun->output['markdown'])->toContain('**every**')
        ->and($nodeRun->output['markdown'])->toContain('*solo*')
        ->and($nodeRun->output['markdown'])->toContain('[the docs](https://example.com/docs/plans)')
        ->and($nodeRun->output['markdown'])->toContain("- Pro\n  - Teams")
        ->and($nodeRun->output['markdown'])->toContain('| Plan | Price |')
        ->and($nodeRun->output['markdown'])->not->toContain('javascript:')
        ->and($nodeRun->output['text'])->toContain('Plans for every team')
        ->and($nodeRun->output['text'])->not->toContain('secret')->not->toContain('color:red')->not->toContain('Footer text')->not->toContain('Home');
});

it('keeps menus and footers when asked for the whole page', function () use ($page) {
    $nodeRun = runNodeStep('html_to_text', ['html' => $page, 'main_content_only' => false]);

    expect($nodeRun->output['text'])->toContain('Footer text')->toContain('Home');
});

it('picks elements off a page with a CSS selector', function () {
    $html = '<div id="list"><a class="item big" href="/a">A</a><a class="item" href="/b">B</a><span class="item">C</span></div><a href="/c">D</a>';
    $document = HtmlDocument::parse($html, 'https://shop.test/');

    expect(array_column($document->select('a.item'), 'text'))->toBe(['A', 'B'])
        ->and(array_column($document->select('#list > .item'), 'text'))->toBe(['A', 'B', 'C'])
        ->and(array_column($document->select('a.big, span'), 'text'))->toBe(['A', 'C'])
        ->and($document->select('a[href="/b"]')[0]['href'])->toBe('https://shop.test/b')
        ->and($document->select('a[href^="/"]'))->toHaveCount(3);
});

it('refuses selectors it does not understand', function () {
    expect(fn () => SelectorToXPath::convert('a:hover'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => SelectorToXPath::convert('  '))->toThrow(InvalidArgumentException::class);
});

/* ---------------------------------------------------------------- pdf_create */

it('makes a PDF from Markdown and from HTML', function () {
    $markdown = runNodeStep('pdf_create', ['content' => "# Report\n\n- one\n- two\n\n| a | b |\n|---|---|\n| 1 | 2 |", 'title' => 'Monthly Report', 'paper' => 'letter', 'orientation' => 'landscape']);
    $html = runNodeStep('pdf_create', ['content' => '<h1>Hi</h1>', 'format' => 'html']);

    expect($markdown->status)->toBe(NodeRunStatus::Completed)
        ->and($markdown->output['file'])->toMatchArray(['filename' => 'monthly-report.pdf', 'mime_type' => 'application/pdf'])
        ->and(base64_decode($markdown->output['file']['content_base64']))->toStartWith('%PDF-')
        ->and($html->output['file']['filename'])->toBe('document.pdf');
});

it('does not fetch remote files or read local ones when making a PDF', function () {
    Http::fake();

    $nodeRun = runNodeStep('pdf_create', ['content' => '<img src="http://203.0.113.5/x.png"><img src="file:///etc/passwd"><link rel="stylesheet" href="http://203.0.113.5/a.css">', 'format' => 'html']);

    expect($nodeRun->status)->toBe(NodeRunStatus::Completed);

    Http::assertNothingSent();
});

it('strips raw HTML from Markdown instead of rendering it', function () {
    $nodeRun = runNodeStep('pdf_create', ['content' => 'Hello <script>alert(1)</script> [x](javascript:alert(1))']);

    expect($nodeRun->status)->toBe(NodeRunStatus::Completed);
});

it('refuses an empty document and a pasted HTML page with nothing in it', function () {
    expect(fn () => executeNode('pdf_create', ['content' => '  ']))->toThrow(InvalidArgumentException::class, 'nothing to put');
});

/* ---------------------------------------------------------------- data_store */

it('saves and reads a value, and says when it is missing', function () {
    $workspace = dataWorkspace();

    runNodeStep('data_store', ['operation' => 'set', 'key' => 'last-id', 'value' => ['id' => 42]], workspace: $workspace);
    $found = runNodeStep('data_store', ['operation' => 'get', 'key' => 'last-id'], workspace: $workspace);
    $missing = runNodeStep('data_store', ['operation' => 'get', 'key' => 'nope'], workspace: $workspace);

    expect($found->output)->toMatchArray(['value' => ['id' => 42], 'found' => true, 'result' => 'found'])
        ->and($missing->output)->toMatchArray(['value' => null, 'found' => false, 'result' => 'missing']);
});

it('keeps each workspace and each store apart', function () {
    $one = dataWorkspace();
    $two = dataWorkspace();

    runNodeStep('data_store', ['operation' => 'set', 'key' => 'k', 'value' => 'one'], workspace: $one);
    runNodeStep('data_store', ['operation' => 'set', 'key' => 'k', 'value' => 'side', 'store' => 'other'], workspace: $one);

    expect(runNodeStep('data_store', ['operation' => 'get', 'key' => 'k'], workspace: $two)->output['found'])->toBeFalse()
        ->and(runNodeStep('data_store', ['operation' => 'get', 'key' => 'k'], workspace: $one)->output['value'])->toBe('one')
        ->and(runNodeStep('data_store', ['operation' => 'get', 'key' => 'k', 'store' => 'other'], workspace: $one)->output['value'])->toBe('side');
});

it('claims a name only the first time, so a run can skip what it has done', function () {
    $workspace = dataWorkspace();

    $first = runNodeStep('data_store', ['operation' => 'claim', 'key' => 'order:1001'], workspace: $workspace);
    $second = runNodeStep('data_store', ['operation' => 'claim', 'key' => 'order:1001'], workspace: $workspace);
    $other = runNodeStep('data_store', ['operation' => 'claim', 'key' => 'order:1002'], workspace: $workspace);

    expect($first->output)->toMatchArray(['is_new' => true, 'result' => 'new'])
        ->and($second->output)->toMatchArray(['is_new' => false, 'result' => 'seen'])
        ->and($other->output['is_new'])->toBeTrue()
        ->and(WorkspaceDataEntry::query()->where('workspace_id', $workspace->id)->count())->toBe(2);
});

it('lets an expired claim be taken again and ignores an expired value', function () {
    $workspace = dataWorkspace();
    WorkspaceDataEntry::factory()->forWorkspace($workspace)->expired()->create(['key' => 'old', 'value' => 'stale']);

    expect(runNodeStep('data_store', ['operation' => 'get', 'key' => 'old'], workspace: $workspace)->output['found'])->toBeFalse()
        ->and(runNodeStep('data_store', ['operation' => 'claim', 'key' => 'old'], workspace: $workspace)->output['is_new'])->toBeTrue();
});

it('forgets a value after the days it was given', function () {
    $workspace = dataWorkspace();
    runNodeStep('data_store', ['operation' => 'set', 'key' => 'temp', 'value' => 1, 'expires_in_days' => 2], workspace: $workspace);

    expect(WorkspaceDataEntry::query()->where('key', 'temp')->first()->expires_at->isBetween(now()->addDay(), now()->addDays(3)))->toBeTrue();
});

it('adds to a number and starts from zero', function () {
    $workspace = dataWorkspace();

    runNodeStep('data_store', ['operation' => 'increment', 'key' => 'count'], workspace: $workspace);
    runNodeStep('data_store', ['operation' => 'increment', 'key' => 'count', 'amount' => 4], workspace: $workspace);
    $last = runNodeStep('data_store', ['operation' => 'increment', 'key' => 'count', 'amount' => -2], workspace: $workspace);

    expect($last->output['value'])->toBe(3);
});

it('will not add to something that is not a number, or append to something that is not a list', function () {
    $workspace = dataWorkspace();
    runNodeStep('data_store', ['operation' => 'set', 'key' => 'text', 'value' => 'hello'], workspace: $workspace);

    expect(runNodeStep('data_store', ['operation' => 'increment', 'key' => 'text'], workspace: $workspace)->status)->toBe(NodeRunStatus::Failed)
        ->and(runNodeStep('data_store', ['operation' => 'append', 'key' => 'text', 'value' => 'x'], workspace: $workspace)->status)->toBe(NodeRunStatus::Failed);
});

it('appends to a list and caps its length', function () {
    $workspace = dataWorkspace();

    runNodeStep('data_store', ['operation' => 'append', 'key' => 'seen', 'value' => 'a'], workspace: $workspace);
    $second = runNodeStep('data_store', ['operation' => 'append', 'key' => 'seen', 'value' => 'b'], workspace: $workspace);

    WorkspaceDataEntry::factory()->forWorkspace($workspace)->create(['key' => 'full', 'value' => range(1, DataStoreNode::MAX_LIST_ITEMS)]);

    expect($second->output)->toMatchArray(['value' => ['a', 'b'], 'count' => 2])
        ->and(runNodeStep('data_store', ['operation' => 'append', 'key' => 'full', 'value' => 'x'], workspace: $workspace)->status)->toBe(NodeRunStatus::Failed);
});

it('deletes a value and lists names by prefix', function () {
    $workspace = dataWorkspace();

    foreach (['a:1', 'a:2', 'b:1', 'a%x'] as $key) {
        runNodeStep('data_store', ['operation' => 'set', 'key' => $key, 'value' => 1], workspace: $workspace);
    }

    $deleted = runNodeStep('data_store', ['operation' => 'delete', 'key' => 'a:1'], workspace: $workspace);
    $again = runNodeStep('data_store', ['operation' => 'delete', 'key' => 'a:1'], workspace: $workspace);
    $keys = runNodeStep('data_store', ['operation' => 'list_keys', 'prefix' => 'a:'], workspace: $workspace);
    $literal = runNodeStep('data_store', ['operation' => 'list_keys', 'prefix' => 'a%'], workspace: $workspace);

    expect($deleted->output['deleted'])->toBeTrue()
        ->and($again->output['deleted'])->toBeFalse()
        ->and($keys->output['keys'])->toBe(['a:2'])
        ->and($literal->output['keys'])->toBe(['a%x']);
});

it('refuses a missing name, a bad store name, an oversized value and a full store', function () {
    $workspace = dataWorkspace();

    expect(fn () => executeNode('data_store', ['operation' => 'set', 'key' => '  ', 'value' => 1], $workspace))->toThrow(InvalidArgumentException::class, 'Give the value a name')
        ->and(fn () => executeNode('data_store', ['operation' => 'set', 'key' => str_repeat('k', 256), 'value' => 1], $workspace))->toThrow(InvalidArgumentException::class, 'too long')
        ->and(fn () => executeNode('data_store', ['operation' => 'set', 'key' => 'k', 'value' => 1, 'store' => 'bad store!'], $workspace))->toThrow(InvalidArgumentException::class, 'store name')
        ->and(fn () => executeNode('data_store', ['operation' => 'set', 'key' => 'big', 'value' => str_repeat('a', DataStoreNode::MAX_VALUE_BYTES + 1)], $workspace))->toThrow(InvalidArgumentException::class, 'too large')
        ->and(fn () => executeNode('data_store', ['operation' => 'explode', 'key' => 'k'], $workspace))->toThrow(InvalidArgumentException::class, 'Unsupported');
});

it('stops saving new names once the workspace store is full, but still updates an existing one', function () {
    $workspace = dataWorkspace();
    $now = now();
    $rows = [];

    for ($i = 0; $i < DataStoreNode::MAX_ENTRIES; $i++) {
        $rows[] = ['id' => (string) Str::uuid(), 'workspace_id' => $workspace->id, 'store' => 'default', 'key' => "k{$i}", 'value' => '1', 'created_at' => $now, 'updated_at' => $now];

        if (count($rows) === 1000) {
            WorkspaceDataEntry::query()->insert($rows);
            $rows = [];
        }
    }

    expect(fn () => executeNode('data_store', ['operation' => 'set', 'key' => 'one-too-many', 'value' => 1], $workspace))->toThrow(RuntimeException::class, 'full')
        ->and(executeNode('data_store', ['operation' => 'set', 'key' => 'k0', 'value' => 2], $workspace)['value'])->toBe(2);
});

it('declares which operations only read, which write and which destroy', function () {
    $node = app(DataStoreNode::class);

    expect($node->effect(['operation' => 'get'])->isRead())->toBeTrue()
        ->and($node->effect(['operation' => 'list_keys'])->isRead())->toBeTrue()
        ->and($node->effect(['operation' => 'set'])->isRead())->toBeFalse()
        ->and($node->effect(['operation' => 'delete'])->value)->toBe('destructive');
});
