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
use App\Models\Triggers\TriggerSubscription;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Nodes\Integrations\GitHub\GitHubListIssuesNode;
use App\Services\Triggers\Push\GmailMessageSource;
use App\Services\Triggers\Push\WatchManager;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

/**
 * A published workflow whose reader node is switched on as a flow trigger,
 * with that provider's push on.
 *
 * @param  array<string, mixed>  $nodeConfig
 * @return array{0: Workspace, 1: User, 2: Trigger, 3: ConnectorCredential}
 */
function watchedTrigger(string $provider, string $nodeType, array $nodeConfig = [], ?Workspace $workspace = null, ?User $owner = null, ?ConnectorCredential $credential = null): array
{
    config([
        "triggers.push.{$provider}.enabled" => true,
        'triggers.push.gmail.topic' => 'projects/p/topics/gmail',
        'triggers.push.gmail.verification_token' => 'tok',
    ]);
    Queue::fake([FireTriggerEvent::class, PollTrigger::class]);

    $owner ??= User::factory()->create();
    $workspace ??= app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $connector = Connector::query()->firstWhere('key', $provider) ?? Connector::factory()->create(['key' => $provider]);
    $credential ??= ConnectorCredential::factory()->forWorkspace($workspace)->forConnector($connector)
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

    return [$workspace, $owner, $trigger->fresh(), $credential];
}

function fakeGmail(): void
{
    Http::fake([
        'gmail.googleapis.com/gmail/v1/users/me/profile' => Http::response(['emailAddress' => 'Me@Example.com']),
        'gmail.googleapis.com/gmail/v1/users/me/watch' => Http::response(['historyId' => '1', 'expiration' => (string) now()->addDays(7)->getTimestampMs()]),
        'gmail.googleapis.com/gmail/v1/users/me/stop' => Http::response([]),
    ]);
}

function pubsubBody(string $email, string $messageId): string
{
    return json_encode(['message' => ['messageId' => $messageId, 'data' => base64_encode(json_encode(['emailAddress' => $email, 'historyId' => '9']))], 'subscription' => 's']);
}

describe('Gmail', function () {
    it('makes one watch per mailbox and shares it between triggers', function () {
        fakeGmail();

        [$workspace, $owner, $first, $credential] = watchedTrigger('gmail', 'gmail_list_messages');
        [, , $second] = watchedTrigger('gmail', 'gmail_list_messages', ['query' => 'is:starred'], $workspace, $owner, $credential);

        $watch = ProviderWatch::query()->sole();

        expect($first->subscription->watch_id)->toBe($watch->id)
            ->and($second->subscription->watch_id)->toBe($watch->id)
            ->and($first->subscription->routing_key)->toBe('me@example.com')
            ->and($first->subscription->status)->toBe(SubscriptionStatus::Active)
            ->and($watch->renew_at->isFuture())->toBeTrue();
        Http::assertSentCount(2); // profile + watch, once
    });

    it('pokes the trigger to poll when Pub/Sub says the mailbox changed', function () {
        fakeGmail();
        [, , $trigger] = watchedTrigger('gmail', 'gmail_list_messages');

        $this->call('POST', '/api/hooks/apps/gmail?token=tok', [], [], [], ['CONTENT_TYPE' => 'application/json'], pubsubBody('me@example.com', 'm1'))->assertOk();

        Queue::assertPushed(PollTrigger::class, fn (PollTrigger $job): bool => $job->trigger->is($trigger));
        expect(Cache::get(PollTrigger::pokeKey($trigger)))->toBeTrue()
            ->and(ProviderEvent::query()->sole()->routing_key)->toBe('me@example.com');
    });

    it('refuses a push with the wrong token', function () {
        fakeGmail();
        watchedTrigger('gmail', 'gmail_list_messages');

        $this->call('POST', '/api/hooks/apps/gmail?token=nope', [], [], [], ['CONTENT_TYPE' => 'application/json'], pubsubBody('me@example.com', 'm1'))->assertUnauthorized();
    });

    it('is not available until it has a topic and a token', function () {
        config(['triggers.push.gmail.enabled' => true, 'triggers.push.gmail.topic' => null, 'triggers.push.gmail.verification_token' => null]);

        expect(app(GmailMessageSource::class)->isEnabled())->toBeFalse();
    });
});

