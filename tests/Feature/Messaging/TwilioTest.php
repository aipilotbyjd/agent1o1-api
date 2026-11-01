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
use App\Nodes\Integrations\Twilio\AbstractTwilioNode;
use App\Nodes\Integrations\Twilio\TwilioCheckVerificationNode;
use App\Nodes\Integrations\Twilio\TwilioListCallsNode;
use App\Nodes\Integrations\Twilio\TwilioListFailedMessagesNode;
use App\Nodes\Integrations\Twilio\TwilioListMessagesNode;
use App\Nodes\Integrations\Twilio\TwilioListNewIncomingMessagesNode;
use App\Nodes\Integrations\Twilio\TwilioLookupPhoneNumberNode;
use App\Nodes\Integrations\Twilio\TwilioMakeCallNode;
use App\Nodes\Integrations\Twilio\TwilioSendSmsNode;
use App\Nodes\Integrations\Twilio\TwilioSendWhatsAppNode;
use App\Nodes\Integrations\Twilio\TwilioStartVerificationNode;
use App\Nodes\Integrations\Twilio\TwilioUpdateCallNode;
use App\Services\Triggers\Push\TwilioSource;
use App\Services\Triggers\Push\WatchManager;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use App\Services\Workflows\NodeOptions\Sources\TwilioOptions;
use App\Services\Workflows\NodeRegistry;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

const TWILIO_SID = 'AC11111111111111111111111111111111';
const TWILIO_TOKEN = 'twilio-auth-token';
const TWILIO_NUMBER_SID = 'PN22222222222222222222222222222222';

/**
 * A run with a connected Twilio account in its workspace.
 */
function twilioRun(): Run
{
    $run = Run::factory()->create();

    ConnectorCredential::factory()->create([
        'workspace_id' => $run->workspace_id,
        'connector_id' => (Connector::query()->firstWhere('key', 'twilio') ?? Connector::factory()->create(['key' => 'twilio']))->id,
        'data' => ['account_sid' => TWILIO_SID, 'api_key' => TWILIO_TOKEN],
        'is_default' => true,
    ]);

    return $run;
}

/**
 * Answer Twilio. A key is "METHOD /path": the path under the account for the
 * REST API (`GET /Messages.json`), or the service and path for the others
 * (`POST verify /v2/Services/VA…/Verifications`). The value is the JSON body,
 * a closure taking the request, or a ready response; a route nobody answers is
 * a 404, so a node that calls something unexpected fails the test.
 *
 * @param  array<string, mixed>  $routes
 */
function fakeTwilio(array $routes): void
{
    Http::swap(new Factory);
    Http::fake(['*.twilio.com/*' => function (Request $request) use ($routes) {
        $host = (string) parse_url($request->url(), PHP_URL_HOST);
        $path = (string) parse_url($request->url(), PHP_URL_PATH);
        $key = $request->method().' '.($host === 'api.twilio.com'
            ? (string) preg_replace('~^/2010-04-01/Accounts/'.TWILIO_SID.'~', '', $path)
            : explode('.', $host)[0].' '.$path);

        if (! array_key_exists($key, $routes)) {
            return Http::response(['code' => 20404, 'message' => "Nothing fakes {$key}", 'status' => 404], 404);
        }

        $answer = value($routes[$key], $request);

        return $answer instanceof GuzzleHttp\Promise\PromiseInterface ? $answer : ($answer === null ? Http::response(null, 204) : Http::response($answer));
    }]);
}

/**
 * The form fields sent on the one request to a route.
 *
 * @return array<string, string>
 */
function twilioSent(string $route): array
{
    $found = array_values(array_filter(Http::recorded()->all(), function (array $pair) use ($route): bool {
        $host = (string) parse_url($pair[0]->url(), PHP_URL_HOST);
        $path = (string) parse_url($pair[0]->url(), PHP_URL_PATH);

        return $pair[0]->method().' '.($host === 'api.twilio.com' ? (string) preg_replace('~^/2010-04-01/Accounts/'.TWILIO_SID.'~', '', $path) : explode('.', $host)[0].' '.$path) === $route;
    }));

    expect($found)->toHaveCount(1);

    parse_str($found[0][0]->body(), $fields);

    return $fields;
}

