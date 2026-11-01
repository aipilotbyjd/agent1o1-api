<?php

use App\Enums\NodeRunStatus;
use App\Services\Workflows\NodeRegistry;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // Nothing here may reach an outside service for real: an unfaked call fails the test.
    Http::preventStrayRequests();
});

/**
 * Runs a node with an API key given directly (the way a run with a connected
 * account resolves to one), and returns its output.
 *
 * @param  array<string, mixed>  $config
 * @return array<string, mixed>
 */
function keyedOutput(string $type, array $config): array
{
    $nodeRun = runNodeStep($type, ['access_token' => 'test-key', ...$config]);

    expect($nodeRun->status)->toBe(NodeRunStatus::Completed, (string) $nodeRun->error);

    return $nodeRun->output;
}

/* -------------------------------------------------------------------- Brave */

it('searches the web with Brave, sending the key in its header', function () {
    Http::fake(['api.search.brave.com/*' => Http::response(['web' => ['results' => [
        ['title' => 'Best <strong>tools</strong>', 'url' => 'https://a.test/x', 'description' => 'Tools &amp; more', 'age' => '2 days ago', 'profile' => ['name' => 'A Site']],
        ['title' => 'No link'],
    ]]])]);

    $output = keyedOutput('brave_search_web', ['query' => 'project tools', 'count' => 5, 'country' => 'gb', 'language' => 'EN', 'freshness' => 'pw', 'safesearch' => 'strict']);

    expect($output)->toMatchArray(['count' => 1, 'urls' => ['https://a.test/x'], 'query' => 'project tools'])
        ->and($output['results'][0])->toMatchArray(['title' => 'Best tools', 'description' => 'Tools & more', 'age' => '2 days ago', 'source' => 'A Site']);

    Http::assertSent(fn (Request $request): bool => $request->header('X-Subscription-Token') === ['test-key']
        && $request['q'] === 'project tools' && $request['count'] === 5 && $request['country'] === 'GB'
        && $request['search_lang'] === 'en' && $request['freshness'] === 'pw' && $request['safesearch'] === 'strict');
});

it('searches Brave news and leaves out freshness when not asked', function () {
    Http::fake(['api.search.brave.com/*' => Http::response(['results' => [['title' => 'Headline', 'url' => 'https://n.test/1', 'description' => 'Lead', 'age' => '1 hour ago', 'meta_url' => ['hostname' => 'n.test']]]])]);

    $output = keyedOutput('brave_search_news', ['query' => 'ai regulation']);

    expect($output['results'][0])->toMatchArray(['title' => 'Headline', 'source' => 'n.test']);

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/news/search') && ! array_key_exists('freshness', $request->data()));
});

it('refuses an empty or oversized Brave search before calling anything', function () {
    Http::fake();

    expect(fn () => executeNode('brave_search_web', ['access_token' => 'k', 'query' => '  ']))->toThrow(InvalidArgumentException::class, 'empty')
        ->and(fn () => executeNode('brave_search_web', ['access_token' => 'k', 'query' => str_repeat('a', 401)]))->toThrow(InvalidArgumentException::class, 'too long');

    Http::assertNothingSent();
});

/* ------------------------------------------------------------------- Tavily */

it('searches with Tavily and wraps the results as outside content', function () {
    Http::fake(['api.tavily.com/search' => Http::response(['answer' => 'It is 14 days.', 'results' => [['title' => 'Policy', 'url' => 'https://p.test', 'content' => 'Refunds within 14 days.', 'score' => 0.91234567], ['title' => 'x']]])]);

    $output = keyedOutput('tavily_search', ['query' => 'refund policy', 'include_answer' => true, 'depth' => 'advanced', 'max_results' => 3, 'include_domains' => ['p.test'], 'topic' => 'news', 'days' => 7]);

    expect($output)->toMatchArray(['answer' => 'It is 14 days.', 'count' => 1, 'urls' => ['https://p.test']])
        ->and($output['results'][0]['score'])->toBe(0.9123)
        ->and($output['context'])->toStartWith('<untrusted_content source="web_search">')->toContain('Refunds within 14 days.');

    Http::assertSent(fn (Request $request): bool => $request->header('Authorization') === ['Bearer test-key']
        && $request['search_depth'] === 'advanced' && $request['include_answer'] === true && $request['include_domains'] === ['p.test']
        && $request['topic'] === 'news' && $request['days'] === 7 && $request['max_results'] === 3);
});