describe('watch lifecycle', function () {
    it('renews a watch that is due', function () {
        fakeGmail();
        [, , $trigger] = watchedTrigger('gmail', 'gmail_list_messages');
        $watch = ProviderWatch::query()->sole();
        $watch->forceFill(['renew_at' => now()->subMinute(), 'expires_at' => now()->addHour()])->save();
        Queue::fake([RenewProviderWatch::class]);

        $this->artisan('triggers:renew-watches')->assertSuccessful();

        Queue::assertPushed(RenewProviderWatch::class, fn (RenewProviderWatch $job): bool => $job->watchId === $watch->id);

        Queue::fake([FireTriggerEvent::class]);
        RenewProviderWatch::dispatchSync($watch->id);

        expect($watch->fresh()->renew_at->isFuture())->toBeTrue()
            ->and($watch->fresh()->expires_at->gt(now()->addDays(6)))->toBeTrue();
    });

    it('asks for a reconnect, on the watch and its triggers, when the account has no usable token', function () {
        fakeGmail();
        [, , $trigger, $credential] = watchedTrigger('gmail', 'gmail_list_messages');
        $watch = ProviderWatch::query()->sole();
        $credential->update(['data' => []]);

        app(WatchManager::class)->renew(app(GmailMessageSource::class), $watch);

        expect($watch->fresh()->status)->toBe(SubscriptionStatus::ReconnectNeeded)
            ->and($trigger->subscription->fresh()->status)->toBe(SubscriptionStatus::ReconnectNeeded);
    });

    it('keeps the watch for a while after its last trigger leaves, then removes it', function () {
        fakeGmail();
        [, , $trigger] = watchedTrigger('gmail', 'gmail_list_messages');
        $watch = ProviderWatch::query()->sole();

        $trigger->forceFill(['is_active' => false])->save();
        SyncTriggerSubscription::dispatchSync($trigger->id);

        expect(TriggerSubscription::query()->count())->toBe(0)
            ->and($watch->fresh()->release_at)->not->toBeNull();

        $this->artisan('triggers:collect-watches')->assertSuccessful();
        expect(ProviderWatch::query()->count())->toBe(1);

        $this->travel(15)->minutes();
        $this->artisan('triggers:collect-watches')->assertSuccessful();

        expect(ProviderWatch::query()->count())->toBe(0);
        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/users/me/stop'));
    });

    it('keeps a watch another trigger still uses', function () {
        fakeGmail();
        [$workspace, $owner, $first, $credential] = watchedTrigger('gmail', 'gmail_list_messages');
        watchedTrigger('gmail', 'gmail_list_messages', ['query' => 'x'], $workspace, $owner, $credential);

        $first->forceFill(['is_active' => false])->save();
        SyncTriggerSubscription::dispatchSync($first->id);

        expect(ProviderWatch::query()->sole()->release_at)->toBeNull();
    });

    it('prints a summary', function () {
        fakeGmail();
        watchedTrigger('gmail', 'gmail_list_messages');

        $this->artisan('triggers:subscriptions')->assertSuccessful();
    });
});

describe('Outlook', function () {
    it('creates a Graph subscription and answers the validation handshake', function () {
        Http::fake(['graph.microsoft.com/v1.0/subscriptions' => Http::response(['id' => 'sub-1', 'expirationDateTime' => now()->addDays(3)->toIso8601ZuluString()])]);
        [, , $trigger] = watchedTrigger('outlook', 'outlook_list_messages', ['folder' => 'inbox']);

        expect($trigger->subscription->routing_key)->toBe('sub-1');

        $this->postJson('/api/hooks/apps/outlook?validationToken=abc%20123')->assertOk()->assertSee('abc 123');
    });

    it('pokes on a notification with the right client state and refuses one with the wrong state', function () {
        Http::fake(['graph.microsoft.com/v1.0/subscriptions' => Http::response(['id' => 'sub-1', 'expirationDateTime' => now()->addDays(3)->toIso8601ZuluString()])]);
        [, , $trigger] = watchedTrigger('outlook', 'outlook_list_messages');
        $secret = ProviderWatch::query()->sole()->secret;

        $this->postJson('/api/hooks/apps/outlook', ['value' => [['subscriptionId' => 'sub-1', 'clientState' => 'wrong', 'changeType' => 'created']]])->assertUnauthorized();
        $this->postJson('/api/hooks/apps/outlook', ['value' => [['subscriptionId' => 'sub-1', 'clientState' => $secret, 'changeType' => 'created', 'resource' => 'x']]])->assertOk();

        Queue::assertPushed(PollTrigger::class, fn (PollTrigger $job): bool => $job->trigger->is($trigger));
    });

    it('makes a new subscription when Graph has forgotten the old one', function () {
        Http::fake([
            'graph.microsoft.com/v1.0/subscriptions/sub-1' => Http::response([], 404),
            'graph.microsoft.com/v1.0/subscriptions' => Http::sequence()
                ->push(['id' => 'sub-1', 'expirationDateTime' => now()->addDays(3)->toIso8601ZuluString()])
                ->push(['id' => 'sub-2', 'expirationDateTime' => now()->addDays(3)->toIso8601ZuluString()]),
        ]);
        [, , $trigger] = watchedTrigger('outlook', 'outlook_list_messages');

        RenewProviderWatch::dispatchSync(ProviderWatch::query()->sole()->id);

        expect($trigger->subscription->fresh()->routing_key)->toBe('sub-2');
    });
});