/**
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function twilioMessage(array $extra = []): array
{
    return [
        'sid' => 'SM33333333333333333333333333333333', 'status' => 'queued', 'direction' => 'outbound-api', 'from' => '+14155550123', 'to' => '+14155550199', 'body' => 'Hello',
        'num_segments' => '1', 'num_media' => '0', 'price' => null, 'date_created' => 'Sun, 01 Mar 2026 10:00:00 +0000', 'date_sent' => null, ...$extra,
    ];
}

describe('twilio nodes', function () {
    it('registers the nodes under the twilio connector', function () {
        $registry = app(NodeRegistry::class);
        $types = array_filter(array_column($registry->placeableTypes(), 'type'), fn (string $type): bool => str_starts_with($type, 'twilio_'));

        expect(count($types))->toBeGreaterThanOrEqual(23);

        foreach ($types as $type) {
            expect($registry->resolve($type))->toBeInstanceOf(AbstractTwilioNode::class)->and($registry->resolve($type)->category())->toBe('twilio');
        }
    });

    it('sends a text with basic auth, form encoding and repeated media', function () {
        fakeTwilio(['POST /Messages.json' => twilioMessage()]);

        $output = (new TwilioSendSmsNode)->execute(twilioRun(), ['from' => '+1 (415) 555-0123', 'to' => '+14155550199', 'body' => 'Hello', 'media_urls' => ['https://x.test/a.jpg', 'https://x.test/b.jpg']], []);

        expect($output)->toMatchArray(['sid' => 'SM33333333333333333333333333333333', 'status' => 'queued', 'to' => '+14155550199', 'channel' => 'sms', 'num_segments' => 1, 'date_created' => '2026-03-01T10:00:00+00:00']);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Basic '.base64_encode(TWILIO_SID.':'.TWILIO_TOKEN))
            && $request->body() === 'To=%2B14155550199&From=%2B14155550123&Body=Hello&MediaUrl=https%3A%2F%2Fx.test%2Fa.jpg&MediaUrl=https%3A%2F%2Fx.test%2Fb.jpg');
    });

    it('sends through a Messaging Service instead of a number, and never both', function () {
        fakeTwilio(['POST /Messages.json' => twilioMessage()]);
        $node = new TwilioSendSmsNode;

        $node->execute(twilioRun(), ['messaging_service_sid' => 'MG44444444444444444444444444444444', 'to' => '+14155550199', 'body' => 'Hi'], []);

        expect(twilioSent('POST /Messages.json'))->toBe(['To' => '+14155550199', 'MessagingServiceSid' => 'MG44444444444444444444444444444444', 'Body' => 'Hi'])
            ->and(fn () => $node->execute(twilioRun(), ['from' => '+14155550123', 'messaging_service_sid' => 'MG44444444444444444444444444444444', 'to' => '+14155550199', 'body' => 'x'], []))->toThrow(InvalidArgumentException::class, 'not both')
            ->and(fn () => $node->execute(twilioRun(), ['to' => '+14155550199', 'body' => 'x'], []))->toThrow(InvalidArgumentException::class, 'Choose a From number');
    });

    it('checks numbers and text before a message is charged for', function () {
        fakeTwilio([]);
        $node = new TwilioSendSmsNode;
        $run = twilioRun();

        expect(fn () => $node->execute($run, ['from' => '+14155550123', 'to' => '(415) 555-0199', 'body' => 'x'], []))->toThrow(InvalidArgumentException::class, 'international format')
            ->and(fn () => $node->execute($run, ['from' => '+14155550123', 'to' => '+14155550199'], []))->toThrow(InvalidArgumentException::class, 'some text or media')
            ->and(fn () => $node->execute($run, ['from' => '+14155550123', 'to' => '+14155550199', 'body' => str_repeat('x', 1601)], []))->toThrow(InvalidArgumentException::class, '1600 characters')
            ->and(fn () => $node->execute($run, ['from' => '+14155550123', 'to' => '+14155550199', 'body' => 'x', 'media_urls' => ['ftp://x']], []))->toThrow(InvalidArgumentException::class, 'must start with http');
        Http::assertNothingSent();
    });

    it('needs both halves of the connection', function () {
        $run = Run::factory()->create();
        ConnectorCredential::factory()->create([
            'workspace_id' => $run->workspace_id,
            'connector_id' => (Connector::query()->firstWhere('key', 'twilio') ?? Connector::factory()->create(['key' => 'twilio']))->id,
            'data' => ['api_key' => 'token-only'],
            'is_default' => true,
        ]);

        expect(fn () => (new TwilioSendSmsNode)->execute($run, ['from' => '+14155550123', 'to' => '+14155550199', 'body' => 'x'], []))->toThrow(InvalidArgumentException::class, 'Account SID');
    });

    it('says what Twilio said when it refuses', function () {
        fakeTwilio(['POST /Messages.json' => fn () => Http::response(['code' => 21211, 'message' => "The 'To' number +1415 is not a valid phone number.", 'status' => 400], 400)]);

        expect(fn () => (new TwilioSendSmsNode)->execute(twilioRun(), ['from' => '+14155550123', 'to' => '+14155550199', 'body' => 'x'], []))
            ->toThrow(RuntimeException::class, "Twilio error 21211: The 'To' number +1415 is not a valid phone number.");
    });

    it('sends WhatsApp with the channel prefix, or as a template with variables', function () {
        fakeTwilio(['POST /Messages.json' => twilioMessage(['to' => 'whatsapp:+14155550199', 'from' => 'whatsapp:+14155238886'])]);
        $node = new TwilioSendWhatsAppNode;

        $output = $node->execute(twilioRun(), ['from' => '+14155238886', 'to' => '+14155550199', 'body' => 'Hi'], []);

        expect($output['channel'])->toBe('whatsapp')
            ->and(twilioSent('POST /Messages.json'))->toBe(['To' => 'whatsapp:+14155550199', 'From' => 'whatsapp:+14155238886', 'Body' => 'Hi']);

        fakeTwilio(['POST /Messages.json' => twilioMessage()]);
        $node->execute(twilioRun(), ['from' => 'whatsapp:+14155238886', 'to' => 'whatsapp:+14155550199', 'body' => 'ignored', 'content_sid' => 'HX55555555555555555555555555555555', 'content_variables' => ['1' => 'Ada']], []);

        expect(twilioSent('POST /Messages.json'))->toBe(['To' => 'whatsapp:+14155550199', 'From' => 'whatsapp:+14155238886', 'ContentSid' => 'HX55555555555555555555555555555555', 'ContentVariables' => '{"1":"Ada"}']);
    });

    it('lists messages page by page, and filters direction and status itself', function () {
        $page = fn (array $messages, ?string $next) => ['messages' => $messages, 'next_page_uri' => $next];
        fakeTwilio(['GET /Messages.json' => function (Request $request) use ($page) {
            return str_contains($request->url(), 'PageToken=2')
                ? $page([twilioMessage(['sid' => 'SM3333333333333333333333333333333c', 'direction' => 'inbound', 'status' => 'received'])], null)
                : $page([twilioMessage(['sid' => 'SM3333333333333333333333333333333a', 'direction' => 'inbound', 'status' => 'received']), twilioMessage(['sid' => 'SM3333333333333333333333333333333b'])], '/2010-04-01/Accounts/'.TWILIO_SID.'/Messages.json?PageSize=5&PageToken=2');
        }]);

        $output = (new TwilioListMessagesNode)->execute(twilioRun(), ['direction' => 'inbound', 'to' => '+14155550123', 'sent_after' => '2026-03-01 00:00:00', 'limit' => 5], []);

        expect(array_column($output['messages'], 'sid'))->toBe(['SM3333333333333333333333333333333a', 'SM3333333333333333333333333333333c']);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'To=%2B14155550123') && str_contains(urldecode($request->url()), 'DateSent>=2026-03-01T00:00:00'));
    });

    it('speaks, plays, runs TwiML or fetches it, escaping what a person typed', function () {
        fakeTwilio(['POST /Calls.json' => ['sid' => 'CA66666666666666666666666666666666', 'status' => 'queued', 'to' => '+14155550199', 'from' => '+14155550123']]);
        $node = new TwilioMakeCallNode;
        $run = twilioRun();

        $node->execute($run, ['from' => '+14155550123', 'to' => '+14155550199', 'say' => 'Tom & Jerry <3', 'timeout' => 20, 'record' => true], []);
        expect(twilioSent('POST /Calls.json'))->toBe(['To' => '+14155550199', 'From' => '+14155550123', 'Timeout' => '20', 'Record' => 'true', 'Twiml' => '<Response><Say voice="Polly.Joanna" language="en-US">Tom &amp; Jerry &lt;3</Say></Response>']);

        fakeTwilio(['POST /Calls.json' => ['sid' => 'CA66666666666666666666666666666666']]);
        $node->execute($run, ['from' => '+14155550123', 'to' => '+14155550199', 'mode' => 'url', 'twiml_url' => 'https://x.test/voice.xml'], []);
        expect(twilioSent('POST /Calls.json')['Url'])->toBe('https://x.test/voice.xml')
            ->and(fn () => $node->execute($run, ['from' => '+14155550123', 'to' => '+14155550199', 'mode' => 'twiml', 'twiml' => '<Say>no</Say>'], []))->toThrow(InvalidArgumentException::class, 'must start with <Response>')
            ->and(fn () => $node->execute($run, ['from' => '+14155550123', 'to' => '+14155550199', 'mode' => 'url', 'twiml_url' => 'ftp://x'], []))->toThrow(InvalidArgumentException::class, 'must start with http');
    });

    it('hangs up, cancels or redirects a call', function () {
        $sid = 'CA66666666666666666666666666666666';
        fakeTwilio(["POST /Calls/{$sid}.json" => ['sid' => $sid, 'status' => 'completed']]);
        $node = new TwilioUpdateCallNode;

        $node->execute(twilioRun(), ['call_sid' => $sid], []);
        expect(twilioSent("POST /Calls/{$sid}.json"))->toBe(['Status' => 'completed']);

        fakeTwilio(["POST /Calls/{$sid}.json" => ['sid' => $sid]]);
        $node->execute(twilioRun(), ['call_sid' => $sid, 'action' => 'redirect', 'twiml_url' => 'https://x.test/next.xml'], []);
        expect(twilioSent("POST /Calls/{$sid}.json"))->toBe(['Url' => 'https://x.test/next.xml'])
            ->and(fn () => $node->execute(twilioRun(), ['call_sid' => 'nope'], []))->toThrow(InvalidArgumentException::class, 'is not a Call SID')
            ->and(fn () => $node->execute(twilioRun(), ['call_sid' => $sid, 'action' => 'redirect'], []))->toThrow(InvalidArgumentException::class, 'needs TwiML');
    });

    it('lists calls and keeps only the direction asked for', function () {
        fakeTwilio(['GET /Calls.json' => ['calls' => [
            ['sid' => 'CA6666666666666666666666666666666a', 'direction' => 'inbound', 'status' => 'completed', 'duration' => '42', 'price' => '-0.0085', 'start_time' => 'Sun, 01 Mar 2026 10:00:00 +0000'],
            ['sid' => 'CA6666666666666666666666666666666b', 'direction' => 'outbound-api', 'status' => 'completed'],
        ]]]);

        $output = (new TwilioListCallsNode)->execute(twilioRun(), ['direction' => 'inbound', 'status' => 'completed'], []);

        expect($output['count'])->toBe(1)->and($output['calls'][0])->toMatchArray(['sid' => 'CA6666666666666666666666666666666a', 'duration_seconds' => 42, 'price' => -0.0085, 'start_time' => '2026-03-01T10:00:00+00:00']);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'Status=completed'));
    });

    it('looks a number up, with the paid extras only when asked', function () {
        fakeTwilio(['GET lookups /v2/PhoneNumbers/%2B14155550199' => ['phone_number' => '+14155550199', 'national_format' => '(415) 555-0199', 'country_code' => 'US', 'valid' => true, 'validation_errors' => null, 'line_type_intelligence' => ['type' => 'mobile', 'carrier_name' => 'Acme Wireless']]]);
        $node = new TwilioLookupPhoneNumberNode;

        $plain = $node->execute(twilioRun(), ['phone_number' => '+1 415 555 0199'], []);
        Http::assertSent(fn (Request $request): bool => ! str_contains($request->url(), 'Fields='));

        $rich = $node->execute(twilioRun(), ['phone_number' => '+14155550199', 'include_line_type' => true], []);

        expect($plain)->toMatchArray(['valid' => true, 'national_format' => '(415) 555-0199', 'validation_errors' => []])
            ->and($rich)->toMatchArray(['line_type' => 'mobile', 'carrier' => 'Acme Wireless'])
            ->and(fn () => $node->execute(twilioRun(), ['phone_number' => 'call me'], []))->toThrow(InvalidArgumentException::class, 'look like a phone number');
        Http::assertSent(fn (Request $request): bool => str_contains(urldecode($request->url()), 'Fields=line_type_intelligence'));
    });

    it('sends a verification code, and reads a right, wrong or expired one', function () {
        $service = 'VA77777777777777777777777777777777';
        fakeTwilio(["POST verify /v2/Services/{$service}/Verifications" => ['sid' => 'VE88888888888888888888888888888888', 'status' => 'pending', 'channel' => 'sms', 'to' => '+14155550199']]);

        $started = (new TwilioStartVerificationNode)->execute(twilioRun(), ['service_sid' => $service, 'to' => '+14155550199', 'channel' => 'sms', 'locale' => 'fr'], []);

        expect($started)->toMatchArray(['status' => 'pending', 'channel' => 'sms'])
            ->and(twilioSent("POST verify /v2/Services/{$service}/Verifications"))->toBe(['To' => '+14155550199', 'Channel' => 'sms', 'Locale' => 'fr']);

        $check = new TwilioCheckVerificationNode;
        $config = ['service_sid' => $service, 'to' => '+14155550199', 'code' => '123456'];

        fakeTwilio(["POST verify /v2/Services/{$service}/VerificationCheck" => ['status' => 'approved', 'to' => '+14155550199']]);
        expect($check->execute(twilioRun(), $config, [])['valid'])->toBeTrue();

        fakeTwilio(["POST verify /v2/Services/{$service}/VerificationCheck" => ['status' => 'pending', 'to' => '+14155550199']]);
        expect($check->execute(twilioRun(), $config, [])['valid'])->toBeFalse();

        fakeTwilio(["POST verify /v2/Services/{$service}/VerificationCheck" => fn () => Http::response(['code' => 20404, 'message' => 'The requested resource was not found', 'status' => 404], 404)]);
        expect($check->execute(twilioRun(), $config, []))->toMatchArray(['valid' => false, 'status' => 'not_found'])
            ->and(fn () => $check->execute(twilioRun(), [...$config, 'code' => 'abcd'], []))->toThrow(InvalidArgumentException::class, '4 to 10 digits');
    });
});

describe('twilio dropdowns', function () {
    it('lists the account\'s numbers and services with the connection\'s account SID', function () {
        Http::swap(new Factory);
        Http::fake([
            'api.twilio.com/*' => Http::response(['incoming_phone_numbers' => [['phone_number' => '+14155550123', 'friendly_name' => '(415) 555-0123'], ['phone_number' => '+14155550124', 'friendly_name' => 'Support line']]]),
            'verify.twilio.com/*' => Http::response(['services' => [['sid' => 'VA77777777777777777777777777777777', 'friendly_name' => 'Login codes']]]),
        ]);
        $query = fn (array $credentialData = ['account_sid' => TWILIO_SID], ?string $search = null): NodeOptionsQuery => new NodeOptionsQuery(app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']), User::factory()->create(), [], TWILIO_TOKEN, $search, null, $credentialData);
        $source = app(TwilioOptions::class);

        expect($source->load('twilio.numbers', $query())->toArray()['options'])->toBe([
            ['value' => '+14155550123', 'label' => '+14155550123', 'description' => '(415) 555-0123'],
            ['value' => '+14155550124', 'label' => '+14155550124', 'description' => 'Support line'],
        ])
            ->and(array_column($source->load('twilio.numbers', $query(search: 'support'))->toArray()['options'], 'value'))->toBe(['+14155550124'])
            ->and($source->load('twilio.verify_services', $query())->toArray()['options'][0])->toMatchArray(['value' => 'VA77777777777777777777777777777777', 'label' => 'Login codes'])
            ->and(fn () => $source->load('twilio.numbers', $query([])))->toThrow(App\Exceptions\ConnectorException::class, 'Account SID');
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Basic '.base64_encode(TWILIO_SID.':'.TWILIO_TOKEN)));
    });
});

/**
 * A published workflow whose first step is a Twilio reader node switched on as a
 * trigger, with its subscription synced against a faked Twilio.
 *
 * @param  array<string, mixed>  $nodeConfig
 * @param  array<string, mixed>  $routes
 */