it('reads pages with Tavily and reports the ones it could not', function () {
    Http::fake(['api.tavily.com/extract' => Http::response(['results' => [['url' => 'https://a.test', 'raw_content' => 'Page text']], 'failed_results' => [['url' => 'https://b.test', 'error' => 'Blocked']]])]);

    $output = keyedOutput('tavily_extract', ['urls' => ['https://a.test', 'https://b.test']]);

    expect($output)->toMatchArray(['count' => 1, 'pages' => [['url' => 'https://a.test', 'content' => 'Page text']], 'failed' => [['url' => 'https://b.test', 'error' => 'Blocked']]])
        ->and($output['context'])->toContain('Page text');
});

it('asks Tavily to read at most ten full web addresses', function () {
    Http::fake();

    expect(fn () => executeNode('tavily_extract', ['access_token' => 'k', 'urls' => ['not-a-url', 'ftp://x']]))->toThrow(InvalidArgumentException::class, 'full web address')
        ->and(fn () => executeNode('tavily_extract', ['access_token' => 'k', 'urls' => array_map(fn (int $i): string => "https://a{$i}.test", range(1, 11))]))->toThrow(InvalidArgumentException::class, 'at most 10')
        ->and(fn () => executeNode('tavily_search', ['access_token' => 'k', 'query' => ' ']))->toThrow(InvalidArgumentException::class);

    Http::assertNothingSent();
});

/* ------------------------------------------------------------------- Hunter */

it('lists the emails Hunter knows at a company', function () {
    Http::fake(['api.hunter.io/v2/domain-search*' => Http::response(['data' => ['organization' => 'Acme', 'pattern' => '{first}.{last}', 'emails' => [
        ['value' => 'ada.l@acme.com', 'type' => 'personal', 'confidence' => 96, 'first_name' => 'Ada', 'last_name' => 'L', 'position' => 'CTO', 'department' => 'it', 'linkedin' => 'https://linkedin.test/ada', 'phone_number' => null],
        ['type' => 'generic'],
    ]]])]);

    $output = keyedOutput('hunter_domain_search', ['domain' => 'https://www.Acme.com/about', 'type' => 'personal', 'department' => 'it', 'limit' => 5]);

    expect($output)->toMatchArray(['count' => 1, 'organization' => 'Acme', 'pattern' => '{first}.{last}', 'domain' => 'acme.com'])
        ->and($output['emails'][0])->toMatchArray(['email' => 'ada.l@acme.com', 'confidence' => 96, 'position' => 'CTO']);

    Http::assertSent(fn (Request $request): bool => $request->header('X-API-KEY') === ['test-key'] && $request['domain'] === 'acme.com' && $request['type'] === 'personal' && $request['department'] === 'it' && $request['limit'] === 5);
});

it('finds a person’s email with Hunter and says when there is none', function () {
    Http::fake([
        'api.hunter.io/v2/email-finder*' => Http::sequence()
            ->push(['data' => ['email' => 'ada@acme.com', 'score' => 91, 'position' => 'CTO', 'verification' => ['status' => 'valid']]])
            ->push(['data' => ['email' => null, 'score' => null]]),
    ]);

    $found = keyedOutput('hunter_find_email', ['domain' => 'ada@acme.com', 'first_name' => 'Ada', 'last_name' => 'L']);
    $none = keyedOutput('hunter_find_email', ['domain' => 'acme.com', 'full_name' => 'Nobody Here']);

    expect($found)->toMatchArray(['email' => 'ada@acme.com', 'found' => true, 'result' => 'found', 'confidence' => 91, 'verification' => 'valid'])
        ->and($none)->toMatchArray(['email' => null, 'found' => false, 'result' => 'none']);
});

it('verifies an email with Hunter and treats an unclear answer as risky', function (array $data, string $result, bool $deliverable) {
    Http::fake(['api.hunter.io/v2/email-verifier*' => Http::response(['data' => $data])]);

    expect(keyedOutput('hunter_verify_email', ['email' => 'a@b.com']))->toMatchArray(['result' => $result, 'deliverable' => $deliverable]);
})->with([
    'deliverable' => [['status' => 'valid', 'result' => 'deliverable', 'score' => 95], 'deliverable', true],
    'undeliverable' => [['status' => 'invalid', 'result' => 'undeliverable', 'score' => 0], 'undeliverable', false],
    'risky' => [['status' => 'accept_all', 'result' => 'risky', 'accept_all' => true], 'risky', false],
    'unknown wording' => [['status' => 'unknown', 'result' => 'maybe'], 'risky', false],
]);