describe('Google Drive', function () {
    it('opens a channel and routes its notifications', function () {
        Http::fake([
            'www.googleapis.com/drive/v3/changes/startPageToken' => Http::response(['startPageToken' => '7']),
            'www.googleapis.com/drive/v3/changes/watch*' => Http::response(['resourceId' => 'res-1', 'expiration' => (string) now()->addDays(6)->getTimestampMs()]),
        ]);
        [, , $trigger] = watchedTrigger('google_drive', 'google_drive_list_files');
        $watch = ProviderWatch::query()->sole();

        expect($trigger->subscription->routing_key)->toBe($watch->external_id);

        $headers = fn (string $state, ?string $token = null): array => [
            'HTTP_X_GOOG_CHANNEL_ID' => $watch->external_id,
            'HTTP_X_GOOG_CHANNEL_TOKEN' => $token ?? $watch->secret,
            'HTTP_X_GOOG_RESOURCE_STATE' => $state,
            'HTTP_X_GOOG_MESSAGE_NUMBER' => '2',
        ];

        $this->call('POST', '/api/hooks/apps/google_drive', [], [], [], $headers('sync'))->assertOk();
        expect(ProviderEvent::query()->count())->toBe(0);

        $this->call('POST', '/api/hooks/apps/google_drive', [], [], [], $headers('add', 'wrong'))->assertUnauthorized();

        $this->call('POST', '/api/hooks/apps/google_drive', [], [], [], $headers('add'))->assertOk();
        Queue::assertPushed(PollTrigger::class, fn (PollTrigger $job): bool => $job->trigger->is($trigger));
    });

    it('opens the new channel before closing the old one when renewing', function () {
        Http::fake([
            'www.googleapis.com/drive/v3/changes/startPageToken' => Http::response(['startPageToken' => '7']),
            'www.googleapis.com/drive/v3/changes/watch*' => Http::response(['resourceId' => 'res-1', 'expiration' => (string) now()->addDays(6)->getTimestampMs()]),
            'www.googleapis.com/drive/v3/channels/stop' => Http::response([]),
        ]);
        [, , $trigger] = watchedTrigger('google_drive', 'google_drive_list_files');
        $oldChannel = ProviderWatch::query()->sole()->external_id;

        RenewProviderWatch::dispatchSync(ProviderWatch::query()->sole()->id);

        expect(ProviderWatch::query()->sole()->external_id)->not->toBe($oldChannel)
            ->and($trigger->subscription->fresh()->routing_key)->toBe(ProviderWatch::query()->sole()->external_id);
        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/channels/stop') && $request['id'] === $oldChannel);
    });
});

describe('Google Calendar', function () {
    it('watches the chosen calendar', function () {
        Http::fake(['www.googleapis.com/calendar/v3/calendars/team%40example.com/events/watch' => Http::response(['resourceId' => 'r', 'expiration' => (string) now()->addDays(6)->getTimestampMs()])]);

        [, , $trigger] = watchedTrigger('google_calendar', 'google_calendar_list_events', ['calendar_id' => 'team@example.com']);

        expect(ProviderWatch::query()->sole()->resource_key)->toBe('calendar:team@example.com')
            ->and($trigger->subscription->status)->toBe(SubscriptionStatus::Active);
    });
});

