<?php

use App\Enums\Triggers\SubscriptionStatus;
use App\Jobs\Triggers\FireTriggerEvent;
use App\Jobs\Triggers\PollTrigger;
use App\Jobs\Triggers\RenewProviderWatch;
use App\Jobs\Triggers\SyncTriggerSubscription;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Triggers\ProviderEvent;
use App\Models\Triggers\ProviderWatch;
use App\Models\Triggers\Trigger;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Services\Triggers\Push\GoogleFormsSource;
use App\Services\Triggers\Push\WatchManager;
use App\Services\Triggers\Push\YouTubeUploadsSource;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

const YT_CHANNEL = 'UCabcdefghijklmnopqrstuv';

/**
 * A published workflow whose first step is a reader node switched on as a
 * trigger, with push switched on for the provider, and its subscription synced.
 *
 * @param  array<string, mixed>  $nodeConfig
 * @return array{0: Trigger, 1: ConnectorCredential}
 */
function googleTrigger(string $provider, string $nodeType, array $nodeConfig = [], array $config = []): array
{
    config([
        "triggers.push.{$provider}.enabled" => true,
        'triggers.push.google_forms.topic' => 'projects/p/topics/forms',
        'triggers.push.google_forms.verification_token' => 'tok',
        ...$config,
    ]);
    Queue::fake([FireTriggerEvent::class, PollTrigger::class]);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $connector = Connector::query()->firstWhere('key', $provider) ?? Connector::factory()->create(['key' => $provider]);
    $credential = ConnectorCredential::factory()->forWorkspace($workspace)->forConnector($connector)
        ->create(['data' => ['access_token' => 'token'], 'created_by' => $owner->id]);

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

    return [$trigger->fresh(), $credential];
}

/**
 * Headers of a Google push channel notification.
 *
 * @return array<string, string>
 */
function channelHeaders(ProviderWatch $watch, string $number = '2', string $state = 'update'): array
{
    return [
        'X-Goog-Channel-ID' => (string) $watch->external_id,
        'X-Goog-Channel-Token' => (string) $watch->secret,
        'X-Goog-Message-Number' => $number,
        'X-Goog-Resource-State' => $state,
    ];
}

describe('Sheets and Docs (a Drive channel on the file)', function () {
    it('watches the spreadsheet and pokes the poll when it changes', function () {
        Http::fake(['www.googleapis.com/drive/v3/files/sheet1/watch*' => Http::response(['resourceId' => 'res1', 'expiration' => (string) now()->addDays(6)->getTimestampMs()])]);

        [$trigger] = googleTrigger('google_sheets', 'google_sheets_get_values', ['spreadsheet_id' => 'sheet1', 'range' => 'Sheet1']);
        $watch = ProviderWatch::query()->sole();

        expect($watch->provider)->toBe('google_sheets')
            ->and($watch->resource_key)->toBe('file:sheet1')
            ->and($watch->meta['resource_id'])->toBe('res1')
            ->and($trigger->subscription->status)->toBe(SubscriptionStatus::Active)
            ->and($trigger->subscription->routing_key)->toBe($watch->external_id);
        Http::assertSent(fn ($request) => $request['address'] === url('/api/hooks/apps/google_sheets') && $request['type'] === 'web_hook' && $request['token'] === $watch->secret);

        $this->postJson('/api/hooks/apps/google_sheets', [], channelHeaders($watch))->assertOk();

        Queue::assertPushed(PollTrigger::class, fn (PollTrigger $job): bool => $job->trigger->is($trigger));
    });

    it('answers the first sync message without polling and refuses a wrong token', function () {
        Http::fake(['www.googleapis.com/drive/v3/files/sheet1/watch*' => Http::response(['resourceId' => 'res1'])]);
        googleTrigger('google_sheets', 'google_sheets_list_updated_rows', ['spreadsheet_id' => 'sheet1', 'range' => 'Sheet1']);
        $watch = ProviderWatch::query()->sole();

        $this->postJson('/api/hooks/apps/google_sheets', [], channelHeaders($watch, '1', 'sync'))->assertOk();
        $this->postJson('/api/hooks/apps/google_sheets', [], [...channelHeaders($watch), 'X-Goog-Channel-Token' => 'nope'])->assertUnauthorized();

        Queue::assertNotPushed(PollTrigger::class);
    });

    it('leaves a spreadsheet filled in at run time to polling', function () {
        Http::fake();

        [$trigger] = googleTrigger('google_sheets', 'google_sheets_get_values', ['spreadsheet_id' => '{{ nodes.a.output.id }}', 'range' => 'A']);

        expect($trigger->subscription)->toBeNull()
            ->and(ProviderWatch::query()->count())->toBe(0);
        Http::assertNothingSent();
    });

    it('watches a document for the updated trigger', function () {
        Http::fake(['www.googleapis.com/drive/v3/files/doc1/watch*' => Http::response(['resourceId' => 'res9'])]);

        googleTrigger('google_docs', 'google_docs_read_text', ['document_id' => 'doc1']);

        expect(ProviderWatch::query()->sole()->resource_key)->toBe('file:doc1');
    });
});