it('looks a company up with Hunter', function () {
    Http::fake(['api.hunter.io/v2/companies/find*' => Http::response(['data' => ['name' => 'Acme', 'description' => 'Rockets', 'category' => ['industry' => 'Aerospace'], 'metrics' => ['employeesRange' => '51-200'], 'geo' => ['country' => 'United States', 'city' => 'Austin'], 'linkedin' => ['handle' => 'company/acme']]])]);

    expect(keyedOutput('hunter_find_company', ['domain' => 'acme.com']))->toMatchArray(['found' => true, 'name' => 'Acme', 'industry' => 'Aerospace', 'size' => '51-200', 'city' => 'Austin', 'linkedin' => 'https://www.linkedin.com/company/acme']);
});

it('refuses a bad domain, name or email for Hunter before calling it', function () {
    Http::fake();

    expect(fn () => executeNode('hunter_domain_search', ['access_token' => 'k', 'domain' => 'not a domain']))->toThrow(InvalidArgumentException::class, 'company domain')
        ->and(fn () => executeNode('hunter_find_email', ['access_token' => 'k', 'domain' => 'acme.com', 'first_name' => 'Ada']))->toThrow(InvalidArgumentException::class, 'first and last name')
        ->and(fn () => executeNode('hunter_verify_email', ['access_token' => 'k', 'email' => 'nope']))->toThrow(InvalidArgumentException::class, 'not an email');

    Http::assertNothingSent();
});

/* ------------------------------------------------------------------- Apollo */

it('enriches a person with Apollo, sending the key in its header', function () {
    Http::fake(['api.apollo.io/*' => Http::response(['person' => ['name' => 'Ada L', 'title' => 'CTO', 'email' => 'ada@acme.com', 'linkedin_url' => 'https://linkedin.test/ada', 'city' => 'Austin', 'state' => 'Texas', 'country' => 'United States', 'seniority' => 'c_suite', 'organization' => ['name' => 'Acme', 'primary_domain' => 'acme.com']]])]);

    $output = keyedOutput('apollo_enrich_person', ['email' => 'ada@acme.com']);

    expect($output)->toMatchArray(['found' => true, 'result' => 'found', 'name' => 'Ada L', 'title' => 'CTO', 'company' => 'Acme', 'company_domain' => 'acme.com', 'location' => 'Austin, Texas, United States']);

    Http::assertSent(fn (Request $request): bool => $request->header('x-api-key') === ['test-key'] && $request['email'] === 'ada@acme.com');
});

it('matches an Apollo person by name and company domain, and reports no match', function () {
    Http::fake(['api.apollo.io/*' => Http::response(['person' => null])]);

    $output = keyedOutput('apollo_enrich_person', ['first_name' => 'Ada', 'last_name' => 'L', 'company' => 'https://www.acme.com/']);

    expect($output)->toMatchArray(['found' => false, 'result' => 'none']);

    Http::assertSent(fn (Request $request): bool => $request['first_name'] === 'Ada' && $request['domain'] === 'acme.com' && ! isset($request['organization_name']));
});

it('enriches a company with Apollo', function () {
    Http::fake(['api.apollo.io/*' => Http::response(['organization' => ['name' => 'Acme', 'short_description' => 'Rockets', 'industry' => 'aerospace', 'estimated_num_employees' => 120, 'organization_revenue_printed' => '10M', 'city' => 'Austin', 'country' => 'United States', 'founded_year' => 2010, 'website_url' => 'http://acme.com', 'current_technologies' => [['name' => 'Salesforce'], 'AWS']]])]);

    expect(keyedOutput('apollo_enrich_company', ['domain' => 'acme.com']))->toMatchArray(['found' => true, 'name' => 'Acme', 'employees' => 120, 'revenue' => '10M', 'technologies' => ['Salesforce', 'AWS']]);
});

