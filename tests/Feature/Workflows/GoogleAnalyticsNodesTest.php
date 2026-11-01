<?php

use App\Models\Runs\Run;
use App\Nodes\Integrations\GoogleAnalytics\GoogleAnalyticsListPropertiesNode;
use App\Nodes\Integrations\GoogleAnalytics\GoogleAnalyticsRunRealtimeReportNode;
use App\Nodes\Integrations\GoogleAnalytics\GoogleAnalyticsRunReportNode;
use App\Services\Workflows\NodeRegistry;
use Illuminate\Support\Facades\Http;

const GA_BASE = ['access_token' => 'ya29-test'];

const GA_REPORT = [
    'dimensionHeaders' => [['name' => 'country']],
    'metricHeaders' => [['name' => 'sessions'], ['name' => 'engagementRate']],
    'rows' => [
        ['dimensionValues' => [['value' => 'India']], 'metricValues' => [['value' => '120'], ['value' => '0.5']]],
        ['dimensionValues' => [['value' => 'Peru']], 'metricValues' => [['value' => '7'], ['value' => '0.25']]],
    ],
    'rowCount' => 2,
];

it('registers every google analytics node under the google_analytics category', function () {
    foreach (['run_report', 'run_realtime_report', 'list_properties'] as $suffix) {
        expect(app(NodeRegistry::class)->resolve("google_analytics_{$suffix}")->category())->toBe('google_analytics');
    }
});

it('runs a report and returns rows keyed by name with numeric metrics', function () {
    Http::fake(['analyticsdata.googleapis.com/*' => Http::response(GA_REPORT)]);

    $output = (new GoogleAnalyticsRunReportNode)->execute(Run::factory()->create(), [
        ...GA_BASE, 'property_id' => 'properties/123456', 'metrics' => 'sessions, engagementRate', 'dimensions' => 'country', 'order_by' => 'sessions', 'start_date' => '30daysAgo',
    ], []);

    expect($output)->toBe([
        'rows' => [['country' => 'India', 'sessions' => 120, 'engagementRate' => 0.5], ['country' => 'Peru', 'sessions' => 7, 'engagementRate' => 0.25]],
        'row_count' => 2,
    ]);
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/v1beta/properties/123456:runReport')
        && $request['dateRanges'] === [['startDate' => '30daysAgo', 'endDate' => 'yesterday']]
        && $request['metrics'] === [['name' => 'sessions'], ['name' => 'engagementRate']]
        && $request['dimensions'] === [['name' => 'country']]
        && $request['orderBys'] === [['metric' => ['metricName' => 'sessions'], 'desc' => true]]
        && $request['limit'] === '100');
});

it('sorts by a dimension when the sort name is not a metric', function () {
    Http::fake(['analyticsdata.googleapis.com/*' => Http::response(GA_REPORT)]);

    (new GoogleAnalyticsRunReportNode)->execute(Run::factory()->create(), [
        ...GA_BASE, 'property_id' => '1', 'metrics' => ['sessions'], 'dimensions' => ['date', 'country'], 'order_by' => 'date', 'descending' => false,
    ], []);

    Http::assertSent(fn ($request) => $request['orderBys'] === [['dimension' => ['dimensionName' => 'date'], 'desc' => false]]
        && $request['dimensions'] === [['name' => 'date'], ['name' => 'country']]);
});

it('runs a totals report without dimensions or sorting', function () {
    Http::fake(['analyticsdata.googleapis.com/*' => Http::response(['metricHeaders' => [['name' => 'sessions']], 'rows' => [['metricValues' => [['value' => '9']]]]])]);

    $output = (new GoogleAnalyticsRunReportNode)->execute(Run::factory()->create(), [...GA_BASE, 'property_id' => '1', 'metrics' => 'sessions'], []);

    expect($output['rows'])->toBe([['sessions' => 9]]);
    Http::assertSent(fn ($request) => ! isset($request['dimensions'], $request['orderBys']));
});

it('refuses a report without metrics', function () {
    Http::fake();

    (new GoogleAnalyticsRunReportNode)->execute(Run::factory()->create(), [...GA_BASE, 'property_id' => '1', 'metrics' => ' , '], []);
})->throws(InvalidArgumentException::class, 'at least one metric');

it('refuses a property id that is not a number', function (string $id) {
    Http::fake();

    (new GoogleAnalyticsRunReportNode)->execute(Run::factory()->create(), [...GA_BASE, 'property_id' => $id, 'metrics' => 'sessions'], []);
})->with(['abc', '12/../x', '1?x=1', ''])->throws(InvalidArgumentException::class, 'property ID is a number');

it('runs a realtime report with active users by default', function () {
    Http::fake(['analyticsdata.googleapis.com/*' => Http::response(['metricHeaders' => [['name' => 'activeUsers']], 'rows' => [['metricValues' => [['value' => '4']]]]])]);

    $output = (new GoogleAnalyticsRunRealtimeReportNode)->execute(Run::factory()->create(), [...GA_BASE, 'property_id' => '77', 'metrics' => '', 'dimensions' => ''], []);

    expect($output['rows'])->toBe([['activeUsers' => 4]]);
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/properties/77:runRealtimeReport') && $request['metrics'] === [['name' => 'activeUsers']]);
});

it('lists properties across accounts from the admin api', function () {
    Http::fake(['analyticsadmin.googleapis.com/*' => Http::response(['accountSummaries' => [
        ['displayName' => 'Acme', 'propertySummaries' => [['property' => 'properties/111', 'displayName' => 'Web'], ['property' => 'properties/222', 'displayName' => 'App']]],
        ['displayName' => 'Empty'],
    ]])]);

    $output = (new GoogleAnalyticsListPropertiesNode)->execute(Run::factory()->create(), GA_BASE, []);

    expect($output)->toBe(['properties' => [
        ['property_id' => '111', 'name' => 'Web', 'account' => 'Acme'],
        ['property_id' => '222', 'name' => 'App', 'account' => 'Acme'],
    ]]);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'analyticsadmin.googleapis.com/v1beta/accountSummaries'));
});