describe('Forms (a watch and a Pub/Sub topic)', function () {
    it('watches the form for responses and pokes the poll on a new one', function () {
        Http::fake(['forms.googleapis.com/v1/forms/form1/watches' => Http::response(['id' => 'w1', 'expireTime' => now()->addDays(7)->toIso8601String()])]);

        [$trigger] = googleTrigger('google_forms', 'google_forms_list_responses', ['form_id' => 'form1']);
        $watch = ProviderWatch::query()->sole();

        expect($watch->external_id)->toBe('w1')
            ->and($watch->meta['form_id'])->toBe('form1')
            ->and($trigger->subscription->routing_key)->toBe('form1')
            ->and($watch->renew_at->lt($watch->expires_at))->toBeTrue();
        Http::assertSent(fn ($request) => $request['watch'] === ['target' => ['topic' => ['topicName' => 'projects/p/topics/forms']], 'eventType' => 'RESPONSES']);

        $body = ['message' => ['messageId' => 'm1', 'attributes' => ['formId' => 'form1', 'eventType' => 'RESPONSES']], 'subscription' => 's'];
        $this->postJson('/api/hooks/apps/google_forms?token=tok', $body)->assertOk();

        Queue::assertPushed(PollTrigger::class, fn (PollTrigger $job): bool => $job->trigger->is($trigger));
        expect(ProviderEvent::query()->sole()->routing_key)->toBe('form1');
    });

    it('ignores a change to the form itself and refuses a wrong token', function () {
        Http::fake(['forms.googleapis.com/*' => Http::response(['id' => 'w1'])]);
        googleTrigger('google_forms', 'google_forms_list_responses', ['form_id' => 'form1']);

        $schema = ['message' => ['messageId' => 'm2', 'attributes' => ['formId' => 'form1', 'eventType' => 'SCHEMA']]];
        $this->postJson('/api/hooks/apps/google_forms?token=tok', $schema)->assertOk();
        $this->postJson('/api/hooks/apps/google_forms?token=nope', $schema)->assertUnauthorized();

        Queue::assertNotPushed(PollTrigger::class);
        expect(ProviderEvent::query()->count())->toBe(0);
    });

    it('renews the watch in place and removes it when it is no longer needed', function () {
        Http::fake([
            'forms.googleapis.com/v1/forms/form1/watches/w1:renew' => Http::response(['id' => 'w1', 'expireTime' => now()->addDays(7)->toIso8601String()]),
            'forms.googleapis.com/v1/forms/form1/watches/w1' => Http::response([]),
            'forms.googleapis.com/v1/forms/form1/watches' => Http::response(['id' => 'w1']),
        ]);
        googleTrigger('google_forms', 'google_forms_list_responses', ['form_id' => 'form1']);
        $watch = ProviderWatch::query()->sole();

        RenewProviderWatch::dispatchSync($watch->id);
        Http::assertSent(fn ($request) => $request->method() === 'POST' && str_ends_with($request->url(), '/watches/w1:renew'));

        app(WatchManager::class)->remove(app(GoogleFormsSource::class), $watch->fresh());
        Http::assertSent(fn ($request) => $request->method() === 'DELETE' && str_ends_with($request->url(), '/watches/w1'));
    });

    it('does nothing without its topic configured', function () {
        Http::fake();

        [$trigger] = googleTrigger('google_forms', 'google_forms_list_responses', ['form_id' => 'form1'], ['triggers.push.google_forms.topic' => null]);

        expect($trigger->subscription)->toBeNull()
            ->and(ProviderWatch::query()->count())->toBe(0);
        Http::assertNothingSent();
    });

    it('leaves a form filled in at run time to polling', function () {
        Http::fake();

        [$trigger] = googleTrigger('google_forms', 'google_forms_list_responses', ['form_id' => '{{ input.form }}']);

        expect($trigger->subscription)->toBeNull();
        Http::assertNothingSent();
    });
});