it('refuses an Apollo lookup with too little to go on', function () {
    Http::fake();

    expect(fn () => executeNode('apollo_enrich_person', ['access_token' => 'k']))->toThrow(InvalidArgumentException::class, 'Give an email')
        ->and(fn () => executeNode('apollo_enrich_person', ['access_token' => 'k', 'first_name' => 'Ada']))->toThrow(InvalidArgumentException::class)
        ->and(fn () => executeNode('apollo_enrich_person', ['access_token' => 'k', 'email' => 'bad']))->toThrow(InvalidArgumentException::class, 'not an email')
        ->and(fn () => executeNode('apollo_enrich_company', ['access_token' => 'k', 'domain' => 'nope']))->toThrow(InvalidArgumentException::class, 'company domain');

    Http::assertNothingSent();
});

/* -------------------------------------------------------------- Google Maps */

it('searches places, sending the key and the fields it wants in headers', function () {
    Http::fake(['places.googleapis.com/*' => Http::response(['places' => [[
        'id' => 'ChIJabc', 'displayName' => ['text' => 'Cafe Uno'], 'formattedAddress' => '1 High St, Leeds', 'location' => ['latitude' => 53.8, 'longitude' => -1.5],
        'rating' => 4.6, 'userRatingCount' => 210, 'websiteUri' => 'https://cafe.test', 'internationalPhoneNumber' => '+44 113 000 0000', 'googleMapsUri' => 'https://maps.test/x',
        'businessStatus' => 'OPERATIONAL', 'primaryTypeDisplayName' => ['text' => 'Cafe'], 'types' => ['cafe', 'food'],
    ]]])]);

    $output = keyedOutput('google_maps_search_places', ['query' => 'cafes in Leeds', 'max_results' => 5, 'min_rating' => 4, 'open_now' => true]);

    expect($output['count'])->toBe(1)
        ->and($output['places'][0])->toMatchArray(['id' => 'ChIJabc', 'name' => 'Cafe Uno', 'rating' => 4.6, 'phone' => '+44 113 000 0000', 'category' => 'Cafe', 'types' => ['cafe', 'food']]);

    Http::assertSent(fn (Request $request): bool => $request->header('X-Goog-Api-Key') === ['test-key']
        && str_contains($request->header('X-Goog-FieldMask')[0], 'places.displayName')
        && $request['textQuery'] === 'cafes in Leeds' && $request['maxResultCount'] === 5 && $request['minRating'] === 4 && $request['openNow'] === true);
});

it('gets one place and refuses a malformed place ID', function () {
    Http::fake(['places.googleapis.com/v1/places/*' => Http::response(['id' => 'ChIJabcdefghij', 'displayName' => ['text' => 'Cafe Uno'], 'formattedAddress' => '1 High St', 'rating' => 4.6])]);

    $output = keyedOutput('google_maps_get_place', ['place_id' => 'ChIJabcdefghij']);

    expect($output)->toMatchArray(['name' => 'Cafe Uno', 'address' => '1 High St', 'rating' => 4.6])
        ->and(fn () => executeNode('google_maps_get_place', ['access_token' => 'k', 'place_id' => '../../etc']))->toThrow(InvalidArgumentException::class, 'place ID');
});

it('turns an address into coordinates and tidy parts', function () {
    Http::fake(['maps.googleapis.com/*' => Http::response(['status' => 'OK', 'results' => [[
        'formatted_address' => '1600 Amphitheatre Pkwy, Mountain View, CA 94043, USA', 'place_id' => 'ChIJxyz',
        'geometry' => ['location' => ['lat' => 37.42, 'lng' => -122.08], 'location_type' => 'ROOFTOP'],
        'address_components' => [
            ['long_name' => '1600', 'short_name' => '1600', 'types' => ['street_number']],
            ['long_name' => 'Amphitheatre Parkway', 'short_name' => 'Amphitheatre Pkwy', 'types' => ['route']],
            ['long_name' => 'Mountain View', 'short_name' => 'Mountain View', 'types' => ['locality']],
            ['long_name' => 'California', 'short_name' => 'CA', 'types' => ['administrative_area_level_1']],
            ['long_name' => '94043', 'short_name' => '94043', 'types' => ['postal_code']],
            ['long_name' => 'United States', 'short_name' => 'US', 'types' => ['country']],
        ],
    ]]])]);

    $output = keyedOutput('google_maps_geocode', ['address' => '1600 Amphitheatre Parkway']);

    expect($output)->toMatchArray(['found' => true, 'latitude' => 37.42, 'longitude' => -122.08, 'street' => '1600 Amphitheatre Parkway', 'city' => 'Mountain View', 'region' => 'California', 'postal_code' => '94043', 'country' => 'United States', 'country_code' => 'US', 'accuracy' => 'ROOFTOP']);

    Http::assertSent(fn (Request $request): bool => $request['address'] === '1600 Amphitheatre Parkway' && $request['key'] === 'test-key');
});