describe('GitHub', function () {
    it('creates a repository hook and checks its signature', function () {
        Http::fake(['api.github.com/repos/acme/widgets/hooks' => Http::response(['id' => 42])]);
        [, , $trigger] = watchedTrigger('github', 'github_list_issues', ['repo' => 'Acme/Widgets']);
        $watch = ProviderWatch::query()->sole();
        $body = json_encode(['action' => 'opened', 'repository' => ['full_name' => 'acme/widgets']]);
        $send = fn (string $signature): TestResponse => $this->call('POST', '/api/hooks/apps/github', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_GITHUB_HOOK_ID' => '42',
            'HTTP_X_GITHUB_DELIVERY' => 'd-'.$signature,
            'HTTP_X_GITHUB_EVENT' => 'issues',
            'HTTP_X_HUB_SIGNATURE_256' => $signature,
        ], $body);

        expect($trigger->subscription->routing_key)->toBe('acme/widgets#42');

        $send('sha256=bad')->assertUnauthorized();
        $send('sha256='.hash_hmac('sha256', $body, $watch->secret))->assertOk();

        Queue::assertPushed(PollTrigger::class, fn (PollTrigger $job): bool => $job->trigger->is($trigger));
    });

    it('keeps polling and says why when GitHub refuses the hook', function () {
        Http::fake(['api.github.com/repos/acme/widgets/hooks' => Http::response(['message' => 'Not Found'], 404)]);

        [, , $trigger] = watchedTrigger('github', 'github_list_issues', ['repo' => 'acme/widgets']);

        expect($trigger->subscription->status)->toBe(SubscriptionStatus::Failed)
            ->and($trigger->subscription->last_error)->toContain('Instant delivery is not available')
            ->and($trigger->pollIntervalMinutes())->toBe((new GitHubListIssuesNode)->triggerPollMinutes());
    });
});

describe('when push falls behind', function () {
    it('goes back to checking on a timer when a reconciliation check finds something push missed', function () {
        Http::fake([
            'gmail.googleapis.com/gmail/v1/users/me/profile' => Http::response(['emailAddress' => 'me@example.com']),
            'gmail.googleapis.com/gmail/v1/users/me/watch' => Http::response(['historyId' => '1', 'expiration' => (string) now()->addDays(7)->getTimestampMs()]),
            'gmail.googleapis.com/gmail/v1/users/me/messages/m1*' => Http::response(['id' => 'm1', 'payload' => ['headers' => []]]),
            'gmail.googleapis.com/gmail/v1/users/me/messages?*' => Http::sequence()->push(['messages' => []])->push(['messages' => [['id' => 'm1']]]),
        ]);
        [, , $trigger] = watchedTrigger('gmail', 'gmail_list_messages');
        Queue::fake([FireTriggerEvent::class]);

        dispatch_sync(new PollTrigger($trigger->fresh())); // baseline
        dispatch_sync(new PollTrigger($trigger->fresh())); // nobody poked it, yet there is mail

        expect($trigger->subscription->fresh()->status)->toBe(SubscriptionStatus::Degraded);
    });

    it('does not count mail found by a poll that push asked for', function () {
        Http::fake([
            'gmail.googleapis.com/gmail/v1/users/me/profile' => Http::response(['emailAddress' => 'me@example.com']),
            'gmail.googleapis.com/gmail/v1/users/me/watch' => Http::response(['historyId' => '1', 'expiration' => (string) now()->addDays(7)->getTimestampMs()]),
            'gmail.googleapis.com/gmail/v1/users/me/messages/m1*' => Http::response(['id' => 'm1', 'payload' => ['headers' => []]]),
            'gmail.googleapis.com/gmail/v1/users/me/messages?*' => Http::sequence()->push(['messages' => []])->push(['messages' => [['id' => 'm1']]]),
        ]);
        [, , $trigger] = watchedTrigger('gmail', 'gmail_list_messages');
        Queue::fake([FireTriggerEvent::class]);

        dispatch_sync(new PollTrigger($trigger->fresh()));
        Cache::put(PollTrigger::pokeKey($trigger), true, 60);
        dispatch_sync(new PollTrigger($trigger->fresh()));

        expect($trigger->subscription->fresh()->status)->toBe(SubscriptionStatus::Active);
    });

    it('recovers when push delivers again', function () {
        Http::fake([
            'gmail.googleapis.com/gmail/v1/users/me/profile' => Http::response(['emailAddress' => 'me@example.com']),
            'gmail.googleapis.com/gmail/v1/users/me/watch' => Http::response(['historyId' => '1', 'expiration' => (string) now()->addDays(7)->getTimestampMs()]),
        ]);
        [, , $trigger] = watchedTrigger('gmail', 'gmail_list_messages');
        $trigger->subscription->forceFill(['status' => SubscriptionStatus::Degraded, 'last_error' => 'x'])->save();

        $this->call('POST', '/api/hooks/apps/gmail?token=tok', [], [], [], ['CONTENT_TYPE' => 'application/json'], pubsubBody('me@example.com', 'm2'))->assertOk();

        expect($trigger->subscription->fresh()->status)->toBe(SubscriptionStatus::Active)
            ->and($trigger->subscription->fresh()->last_error)->toBeNull();
    });

    it('polls again within a minute when it was poked while a poll was running', function () {
        $pokeKey = null;
        Http::fake([
            'gmail.googleapis.com/gmail/v1/users/me/profile' => Http::response(['emailAddress' => 'me@example.com']),
            'gmail.googleapis.com/gmail/v1/users/me/watch' => Http::response(['historyId' => '1', 'expiration' => (string) now()->addDays(7)->getTimestampMs()]),
            // The poke lands while the poll is reading.
            'gmail.googleapis.com/gmail/v1/users/me/messages*' => function () use (&$pokeKey) {
                Cache::put($pokeKey, true, 60);

                return Http::response(['messages' => []]);
            },
        ]);
        [, , $trigger] = watchedTrigger('gmail', 'gmail_list_messages');
        Queue::fake([FireTriggerEvent::class]);
        $pokeKey = PollTrigger::pokeKey($trigger);

        dispatch_sync(new PollTrigger($trigger->fresh()));

        expect($trigger->fresh()->next_poll_at->lte(now()->addSecond()))->toBeTrue();
    });
});