describe('YouTube (the WebSub feed)', function () {
    /**
     * @return array<string, string>
     */
    function websubQuery(string $topic, string $mode = 'subscribe'): array
    {
        return ['hub.mode' => $mode, 'hub.topic' => $topic, 'hub.challenge' => 'abc123', 'hub.lease_seconds' => '432000'];
    }

    function uploadFeed(string $channel = YT_CHANNEL, string $video = 'vid12345678'): string
    {
        return <<<XML
            <feed xmlns:yt="http://www.youtube.com/xml/schemas/2015" xmlns="http://www.w3.org/2005/Atom">
              <entry>
                <yt:videoId>{$video}</yt:videoId>
                <yt:channelId>{$channel}</yt:channelId>
                <title>New upload</title>
                <published>2026-01-01T10:00:00+00:00</published>
                <updated>2026-01-01T10:05:00+00:00</updated>
              </entry>
            </feed>
            XML;
    }

    function signedFeed($test, string $body, string $secret, string $algorithm = 'sha1'): TestResponse
    {
        return $test->call('POST', '/api/hooks/apps/google_youtube', [], [], [], [
            'CONTENT_TYPE' => 'application/atom+xml',
            'HTTP_X_HUB_SIGNATURE' => $algorithm.'='.hash_hmac($algorithm, $body, $secret),
        ], $body);
    }

    it('subscribes to the channel feed and pokes the poll when a video is published', function () {
        Http::fake(['pubsubhubbub.appspot.com/*' => Http::response('', 202)]);

        [$trigger] = googleTrigger('google_youtube', 'google_youtube_list_playlist_videos', ['channel_id' => YT_CHANNEL]);
        $watch = ProviderWatch::query()->sole();

        expect($watch->resource_key)->toBe('channel:'.YT_CHANNEL)
            ->and($watch->external_id)->toBe(YT_CHANNEL)
            ->and($trigger->subscription->routing_key)->toBe(YT_CHANNEL)
            ->and($watch->secret)->not->toBeEmpty();
        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && $request['hub.mode'] === 'subscribe'
            && $request['hub.topic'] === 'https://www.youtube.com/xml/feeds/videos.xml?channel_id='.YT_CHANNEL
            && $request['hub.callback'] === url('/api/hooks/apps/google_youtube')
            && $request['hub.secret'] === $watch->secret);

        signedFeed($this, uploadFeed(), $watch->secret)->assertOk();

        Queue::assertPushed(PollTrigger::class, fn (PollTrigger $job): bool => $job->trigger->is($trigger));
        $event = ProviderEvent::query()->sole();
        expect($event->routing_key)->toBe(YT_CHANNEL)
            ->and($event->payload['video_id'])->toBe('vid12345678');
    });

    it('confirms the hubs subscription check for a watched channel only', function () {
        Http::fake(['pubsubhubbub.appspot.com/*' => Http::response('', 202)]);
        googleTrigger('google_youtube', 'google_youtube_list_playlist_videos', ['channel_id' => YT_CHANNEL]);
        $topic = 'https://www.youtube.com/xml/feeds/videos.xml?channel_id=';

        $this->get('/api/hooks/apps/google_youtube?'.http_build_query(websubQuery($topic.YT_CHANNEL)))->assertOk()->assertSee('abc123', false);
        $this->get('/api/hooks/apps/google_youtube?'.http_build_query(websubQuery($topic.'UCzzzzzzzzzzzzzzzzzzzzzz')))->assertUnauthorized();
        $this->get('/api/hooks/apps/google_youtube?'.http_build_query(websubQuery($topic.YT_CHANNEL, 'denied')))->assertUnauthorized();
    });

    it('refuses a feed with a wrong or missing signature', function () {
        Http::fake(['pubsubhubbub.appspot.com/*' => Http::response('', 202)]);
        googleTrigger('google_youtube', 'google_youtube_list_playlist_videos', ['channel_id' => YT_CHANNEL]);
        $watch = ProviderWatch::query()->sole();

        signedFeed($this, uploadFeed(), 'not-the-secret')->assertUnauthorized();
        $this->call('POST', '/api/hooks/apps/google_youtube', [], [], [], ['CONTENT_TYPE' => 'application/atom+xml'], uploadFeed())->assertUnauthorized();
        signedFeed($this, uploadFeed('UCunknownunknownunknownu'), $watch->secret)->assertUnauthorized();
        signedFeed($this, 'not xml', $watch->secret)->assertUnauthorized();

        Queue::assertNotPushed(PollTrigger::class);
    });

    it('accepts a sha256 signature', function () {
        Http::fake(['pubsubhubbub.appspot.com/*' => Http::response('', 202)]);
        googleTrigger('google_youtube', 'google_youtube_list_playlist_videos', ['channel_id' => YT_CHANNEL]);

        signedFeed($this, uploadFeed(), ProviderWatch::query()->sole()->secret, 'sha256')->assertOk();

        Queue::assertPushed(PollTrigger::class);
    });

    it('ignores a feed that has no video, such as a deleted entry', function () {
        Http::fake(['pubsubhubbub.appspot.com/*' => Http::response('', 202)]);
        googleTrigger('google_youtube', 'google_youtube_list_playlist_videos', ['channel_id' => YT_CHANNEL]);
        $watch = ProviderWatch::query()->sole();
        $deleted = '<feed xmlns="http://www.w3.org/2005/Atom" xmlns:at="http://purl.org/atompub/tombstones/1.0"><at:deleted-entry ref="yt:video:x"/></feed>';

        // No entry names a channel, so there is nothing to check the signature against.
        signedFeed($this, $deleted, $watch->secret)->assertUnauthorized();
        expect(ProviderEvent::query()->count())->toBe(0);
    });

    it('leaves a playlist, or a channel filled in at run time, to polling', function () {
        Http::fake(['pubsubhubbub.appspot.com/*' => Http::response('', 202)]);

        [$playlist] = googleTrigger('google_youtube', 'google_youtube_list_playlist_videos', ['playlist_id' => 'PL123', 'channel_id' => YT_CHANNEL]);
        [$template] = googleTrigger('google_youtube', 'google_youtube_list_playlist_videos', ['channel_id' => '{{ input.channel }}']);

        expect($playlist->subscription)->toBeNull()
            ->and($template->subscription)->toBeNull()
            ->and(ProviderWatch::query()->count())->toBe(0);
        Http::assertNothingSent();
    });

    it('unsubscribes from the feed when the watch is removed', function () {
        Http::fake(['pubsubhubbub.appspot.com/*' => Http::response('', 202)]);
        googleTrigger('google_youtube', 'google_youtube_list_playlist_videos', ['channel_id' => YT_CHANNEL]);

        app(WatchManager::class)->remove(app(YouTubeUploadsSource::class), ProviderWatch::query()->sole());

        Http::assertSent(fn ($request) => $request['hub.mode'] === 'unsubscribe');
    });
});

it('keeps the push switch for each new provider off by default', function () {
    foreach (['google_sheets', 'google_docs', 'google_forms', 'google_youtube'] as $provider) {
        expect(config("triggers.push.{$provider}.enabled"))->toBeFalse();
    }
});