function twilioTrigger(string $nodeType, array $nodeConfig = [], array $routes = [], bool $sync = true): Trigger
{
    Queue::fake([FireTriggerEvent::class, PollTrigger::class]);
    fakeTwilio($routes);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $credential = ConnectorCredential::factory()->forWorkspace($workspace)
        ->forConnector(Connector::query()->firstWhere('key', 'twilio') ?? Connector::factory()->create(['key' => 'twilio']))
        ->create(['data' => ['account_sid' => TWILIO_SID, 'api_key' => TWILIO_TOKEN], 'created_by' => $owner->id]);

    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'start', 'type' => $nodeType, 'config' => ['credential_id' => $credential->id, 'activate' => true, ...$nodeConfig]],
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
 * The routes of an account with one number that answers texts at `$smsUrl`.
 *
 * @param  array<string, mixed>  $number
 * @return array<string, mixed>
 */
function twilioNumberRoutes(string $smsUrl = 'https://demo.twilio.com/welcome/sms/reply/', array $number = []): array
{
    $record = ['sid' => TWILIO_NUMBER_SID, 'phone_number' => '+14155550123', 'sms_url' => $smsUrl, 'sms_method' => 'POST', 'sms_application_sid' => '', ...$number];

    return [
        'GET /IncomingPhoneNumbers.json' => ['incoming_phone_numbers' => [$record]],
        'GET /IncomingPhoneNumbers/'.TWILIO_NUMBER_SID.'.json' => $record,
        'POST /IncomingPhoneNumbers/'.TWILIO_NUMBER_SID.'.json' => $record,
    ];
}

