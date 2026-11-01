<?php

use App\Models\Runs\Run;
use App\Nodes\Integrations\GoogleSheets\GoogleSheetsAddSheetNode;
use App\Nodes\Integrations\GoogleSheets\GoogleSheetsAppendValuesNode;
use App\Nodes\Integrations\GoogleSheets\GoogleSheetsClearRangeNode;
use App\Nodes\Integrations\GoogleSheets\GoogleSheetsCreateSpreadsheetNode;
use App\Nodes\Integrations\GoogleSheets\GoogleSheetsDeleteRowsNode;
use App\Nodes\Integrations\GoogleSheets\GoogleSheetsGetSpreadsheetInfoNode;
use App\Nodes\Integrations\GoogleSheets\GoogleSheetsGetValuesNode;
use App\Nodes\Integrations\GoogleSheets\GoogleSheetsListUpdatedRowsNode;
use App\Nodes\Integrations\GoogleSheets\GoogleSheetsLookupRowsNode;
use App\Nodes\Integrations\GoogleSheets\GoogleSheetsUpdateValuesNode;
use App\Services\Workflows\NodeRegistry;
use Illuminate\Support\Facades\Http;

it('registers every google sheets node under its own type string', function () {
    $registry = app(NodeRegistry::class);

    $types = [
        'google_sheets_get_values' => GoogleSheetsGetValuesNode::class,
        'google_sheets_append_values' => GoogleSheetsAppendValuesNode::class,
        'google_sheets_update_values' => GoogleSheetsUpdateValuesNode::class,
    ];

    foreach ($types as $type => $class) {
        expect($registry->has($type))->toBeTrue();
        $node = $registry->resolve($type);
        expect($node)->toBeInstanceOf($class);
        expect($node->category())->toBe('google_sheets');
    }
});

it('gets values for a range', function () {
    Http::fake(['sheets.googleapis.com/*' => Http::response(['values' => [['a', 'b']]])]);

    $node = new GoogleSheetsGetValuesNode;
    $run = Run::factory()->create();

    $output = $node->execute($run, [
        'access_token' => 'ya29-test',
        'spreadsheet_id' => 'sheet1',
        'range' => 'Sheet1!A1:B1',
    ], []);

    expect($output)->toBe(['values' => [['a', 'b']]]);
    Http::assertSent(fn ($request) => str_contains($request->url(), '/v4/spreadsheets/sheet1/values/Sheet1!A1:B1'));
});

it('encodes a quoted tab name picked from the sheet dropdown', function () {
    Http::fake(['sheets.googleapis.com/*' => Http::response(['updates' => ['updatedRows' => 1]])]);

    (new GoogleSheetsAppendValuesNode)->execute(Run::factory()->create(), [
        'access_token' => 'ya29-test',
        'spreadsheet_id' => 'sheet1',
        'range' => "'Q1 Sales'!A:B",
        'values' => ['a', 'b'],
    ], []);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/v4/spreadsheets/sheet1/values/%27Q1%20Sales%27!A:B:append?valueInputOption=USER_ENTERED'));
});

it('appends a row of values', function () {
    Http::fake(['sheets.googleapis.com/*' => Http::response(['updates' => ['updatedRows' => 1]])]);

    $node = new GoogleSheetsAppendValuesNode;
    $run = Run::factory()->create();

    $node->execute($run, [
        'access_token' => 'ya29-test',
        'spreadsheet_id' => 'sheet1',
        'range' => 'Sheet1!A:B',
        'values' => ['a', 'b'],
    ], []);

    Http::assertSent(function ($request) {
        return $request->method() === 'POST'
            && str_contains($request->url(), '/values/Sheet1!A:B:append')
            && str_contains($request->url(), 'valueInputOption=USER_ENTERED')
            && $request['values'] === [['a', 'b']];
    });
});

it('overwrites values for a range', function () {
    Http::fake(['sheets.googleapis.com/*' => Http::response(['updatedCells' => 2])]);

    $node = new GoogleSheetsUpdateValuesNode;
    $run = Run::factory()->create();

    $node->execute($run, [
        'access_token' => 'ya29-test',
        'spreadsheet_id' => 'sheet1',
        'range' => 'Sheet1!A1:B1',
        'values' => ['x', 'y'],
    ], []);

    Http::assertSent(function ($request) {
        return $request->method() === 'PUT'
            && str_contains($request->url(), '/values/Sheet1!A1:B1')
            && $request['values'] === [['x', 'y']];
    });
});

