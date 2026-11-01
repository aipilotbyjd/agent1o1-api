<?php

use App\Models\Runs\Run;
use App\Nodes\Integrations\GoogleSearchConsole\GoogleSearchConsoleInspectUrlNode;
use App\Nodes\Integrations\GoogleSearchConsole\GoogleSearchConsoleListSitemapsNode;
use App\Nodes\Integrations\GoogleSearchConsole\GoogleSearchConsoleListSitesNode;
use App\Nodes\Integrations\GoogleSearchConsole\GoogleSearchConsoleQuerySearchAnalyticsNode;
use App\Services\Workflows\NodeRegistry;
use Illuminate\Support\Facades\Http;

const GSC_BASE = ['access_token' => 'ya29-test'];

it('registers every search console node under the google_search_console category', function () {
    foreach (['list_sites', 'query_search_analytics', 'list_sitemaps', 'inspect_url'] as $suffix) {
        expect(app(NodeRegistry::class)->resolve("google_search_console_{$suffix}")->category())->toBe('google_search_console');
    }
});

it('lists sites', function () {
    Http::fake(['www.googleapis.com/webmasters/*' => Http::response(['siteEntry' => [['siteUrl' => 'sc-domain:example.com', 'permissionLevel' => 'siteOwner']]])]);

    $output = (new GoogleSearchConsoleListSitesNode)->execute(Run::factory()->create(), GSC_BASE, []);

    expect($output)->toBe(['sites' => [['site_url' => 'sc-domain:example.com', 'permission_level' => 'siteOwner']]]);
});

it('queries search analytics with an encoded site url and named dimension columns', function () {
    Http::fake(['www.googleapis.com/webmasters/*' => Http::response(['rows' => [
        ['keys' => ['laravel', 'https://example.com/a'], 'clicks' => 10, 'impressions' => 200, 'ctr' => 0.05123456, 'position' => 3.456789],
    ]])]);

    $output = (new GoogleSearchConsoleQuerySearchAnalyticsNode)->execute(Run::factory()->create(), [
        ...GSC_BASE, 'site_url' => 'https://example.com/', 'start_date' => '2026-01-01', 'end_date' => '2026-01-31', 'dimensions' => 'Query, page, bogus',
    ], []);

    expect($output)->toBe(['rows' => [
        ['query' => 'laravel', 'page' => 'https://example.com/a', 'clicks' => 10, 'impressions' => 200, 'ctr' => 0.0512, 'position' => 3.46],
    ], 'row_count' => 1]);
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/sites/https%3A%2F%2Fexample.com%2F/searchAnalytics/query')
        && $request['dimensions'] === ['query', 'page']
        && $request['type'] === 'web'
        && $request['rowLimit'] === 100);
});

it('returns one row of totals when no dimension is chosen', function () {
    Http::fake(['www.googleapis.com/webmasters/*' => Http::response(['rows' => [['clicks' => 5, 'impressions' => 50, 'ctr' => 0.1, 'position' => 8]]])]);

    $output = (new GoogleSearchConsoleQuerySearchAnalyticsNode)->execute(Run::factory()->create(), [
        ...GSC_BASE, 'site_url' => 'sc-domain:example.com', 'start_date' => '2026-01-01', 'end_date' => '2026-01-31',
    ], []);

    expect($output['rows'])->toBe([['clicks' => 5, 'impressions' => 50, 'ctr' => 0.1, 'position' => 8.0]]);
    Http::assertSent(fn ($request) => ! isset($request['dimensions']) && str_contains($request->url(), '/sites/sc-domain%3Aexample.com/'));
});

it('returns no rows when there is no data', function () {
    Http::fake(['www.googleapis.com/webmasters/*' => Http::response([])]);

    $output = (new GoogleSearchConsoleQuerySearchAnalyticsNode)->execute(Run::factory()->create(), [
        ...GSC_BASE, 'site_url' => 'sc-domain:example.com', 'start_date' => '2026-01-01', 'end_date' => '2026-01-02',
    ], []);

    expect($output)->toBe(['rows' => [], 'row_count' => 0]);
});

it('lists sitemaps', function () {
    Http::fake(['www.googleapis.com/webmasters/*' => Http::response(['sitemap' => [['path' => 'https://example.com/sitemap.xml', 'lastDownloaded' => '2026-01-01T00:00:00Z', 'isPending' => false, 'errors' => '2', 'warnings' => '0']]])]);

    $output = (new GoogleSearchConsoleListSitemapsNode)->execute(Run::factory()->create(), [...GSC_BASE, 'site_url' => 'https://example.com/'], []);

    expect($output['sitemaps'][0])->toMatchArray(['path' => 'https://example.com/sitemap.xml', 'errors' => 2, 'is_pending' => false]);
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/sites/https%3A%2F%2Fexample.com%2F/sitemaps'));
});

it('inspects a url through the search console api', function () {
    Http::fake(['searchconsole.googleapis.com/*' => Http::response(['inspectionResult' => [
        'inspectionResultLink' => 'https://search.google.com/search-console/inspect',
        'indexStatusResult' => ['verdict' => 'PASS', 'coverageState' => 'Submitted and indexed', 'lastCrawlTime' => '2026-01-01T00:00:00Z', 'googleCanonical' => 'https://example.com/p'],
    ]])]);

    $output = (new GoogleSearchConsoleInspectUrlNode)->execute(Run::factory()->create(), [
        ...GSC_BASE, 'site_url' => 'https://example.com/', 'inspection_url' => 'https://example.com/p',
    ], []);

    expect($output)->toMatchArray(['verdict' => 'PASS', 'coverage' => 'Submitted and indexed', 'report_url' => 'https://search.google.com/search-console/inspect']);
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/v1/urlInspection/index:inspect')
        && $request['inspectionUrl'] === 'https://example.com/p' && $request['siteUrl'] === 'https://example.com/');
});