/**
 * A text as Twilio posts it to the number's URL, signed with the account's token.
 *
 * @param  array<string, string>  $overrides
 */
function twilioDelivery(array $overrides = [], ?string $token = null, ?string $url = null)
{
    $fields = ['MessageSid' => 'SM99999999999999999999999999999999', 'AccountSid' => TWILIO_SID, 'From' => '+14155550199', 'To' => '+14155550123', 'Body' => 'Hello there', 'NumMedia' => '0', 'FromCity' => 'SAN FRANCISCO', ...$overrides];
    $url ??= url('/api/hooks/apps/twilio');
    ksort($fields);
    $signature = base64_encode(hash_hmac('sha1', $url.collect($fields)->map(fn (string $value, string $key): string => $key.$value)->implode(''), $token ?? TWILIO_TOKEN, true));

    return test()->call('POST', '/api/hooks/apps/twilio', $fields, [], [], ['HTTP_X_TWILIO_SIGNATURE' => $signature]);
}

describe('twilio triggers', function () {
    it('polls received messages: a baseline first, then one run for each new one, with its pictures', function () {
        Queue::fake([FireTriggerEvent::class]);
        $trigger = twilioTrigger('twilio_list_new_incoming_messages', ['channel' => 'sms'], sync: false);
        $poll = fn (array $messages, array $more = []) => fakeTwilio(['GET /Messages.json' => ['messages' => $messages], ...$more]);
        $received = fn (string $sid, array $extra = []) => twilioMessage(['sid' => $sid, 'direction' => 'inbound', 'status' => 'received', ...$extra]);

        $poll([$received('SM3333333333333333333333333333333a')]);
        dispatch_sync(new PollTrigger($trigger));
        expect($trigger->events()->where('status', 'queued')->count())->toBe(0);

        $poll([
            $received('SM3333333333333333333333333333333c', ['num_media' => '1']),
            twilioMessage(['sid' => 'SM3333333333333333333333333333333b']),
            $received('SM3333333333333333333333333333333a'),
        ], ['GET /Messages/SM3333333333333333333333333333333c/Media.json' => ['media_list' => [['sid' => 'ME1', 'uri' => '/2010-04-01/Accounts/'.TWILIO_SID.'/Messages/SM3333333333333333333333333333333c/Media/ME1.json', 'content_type' => 'image/jpeg']]]]);
        dispatch_sync(new PollTrigger($trigger->fresh()));

        $events = $trigger->events()->where('status', 'queued')->get();

        expect($events)->toHaveCount(1)
            ->and($events->first()->payload)->toMatchArray(['sid' => 'SM3333333333333333333333333333333c', 'direction' => 'inbound', 'media_types' => ['image/jpeg']])
            ->and($events->first()->payload['media_urls'])->toBe(['https://api.twilio.com/2010-04-01/Accounts/'.TWILIO_SID.'/Messages/SM3333333333333333333333333333333c/Media/ME1']);
    });

    it('finds the messages that failed or were not delivered, newest first', function () {
        fakeTwilio(['GET /Messages.json' => ['messages' => [
            twilioMessage(['sid' => 'SM3333333333333333333333333333333a', 'status' => 'failed', 'error_code' => 30003, 'date_created' => 'Sun, 01 Mar 2026 10:00:00 +0000']),
            twilioMessage(['sid' => 'SM3333333333333333333333333333333b', 'status' => 'delivered']),
            twilioMessage(['sid' => 'SM3333333333333333333333333333333c', 'status' => 'undelivered', 'date_created' => 'Sun, 01 Mar 2026 11:00:00 +0000']),
            twilioMessage(['sid' => 'SM3333333333333333333333333333333d', 'status' => 'failed', 'direction' => 'inbound']),
        ]]]);

        $output = (new TwilioListFailedMessagesNode)->execute(twilioRun(), [], []);

        expect(array_column($output['messages'], 'sid'))->toBe(['SM3333333333333333333333333333333c', 'SM3333333333333333333333333333333a'])
            ->and($output['messages'][1]['error_code'])->toBe(30003);
    });
});