it('looks up coordinates in reverse, reports no match, and explains a refused key', function () {
    Http::fake(['maps.googleapis.com/*' => Http::sequence()
        ->push(['status' => 'OK', 'results' => [['formatted_address' => 'Somewhere', 'geometry' => ['location' => ['lat' => 1, 'lng' => 2]], 'address_components' => []]]])
        ->push(['status' => 'ZERO_RESULTS', 'results' => []])
        ->push(['status' => 'REQUEST_DENIED', 'error_message' => 'The provided API key is invalid.', 'results' => []])]);

    $reverse = keyedOutput('google_maps_geocode', ['latitude' => '51.5', 'longitude' => '-0.12']);
    $none = keyedOutput('google_maps_geocode', ['address' => 'zzzzzz']);

    expect($reverse['formatted_address'])->toBe('Somewhere')
        ->and($none)->toMatchArray(['found' => false, 'result' => 'none'])
        ->and(fn () => executeNode('google_maps_geocode', ['access_token' => 'k', 'address' => 'x']))->toThrow(RuntimeException::class, 'refused the API key');

    Http::assertSent(fn (Request $request): bool => ($request['latlng'] ?? null) === '51.5,-0.12');
});

it('refuses a geocode with nothing to look up or impossible coordinates', function () {
    Http::fake();

    expect(fn () => executeNode('google_maps_geocode', ['access_token' => 'k']))->toThrow(InvalidArgumentException::class, 'address, or both')
        ->and(fn () => executeNode('google_maps_geocode', ['access_token' => 'k', 'latitude' => '95', 'longitude' => '0']))->toThrow(InvalidArgumentException::class, 'between -90 and 90')
        ->and(fn () => executeNode('google_maps_search_places', ['access_token' => 'k', 'query' => ' ']))->toThrow(InvalidArgumentException::class);

    Http::assertNothingSent();
});

/* ------------------------------------------------------------- shared errors */

it('turns the failures every keyed API has into a sentence naming the service', function (string $type, array $config, int $status, string $expected) {
    Http::fake(['*' => Http::response(['message' => 'Details here'], $status)]);

    expect(fn () => executeNode($type, ['access_token' => 'k', ...$config]))->toThrow(RuntimeException::class, $expected);
})->with([
    'Brave refuses the key' => ['brave_search_web', ['query' => 'x'], 401, 'Brave Search refused the API key'],
    'Tavily out of credit' => ['tavily_search', ['query' => 'x'], 402, 'Tavily says the account has no credit left'],
    'Hunter rate limited' => ['hunter_find_company', ['domain' => 'acme.com'], 429, 'Hunter is limiting requests'],
    'Apollo rejects the request' => ['apollo_enrich_company', ['domain' => 'acme.com'], 422, 'Apollo rejected the request: Details here'],
    'Maps server error' => ['google_maps_search_places', ['query' => 'x'], 500, 'Google Maps returned an error (HTTP 500)'],
]);

it('says the account is not connected when there is no key', function () {
    $nodeRun = runNodeStep('brave_search_web', ['query' => 'x']);

    expect($nodeRun->status)->toBe(NodeRunStatus::Failed);
});

it('registers the research and enrichment nodes under their own app categories', function () {
    $expected = [
        'brave_search' => ['brave_search_web', 'brave_search_news'],
        'tavily' => ['tavily_search', 'tavily_extract'],
        'hunter' => ['hunter_domain_search', 'hunter_find_email', 'hunter_verify_email', 'hunter_find_company'],
        'apollo' => ['apollo_enrich_person', 'apollo_enrich_company'],
        'maps_platform' => ['google_maps_search_places', 'google_maps_get_place', 'google_maps_geocode'],
    ];

    foreach ($expected as $category => $types) {
        foreach ($types as $type) {
            $node = app(NodeRegistry::class)->resolve($type);

            expect($node->category())->toBe($category)
                ->and($node->type())->toBe($type)
                ->and($node->configSchema()['properties'])->toHaveKey('credential_id')
                ->and($node->outputSchema()['properties'])->not->toBeEmpty();
        }
    }
});