describe('Gmail labels', function () {
    function gmailWatchBodies(): array
    {
        return Http::recorded()
            ->filter(fn (array $pair): bool => str_ends_with($pair[0]->url(), '/users/me/watch'))
            ->map(fn (array $pair): array => $pair[0]->data())
            ->values()
            ->all();
    }

    it('listens to the label a trigger is about, not only the Inbox', function () {
        fakeGmail();

        watchedTrigger('gmail', 'gmail_list_messages', ['label_ids' => ['SENT', 'STARRED']]);

        expect(gmailWatchBodies())->toHaveCount(1)
            ->and(gmailWatchBodies()[0]['labelIds'])->toBe(['SENT']);
    });

    it('widens the shared watch when another trigger on the mailbox needs more, and only then', function () {
        fakeGmail();
        [$workspace, $owner, , $credential] = watchedTrigger('gmail', 'gmail_list_messages', ['label_ids' => ['SENT']]);

        watchedTrigger('gmail', 'gmail_list_messages', [], $workspace, $owner, $credential);
        watchedTrigger('gmail', 'gmail_list_messages', ['label_ids' => ['INBOX']], $workspace, $owner, $credential);
        $bodies = gmailWatchBodies();

        // First: Sent. Second (no filter, so the Inbox): both. Third: already covered, no new call.
        expect(array_column($bodies, 'labelIds'))->toBe([['SENT'], ['INBOX', 'SENT']]);
    });
});

describe('Outlook calendar', function () {
    it('subscribes to the calendar once for every calendar trigger, apart from the mail subscription', function () {
        Http::fake(['graph.microsoft.com/v1.0/subscriptions' => Http::sequence()
            ->push(['id' => 'sub-cal', 'expirationDateTime' => now()->addDays(3)->toIso8601ZuluString()])
            ->push(['id' => 'sub-mail', 'expirationDateTime' => now()->addDays(3)->toIso8601ZuluString()])]);

        [$workspace, $owner, $first, $credential] = watchedTrigger('outlook', 'outlook_list_new_events');
        [, , $second] = watchedTrigger('outlook', 'outlook_list_upcoming_events', [], $workspace, $owner, $credential);
        [, , $mail] = watchedTrigger('outlook', 'outlook_list_messages', [], $workspace, $owner, $credential);

        $created = Http::recorded()->map(fn (array $pair): array => $pair[0]->data())->values();

        expect($created)->toHaveCount(2)
            ->and($created[0])->toMatchArray(['resource' => 'me/events', 'changeType' => 'created,updated,deleted'])
            ->and($created[1]['resource'])->toBe("me/mailFolders('inbox')/messages")
            ->and($first->subscription->routing_key)->toBe('sub-cal')
            ->and($second->subscription->routing_key)->toBe('sub-cal')
            ->and($mail->subscription->routing_key)->toBe('sub-mail');
    });

    it('wakes a calendar trigger when the calendar changes', function () {
        Http::fake(['graph.microsoft.com/v1.0/subscriptions' => Http::response(['id' => 'sub-cal', 'expirationDateTime' => now()->addDays(3)->toIso8601ZuluString()])]);
        [, , $trigger] = watchedTrigger('outlook', 'outlook_list_updated_events');
        $secret = ProviderWatch::query()->sole()->secret;

        $this->postJson('/api/hooks/apps/outlook', ['value' => [['subscriptionId' => 'sub-cal', 'clientState' => $secret, 'changeType' => 'updated', 'resource' => 'Users/u/Events/e1']]])->assertOk();

        Queue::assertPushed(PollTrigger::class, fn (PollTrigger $job): bool => $job->trigger->is($trigger));
    });
});