it('throws when sheets answers a non-2xx status', function () {
    Http::fake(['sheets.googleapis.com/*' => Http::response(['error' => ['message' => 'Requested entity was not found.']], 404)]);

    $node = new GoogleSheetsGetValuesNode;
    $run = Run::factory()->create();

    expect(fn () => $node->execute($run, [
        'access_token' => 'ya29-test', 'spreadsheet_id' => 'missing', 'range' => 'A1:B1',
    ], []))->toThrow(RuntimeException::class, 'Requested entity was not found.');
});

it('throws when access_token is missing', function () {
    $node = new GoogleSheetsGetValuesNode;
    $run = Run::factory()->create();

    expect(fn () => $node->execute($run, ['spreadsheet_id' => 's1', 'range' => 'A1'], []))
        ->toThrow(RuntimeException::class, 'access_token');
});

it('registers the spreadsheet management and lookup nodes', function () {
    $registry = app(NodeRegistry::class);

    foreach ([
        'google_sheets_create_spreadsheet', 'google_sheets_get_spreadsheet_info', 'google_sheets_add_sheet',
        'google_sheets_clear_range', 'google_sheets_delete_rows', 'google_sheets_lookup_rows',
    ] as $type) {
        expect($registry->has($type))->toBeTrue();
        expect($registry->resolve($type)->category())->toBe('google_sheets');
    }
});

it('creates a spreadsheet and writes the header row to its first tab', function () {
    Http::fake([
        'sheets.googleapis.com/v4/spreadsheets' => Http::response([
            'spreadsheetId' => 'new1',
            'spreadsheetUrl' => 'https://docs.google.com/spreadsheets/d/new1',
            'properties' => ['title' => 'Report'],
            'sheets' => [['properties' => ['title' => 'Q1 Sales']]],
        ]),
        'sheets.googleapis.com/*' => Http::response(['updates' => ['updatedRows' => 1]]),
    ]);

    $output = (new GoogleSheetsCreateSpreadsheetNode)->execute(Run::factory()->create(), [
        'access_token' => 'ya29-test',
        'title' => 'Report',
        'sheet_title' => 'Q1 Sales',
        'header' => ['Name', 'Email'],
    ], []);

    expect($output)->toBe(['spreadsheet_id' => 'new1', 'url' => 'https://docs.google.com/spreadsheets/d/new1', 'title' => 'Report']);
    Http::assertSent(fn ($request) => str_contains($request->url(), '/v4/spreadsheets/new1/values/%27Q1%20Sales%27!A1:append')
        && $request['values'] === [['Name', 'Email']]);
});

it('creates a spreadsheet without a header write when none is given', function () {
    Http::fake(['sheets.googleapis.com/*' => Http::response(['spreadsheetId' => 'new2', 'properties' => ['title' => 'Empty']])]);

    (new GoogleSheetsCreateSpreadsheetNode)->execute(Run::factory()->create(), ['access_token' => 'ya29-test', 'title' => 'Empty'], []);

    Http::assertSentCount(1);
});

it('summarises a spreadsheet and its tabs', function () {
    Http::fake(['sheets.googleapis.com/*' => Http::response([
        'spreadsheetId' => 'sheet1',
        'spreadsheetUrl' => 'https://docs.google.com/spreadsheets/d/sheet1',
        'properties' => ['title' => 'Report', 'locale' => 'en_US', 'timeZone' => 'Asia/Kolkata'],
        'sheets' => [['properties' => ['sheetId' => 0, 'title' => 'Sheet1', 'index' => 0, 'gridProperties' => ['rowCount' => 1000, 'columnCount' => 26]]]],
    ])]);

    $output = (new GoogleSheetsGetSpreadsheetInfoNode)->execute(Run::factory()->create(), ['access_token' => 'ya29-test', 'spreadsheet_id' => 'sheet1'], []);

    expect($output['title'])->toBe('Report')
        ->and($output['sheets'])->toBe([['sheet_id' => 0, 'title' => 'Sheet1', 'index' => 0, 'row_count' => 1000, 'column_count' => 26]]);
});