describe('twilio instant texts', function () {
    it('points the number\'s incoming-message URL at us, remembering what it was, only for a text trigger on one number', function () {
        $trigger = twilioTrigger('twilio_list_new_incoming_messages', ['to' => '+1 (415) 555-0123', 'channel' => 'sms'], twilioNumberRoutes());
        $watch = ProviderWatch::query()->sole();

        expect($watch->resource_key)->toBe('+14155550123')
            ->and($watch->external_id)->toBe('+14155550123')
            ->and($watch->meta)->toMatchArray(['number_sid' => TWILIO_NUMBER_SID, 'previous_sms_url' => 'https://demo.twilio.com/welcome/sms/reply/'])
            ->and($trigger->subscription->routing_key)->toBe('+14155550123')
            ->and($trigger->subscription->status)->toBe(SubscriptionStatus::Active);
        expect(twilioSent('POST /IncomingPhoneNumbers/'.TWILIO_NUMBER_SID.'.json'))->toBe(['SmsUrl' => url('/api/hooks/apps/twilio'), 'SmsMethod' => 'POST']);

        foreach ([['channel' => 'whatsapp', 'to' => '+14155550123'], ['channel' => 'sms'], ['channel' => 'sms', 'to' => 'not a number']] as $config) {
            $other = twilioTrigger('twilio_list_new_incoming_messages', $config, twilioNumberRoutes());
            expect($other->subscription)->toBeNull();
        }
    });

    it('starts one run for each text with the sender, body and pictures, and answers Twilio with empty TwiML', function () {
        $trigger = twilioTrigger('twilio_list_new_incoming_messages', ['to' => '+14155550123'], twilioNumberRoutes());

        twilioDelivery()->assertOk()->assertHeader('Content-Type', 'text/xml; charset=UTF-8');
        twilioDelivery()->assertOk();
        twilioDelivery(['MessageSid' => 'SM99999999999999999999999999999998', 'NumMedia' => '1', 'MediaUrl0' => 'https://api.twilio.com/media/ME1', 'MediaContentType0' => 'image/png'])->assertOk();

        $events = $trigger->events()->get()->keyBy('payload.sid');

        expect($events)->toHaveCount(2)
            ->and($events['SM99999999999999999999999999999999']->payload)->toMatchArray(['sid' => 'SM99999999999999999999999999999999', 'from' => '+14155550199', 'to' => '+14155550123', 'body' => 'Hello there', 'direction' => 'inbound', 'channel' => 'sms', 'from_city' => 'SAN FRANCISCO'])
            ->and($events['SM99999999999999999999999999999998']->payload)->toMatchArray(['media_urls' => ['https://api.twilio.com/media/ME1'], 'media_types' => ['image/png'], 'num_media' => 1]);
    });

    it('refuses a delivery with a wrong signature, another account\'s SID, or for a number nobody watches', function () {
        $trigger = twilioTrigger('twilio_list_new_incoming_messages', ['to' => '+14155550123'], twilioNumberRoutes());

        twilioDelivery(token: 'not-the-token')->assertUnauthorized();
        twilioDelivery(url: 'https://evil.test/api/hooks/apps/twilio')->assertUnauthorized();
        twilioDelivery(['AccountSid' => 'AC99999999999999999999999999999999'])->assertUnauthorized();
        twilioDelivery(['To' => '+14155550000'])->assertUnauthorized();
        test()->post('/api/hooks/apps/twilio', ['To' => '+14155550123'])->assertUnauthorized();

        expect($trigger->events()->count())->toBe(0);
    });

    it('is closed while the switch is off', function () {
        twilioTrigger('twilio_list_new_incoming_messages', ['to' => '+14155550123'], twilioNumberRoutes());
        config(['triggers.push.twilio.enabled' => false]);

        twilioDelivery()->assertNotFound();
    });

    it('will not take over a number that already sends texts elsewhere or answers with a TwiML app', function () {
        $elsewhere = twilioTrigger('twilio_list_new_incoming_messages', ['to' => '+14155550123'], twilioNumberRoutes('https://crm.example.test/sms'));

        expect($elsewhere->subscription->status)->toBe(SubscriptionStatus::Failed)
            ->and($elsewhere->subscription->last_error)->toContain('crm.example.test')->toContain('only one');

        $app = twilioTrigger('twilio_list_new_incoming_messages', ['to' => '+14155550123'], twilioNumberRoutes('', ['sms_application_sid' => 'AP11111111111111111111111111111111']));
        expect($app->subscription->status)->toBe(SubscriptionStatus::Failed)->and($app->subscription->last_error)->toContain('TwiML app');

        expect(ProviderWatch::query()->count())->toBe(0);
        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
    });

    it('says so when the account has no such number, and drops the watch when Twilio refuses the URL', function () {
        $none = twilioTrigger('twilio_list_new_incoming_messages', ['to' => '+14155550123'], ['GET /IncomingPhoneNumbers.json' => ['incoming_phone_numbers' => []]]);
        expect($none->subscription->last_error)->toContain('no phone number +14155550123');

        $refused = twilioTrigger('twilio_list_new_incoming_messages', ['to' => '+14155550123'], [...twilioNumberRoutes(), 'POST /IncomingPhoneNumbers/'.TWILIO_NUMBER_SID.'.json' => fn () => Http::response(['code' => 21402, 'message' => 'Url is not valid', 'status' => 400], 400)]);
        expect($refused->subscription->status)->toBe(SubscriptionStatus::Failed)->and($refused->subscription->last_error)->toContain('Url is not valid')
            ->and(ProviderWatch::query()->count())->toBe(0);
    });

    it('sets the URL again when it was cleared, says so when it was replaced, and puts the old one back when the watch goes', function () {
        twilioTrigger('twilio_list_new_incoming_messages', ['to' => '+14155550123'], twilioNumberRoutes());
        $watch = ProviderWatch::query()->sole();
        $source = app(TwilioSource::class);
        $ours = url('/api/hooks/apps/twilio');

        fakeTwilio(twilioNumberRoutes(''));
        app(WatchManager::class)->renew($source, $watch->fresh());
        expect(twilioSent('POST /IncomingPhoneNumbers/'.TWILIO_NUMBER_SID.'.json'))->toBe(['SmsUrl' => $ours, 'SmsMethod' => 'POST'])
            ->and($watch->fresh()->status)->toBe(SubscriptionStatus::Active);

        fakeTwilio(twilioNumberRoutes('https://crm.example.test/sms'));
        app(WatchManager::class)->renew($source, $watch->fresh());
        expect($watch->fresh()->status)->toBe(SubscriptionStatus::ReconnectNeeded)->and($watch->fresh()->last_error)->toContain('now sends incoming texts to another address');

        fakeTwilio(twilioNumberRoutes($ours));
        app(WatchManager::class)->remove($source, $watch->fresh());
        expect(twilioSent('POST /IncomingPhoneNumbers/'.TWILIO_NUMBER_SID.'.json'))->toBe(['SmsUrl' => 'https://demo.twilio.com/welcome/sms/reply/', 'SmsMethod' => 'POST'])
            ->and(ProviderWatch::query()->count())->toBe(0);
    });
});