it('adds a tab and returns its id', function () {
    Http::fake(['sheets.googleapis.com/*' => Http::response(['replies' => [['addSheet' => ['properties' => ['sheetId' => 77, 'title' => 'April', 'index' => 2]]]]])]);

    $output = (new GoogleSheetsAddSheetNode)->execute(Run::factory()->create(), ['access_token' => 'ya29-test', 'spreadsheet_id' => 'sheet1', 'title' => 'April'], []);

    expect($output)->toBe(['sheet_id' => 77, 'title' => 'April', 'index' => 2]);
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/v4/spreadsheets/sheet1:batchUpdate')
        && $request['requests'][0]['addSheet']['properties']['title'] === 'April');
});

it('clears a range with an empty request body', function () {
    Http::fake(['sheets.googleapis.com/*' => Http::response(['clearedRange' => 'Sheet1!A2:D100'])]);

    (new GoogleSheetsClearRangeNode)->execute(Run::factory()->create(), [
        'access_token' => 'ya29-test', 'spreadsheet_id' => 'sheet1', 'range' => 'Sheet1!A2:D100',
    ], []);

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/values/Sheet1!A2:D100:clear')
        && $request->method() === 'POST'
        && $request->body() === '');
});

it('deletes rows from a tab found by name', function () {
    Http::fake([
        'sheets.googleapis.com/v4/spreadsheets/sheet1?*' => Http::response(['sheets' => [
            ['properties' => ['sheetId' => 0, 'title' => 'Sheet1']],
            ['properties' => ['sheetId' => 42, 'title' => "Q1 'Sales'"]],
        ]]),
        'sheets.googleapis.com/*' => Http::response(['replies' => [[]]]),
    ]);

    $output = (new GoogleSheetsDeleteRowsNode)->execute(Run::factory()->create(), [
        'access_token' => 'ya29-test', 'spreadsheet_id' => 'sheet1', 'range' => "'Q1 ''Sales'''", 'start_row' => 5, 'row_count' => 3,
    ], []);

    expect($output)->toBe(['deleted_rows' => 3, 'start_row' => 5]);
    Http::assertSent(fn ($request) => str_ends_with($request->url(), ':batchUpdate')
        && $request['requests'][0]['deleteDimension']['range'] === ['sheetId' => 42, 'dimension' => 'ROWS', 'startIndex' => 4, 'endIndex' => 7]);
});

it('refuses to delete rows from a tab that does not exist', function () {
    Http::fake(['sheets.googleapis.com/*' => Http::response(['sheets' => [['properties' => ['sheetId' => 0, 'title' => 'Sheet1']]]])]);

    (new GoogleSheetsDeleteRowsNode)->execute(Run::factory()->create(), [
        'access_token' => 'ya29-test', 'spreadsheet_id' => 'sheet1', 'range' => 'Nope', 'start_row' => 2,
    ], []);
})->throws(RuntimeException::class, 'no tab named "Nope"');

it('looks up rows by a column value, ignoring case and reporting sheet row numbers', function () {
    Http::fake(['sheets.googleapis.com/*' => Http::response(['values' => [
        ['Name', 'Email'],
        ['Ada', 'ADA@example.com'],
        ['Bob', 'bob@example.com'],
        ['Ada 2', 'ada@example.com'],
    ]])]);

    $output = (new GoogleSheetsLookupRowsNode)->execute(Run::factory()->create(), [
        'access_token' => 'ya29-test', 'spreadsheet_id' => 'sheet1', 'range' => 'Sheet1', 'column' => 'Email', 'value' => 'ada@example.com',
    ], []);

    expect($output['count'])->toBe(2)
        ->and($output['rows'][0])->toBe(['_row' => 2, 'Name' => 'Ada', 'Email' => 'ADA@example.com'])
        ->and($output['rows'][1]['_row'])->toBe(4);
});

it('reports when the lookup column does not exist', function () {
    Http::fake(['sheets.googleapis.com/*' => Http::response(['values' => [['Name'], ['Ada']]])]);

    $output = (new GoogleSheetsLookupRowsNode)->execute(Run::factory()->create(), [
        'access_token' => 'ya29-test', 'spreadsheet_id' => 'sheet1', 'range' => 'Sheet1', 'column' => 'Email', 'value' => 'x',
    ], []);

    expect($output)->toBe(['rows' => [], 'count' => 0, 'column_found' => false]);
});

it('stops a lookup at the max results', function () {
    Http::fake(['sheets.googleapis.com/*' => Http::response(['values' => [['K'], ['a'], ['a'], ['a']]])]);

    $output = (new GoogleSheetsLookupRowsNode)->execute(Run::factory()->create(), [
        'access_token' => 'ya29-test', 'spreadsheet_id' => 'sheet1', 'range' => 'Sheet1', 'column' => 'K', 'value' => 'a', 'max_results' => 2,
    ], []);

    expect($output['count'])->toBe(2);
});

it('registers the row change reader', function () {
    expect(app(NodeRegistry::class)->has('google_sheets_list_updated_rows'))->toBeTrue();
});

it('keeps only the last rows of a big sheet for the new row trigger, so rows at the bottom are seen', function () {
    config(['triggers.poll_max_items' => 2]);
    $values = [['Name']];

    foreach (range(1, 10) as $number) {
        $values[] = ["row {$number}"];
    }

    $items = (new GoogleSheetsGetValuesNode)->triggerItems(['range' => 'Sheet1!A1:A11', 'values' => $values], []);

    // One poll looks at 2 items, so the last 2 rows are kept, still numbered as in the sheet.
    expect(array_column($items, '_row'))->toBe([10, 11])
        ->and($items[1]['Name'])->toBe('row 10');
});

it('keeps every row of a small sheet', function () {
    $items = (new GoogleSheetsGetValuesNode)->triggerItems(['values' => [['A'], ['x'], ['y']]], []);

    expect(array_column($items, '_row'))->toBe([2, 3]);
});

it('versions each row by its cells', function () {
    Http::fake(['sheets.googleapis.com/*' => Http::response(['range' => 'Sheet1!A1:B3', 'values' => [['Name', 'Qty'], ['a', '1'], ['b', '2']]])]);
    $node = new GoogleSheetsListUpdatedRowsNode;

    $rows = $node->execute(Run::factory()->create(), ['access_token' => 'ya29-test', 'spreadsheet_id' => 's1', 'range' => 'Sheet1'], [])['rows'];

    expect($rows)->toHaveCount(2)
        ->and($rows[0])->toMatchArray(['_row' => 2, 'Name' => 'a', 'Qty' => '1'])
        ->and($rows[0]['_version'])->toHaveLength(12)
        ->and($rows[0]['_version'])->not->toBe($rows[1]['_version']);
});

it('triggers again when a cell in a row changes, and not when it does not', function () {
    $node = new GoogleSheetsListUpdatedRowsNode;
    $row = fn (string $qty): array => $node->execute(
        Run::factory()->create(),
        ['access_token' => 'ya29-test', 'spreadsheet_id' => 's1', 'range' => 'Sheet1'],
        [],
    )['rows'][0];

    Http::fake(['sheets.googleapis.com/*' => Http::sequence()
        ->push(['values' => [['Name', 'Qty'], ['a', '1']]])
        ->push(['values' => [['Name', 'Qty'], ['a', '1']]])
        ->push(['values' => [['Name', 'Qty'], ['a', '5']]])]);

    [$first, $same, $edited] = [$row('1'), $row('1'), $row('5')];

    expect($node->triggerItemId($first))->toBe($node->triggerItemId($same))
        ->and($node->triggerItemId($edited))->not->toBe($node->triggerItemId($first))
        ->and($node->triggerItemId($edited))->toStartWith('2:')
        ->and($node->triggerName())->toBe('Google Sheets: Row added or updated');
});

it('caps the rows a poll acts on at the tail of the sheet', function () {
    config(['triggers.poll_max_items' => 3]);
    $rows = array_map(fn (int $number): array => ['_row' => $number, '_version' => 'v'], range(2, 20));

    expect(array_column((new GoogleSheetsListUpdatedRowsNode)->triggerItems(['rows' => $rows], []), '_row'))->toBe([18, 19, 20]);
});
